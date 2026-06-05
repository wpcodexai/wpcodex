# WPCodex — Research & Development Notes

> **Location:** `docs/RND.md`  
> **Status:** Active pre-release development  
> **Last updated:** June 2026

This document tracks open research questions, architectural decisions, experiment logs, and the forward roadmap for WPCodex. It is a living document — update it as decisions are made or invalidated.

---

## 1. Vision & Goals

### What WPCodex Is

WPCodex is a WordPress MCP server plugin. It exposes low-level WordPress primitives (PHP execution, WP-CLI, `$wpdb`, filesystem) as MCP tools so any compatible AI agent can build and manage WordPress sites with full autonomy.

It is a clean-room implementation inspired by Novamira, built under GPL-3.0, with a PSR-4 class structure, a strict `includes/` source layout, and a first-class Skills system from day one.

### What It Is Not

- **Not a hosted proxy.** The AI connects directly to the WordPress server.
- **Not a code generator.** It executes real code on real sites.
- **Not production-safe by default.** Designed for dev/staging workflows with proper backups.

### Core Design Principles

1. **Primitives, not opinions.** WPCodex exposes raw capabilities. The AI decides how to compose them.
2. **Direct connection.** No SaaS layer between the AI client and the WordPress install.
3. **Transparent execution.** Every action the AI takes is logged and reviewable.
4. **Recoverable by design.** PHP sandbox uses temp files. File writes are atomic with `.bak` backups.
5. **PSR-4 throughout.** All PHP under `includes/` with the `WPCodex\` namespace root.
6. **Standards-first.** Use official WordPress infrastructure where it exists rather than reinventing it.

---

## 2. Architecture Decisions

### 2.1 Directory Layout vs. Novamira

Novamira uses a flat procedural `includes/` with `require_once` chains. WPCodex uses the same `includes/` root but with PSR-4 autoloading and a class-per-file structure. This gives us static analysis, testability, and IDE navigation without diverging from the familiar `includes/` convention WordPress developers expect.

| Concern | Novamira | WPCodex |
|---|---|---|
| PHP loading | `require_once` chains | PSR-4 via Jetpack Autoloader (`autoload_packages.php`) |
| Namespace root | None (procedural) | `WPCodex\` → `includes/` |
| MCP transport | `wordpress/mcp-adapter` (bundled in `vendor/`) | `wordpress/mcp-adapter` (same — bundled in `vendor/`) |
| Tool registration | WordPress Abilities API (`wp_register_ability()`) | WordPress Abilities API (`wp_register_ability()`) — same |
| WP version minimum | 6.9 (Abilities API in core) | 6.9 (Abilities API in core) |
| Skills storage | WordPress database | WordPress database (same) |
| Skills admin | Novamira → Skills UI | WPCodex → Skills UI |
| Front-end source | `assets/` (direct) | `src/` (TS/SCSS) → compiled to `assets/` |

### 2.2 MCP Transport — WordPress MCP Adapter

**Decision:** Use `wordpress/mcp-adapter` as the MCP transport layer, bundled in `vendor/` via the Jetpack Autoloader. Register all tools via the WordPress Abilities API (`wp_register_ability()`).

**Why this decision was made:**

The WordPress MCP Adapter (`wordpress/mcp-adapter`) is the official WordPress package for MCP integration, maintained by the WordPress AI Team. It is at v0.5.0 (April 2026), stable, GPL-2.0-or-later, with 74,000+ installs. It is part of the [AI Building Blocks for WordPress](https://make.wordpress.org/ai/) initiative and is the canonical, long-term approach.

The Abilities API has been part of WordPress core since **6.9**. It is no longer a separate plugin — it is a native WordPress API, as standard as `WP_Query` or the REST API.

**The full three-layer stack:**

```
AI client (Claude Code, Cursor, Gemini CLI, etc.)
    │
    │  MCP JSON-RPC 2.0 over HTTPS
    ▼
wordpress/mcp-adapter  ←  bundled in vendor/ via Jetpack Autoloader
    │
    │  reads the registry at request time
    ▼
WordPress Abilities API  ←  wp_register_ability() / wp_get_abilities()  (WP 6.9 core)
    │
    │  dispatches to execute_callback
    ▼
WPCodex ability files  ←  includes/Abilities/*.php
    │
    │  delegates to Runner classes
    ▼
includes/Runner/  ←  PhpRunner, CliRunner, DbRunner, FileManager
```

**What this means in practice:**

- `includes/MCP/Server.php`, `ToolRegistry.php`, and all `includes/MCP/Tool/*.php` wrapper classes are **removed**. The Adapter handles all JSON-RPC routing.
- Each tool becomes an ability file in `includes/Abilities/` that calls `wp_register_ability('wpcodex/{name}', [...])` on `wp_abilities_api_init`.
- All Runner classes (`PhpRunner`, `CliRunner`, `DbRunner`, `FileManager`) are **unchanged** — they contain the real logic and are called from each ability's `execute_callback`.
- The entry point (`wpcodex.php`) loads `vendor/autoload_packages.php` (Jetpack Autoloader) and initialises the Adapter via `\WP\MCP\Core\McpAdapter::instance()`.

**Jetpack Autoloader:**

When multiple plugins on the same site bundle `wordpress/mcp-adapter`, they must not load conflicting versions. The Jetpack Autoloader (`automattic/jetpack-autoloader`) solves this: it ensures only the newest version of any shared package is loaded across all active plugins. WPCodex uses `autoload_packages.php` instead of `autoload.php`:

```php
// wpcodex.php
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
```

**Release build:**

The `vendor/` directory is **not committed to the Git repository**. It is added to the release ZIP by the CI build pipeline (`composer install --no-dev` → ZIP). This matches Novamira's approach exactly and is the standard WordPress plugin release pattern.

**Why not build a custom transport:**
- The MCP specification evolves. Every spec update would require manual changes to `Server.php`.
- The Adapter already handles HTTP transport, STDIO transport, session management, error handling, observability, and MCP component validation. Building all of this correctly is months of work.
- Tools registered via `wp_register_ability()` are automatically available to any MCP client using the standard WordPress MCP Adapter — not just WPCodex. WPCodex becomes interoperable with the broader WordPress MCP ecosystem.

### 2.3 PHP Execution Sandbox

**Decision for v1.0:** Temp file + `include()` in `wpcodex-sandbox/` inside `WP_CONTENT_DIR`.

Execution flow:
1. Write `<?php declare(strict_types=1); {code}` to a uniquely named temp file
2. Capture output via `ob_start()`
3. `include` the file inside a `try/catch(\Throwable)`
4. Always `unlink()` the temp file — even on exception
5. Return captured output + serialised return value

**Why not `eval()`?** Disabled on many shared hosting environments. Temp file approach works on all tested hosts (WP Engine, Kinsta, SiteGround, Flywheel, plain LAMP).

**Sandbox directory:** `WP_CONTENT_DIR . '/wpcodex-sandbox/'` — created on activation, protected by `.htaccess Deny from all` and an `index.php` stub. Lives outside the plugin so it survives updates.

**Open question (v1.1):** A `WPCODEX_SAFE_MODE` constant that switches to subprocess isolation (`proc_open` + separate PHP process with `disable_functions` restrictions) for teams that want tighter isolation on staging.

### 2.4 WP-CLI Integration

**Decision:** `proc_open` subprocess. WP-CLI binary resolved from `WP_CLI_PHP` constant, common paths (`/usr/local/bin/wp`, `/usr/bin/wp`), and `which wp`.

Key safety points:
- `WPCODEX_SECRET` and `HTTP_AUTHORIZATION` stripped from subprocess environment
- Default timeout: 30 seconds (configurable via `WPCODEX_CLI_TIMEOUT` constant)
- Long-running commands get killed with a logged message — background job queue deferred to v1.1
- `--path=ABSPATH --no-color 2>&1` always appended

**Experiment EXP-002 finding:** 30s default is insufficient for bulk operations (`wp post generate --count=1000` took 4m12s). Document the constant and defer bulk support to the job queue.

### 2.5 Authentication

Authentication is handled by the `wordpress/mcp-adapter` using **WordPress Application Passwords** over HTTPS.

- Application Passwords are a native WordPress feature (since WP 5.6)
- The AI client sends `Authorization: Basic base64(username:app-password)` with every MCP request
- The Adapter validates the Application Password against the WordPress user table
- The permission check on each ability (`permission_callback`) confirms the authenticated user has `manage_options` capability

**Why Application Passwords instead of a custom secret key:**
- No key generation, storage, or rotation logic to maintain
- Revoke access for any client individually from the WordPress admin (Users → Application Passwords)
- Audit trail: WordPress logs Application Password usage
- Per-client access control out of the box — create one Application Password per AI client

**Deferred to v1.1:** Expose Application Password creation on the WPCodex Connect page for one-step setup.

### 2.6 File Operations

Every write operation in `FileManager`:
1. Validates path is within `ABSPATH` (blocks traversal)
2. Creates a `.bak` copy of the existing file
3. Registers backup in a transient (`wpcodex_bak_{md5(path)}`, 24h TTL) for admin restore UI
4. Writes content to a temp file (`{path}.tmp_{random}`)
5. `rename()` temp to target — atomic on POSIX systems

**Deferred to v1.1:** One-click restore UI in the admin using the backup transients.

### 2.7 Skills Storage — Database, Not Files

**Decision:** Skills are stored in the **WordPress database**, managed from the admin UI and via dedicated MCP abilities. There are no skill `.md` files on disk.

This matches Novamira's approach exactly:

- Skills are created and edited via **WPCodex → Skills** in the admin, or directly by the agent using `wpcodex/skill-create` / `wpcodex/skill-update` abilities
- Each skill has YAML frontmatter (`name`, `description`, `enable_agentic`, `enable_prompt`) and a plain Markdown body
- The `description` field is the trigger — the agent reads all descriptions at session start to decide which skills are relevant
- Full skill bodies are loaded on demand via `wpcodex/skill-read`
- Skills are site-wide: all connected AI clients share the same skill set

**Why database, not files?**
- Skills survive plugin updates without any gitignore or directory management
- The admin UI can list, edit, enable/disable, and delete skills without filesystem access
- The agent can create and update skills using MCP abilities without needing `wpcodex/write-file`
- No risk of skill files being accidentally committed to version control

**`includes/Skills/` contains only PHP engine code:**
- `Repository.php` — DB read/write for skill records
- `AdminPage.php` — WPCodex → Skills admin UI
- `Schema.php` — DB table creation and upgrade on activation

**MCP abilities for skills** (registered via `wp_register_ability()`):
- `wpcodex/skill-list` — list all skills with names and descriptions
- `wpcodex/skill-read` — read a skill body by name
- `wpcodex/skill-create` — create a new skill
- `wpcodex/skill-update` — update an existing skill
- `wpcodex/skill-delete` — delete a skill by name

---

## 3. Feature Roadmap

### v1.0 — Core MCP Server (Target: Q3 2026)

- [x] Plugin scaffold, constants
- [x] PSR-4 class structure under `includes/` via Jetpack Autoloader
- [x] `wordpress/mcp-adapter` integrated — HTTP + STDIO transport
- [x] WordPress Abilities API — all tools registered via `wp_register_ability()`
- [x] `wpcodex/php-execute` — temp file sandbox
- [x] `wpcodex/wpcli-run` — subprocess with env sanitisation
- [x] `wpcodex/db-query` — `$wpdb` with `prepare()`
- [x] `wpcodex/file-read` / `wpcodex/file-write` / `wpcodex/file-list`
- [x] `wpcodex/site-info` — install snapshot
- [x] `wpcodex/option-get` / `wpcodex/option-set`
- [x] `wpcodex/post-query` — `WP_Query` wrapper
- [x] `wpcodex/skill-list` / `wpcodex/skill-read` / `wpcodex/skill-create` / `wpcodex/skill-update` / `wpcodex/skill-delete`
- [x] Skills DB schema (`wpcodex_skills` table), `Repository`, `Schema`
- [x] WPCodex → Skills admin UI (`AdminPage`)
- [x] Connect page (Application Password setup guide)
- [x] Settings page (abilities toggle per category)
- [x] Admin bar status indicator
- [x] `CLAUDE.md` and `GEMINI.md` agent context files
- [x] PHPUnit test bootstrap + `AuthTest`
- [x] TypeScript + SCSS source in `src/`, compiled to `assets/`
- [x] Release CI: `composer install --no-dev` → ZIP with `vendor/`

### v1.1 — Stability & Developer UX (Target: Q4 2026)

- [ ] Background job queue for long-running WP-CLI commands
- [ ] One-click file restore from `.bak` backup (admin UI)
- [ ] Application Password creation on the Connect page (one-step setup)
- [ ] `wpcodex/hook-inspect` — list active filters/actions on a hook
- [ ] `wpcodex/plugin-info` — detailed metadata for any active plugin
- [ ] `WPCODEX_SAFE_MODE` constant for subprocess PHP isolation
- [ ] Execution log viewer in admin (last 100 actions)
- [ ] WPCS and PHPStan CI via GitHub Actions
- [ ] Skills: enable/disable toggle in admin without deleting
- [ ] Skills: import/export `.md` file with frontmatter

### v1.2 — Extended Capabilities (Target: Q1 2027)

- [ ] WordPress Multisite (network) support
- [ ] Read-only mode flag for production monitoring without write access
- [ ] `wpcodex/cron-inspect` — list scheduled cron events
- [ ] `wpcodex/transient-get` / `wpcodex/transient-set`
- [ ] Plugin activate/deactivate via MCP ability
- [ ] STDIO transport documentation and WP-CLI integration guide

### v2.0 — Pro Tier: Builder Specialisations (Target: Q2 2027)

Pro adds maintained skill bundles and specialised MCP abilities on top of the core plugin.

| Specialisation | Status |
|---|---|
| Elementor | Research |
| Bricks Builder | Research |
| ACF / Advanced Custom Fields | Research |
| WooCommerce | Research |
| Divi | Research |
| Kadence Blocks | Research |
| Meta Box | Research |
| Gravity Forms | Research |
| WPML / Polylang | Research |

### v2.1 — Agent Memory Layer

- [ ] Persistent memory between sessions: agent stores project decisions as named memory records
- [ ] Memory versioning (author, timestamp, source context)
- [ ] Memory export/import between installs
- [ ] `wpcodex/memory-set` / `wpcodex/memory-get` — persistent key-value store separate from skills

---

## 4. Open Research Questions

### RQ-001: MCP Streaming for Long-Running Commands

Some WP-CLI commands produce output over minutes. The `wordpress/mcp-adapter` supports HTTP streaming — need to verify that all target AI clients (Claude Code, Cursor, Windsurf, Cline, Gemini CLI) handle streaming responses correctly.

Fallback plan if clients don't support streaming: return a job ID from `wpcodex/wpcli-run`, expose `wpcodex/job-status` for polling. Scheduled for v1.1 background job queue work.

Status: Claude Code ✅, Cursor ?, Windsurf ?, Cline ?, Gemini CLI ?

### RQ-002: Safe Mode Sandbox

At what point does tighter PHP sandboxing (subprocess, separate FPM pool) justify the added complexity? Most dev/staging users prefer simplicity. Hypothesis: offer `WPCODEX_SAFE_MODE` constant — off by default, documented for teams that need it.

### RQ-003: STDIO Transport

The `wordpress/mcp-adapter` includes a STDIO transport for local development via WP-CLI. This allows AI clients to connect without HTTPS — useful for local environments where setting up SSL is impractical.

Open questions:
- Does STDIO transport work correctly with the Jetpack Autoloader on Windows/WAMP?
- What is the UX for connecting AI clients via STDIO vs HTTP?
- Should the WPCodex Connect page show both connection methods?

Document findings and add a `STDIO.md` guide targeting v1.2.

### RQ-004: CI/CD Usage

Some teams want WPCodex active in CI/CD pipelines (GitHub Actions running AI-generated integration tests). Different threat model: no human reviewing in real time, automated Application Password rotation needed, read-only mode critical. Draft a `CICD.md` guide targeting v1.1.

---

## 5. Technical Debt Log

| ID | Description | Priority | Target |
|---|---|---|---|
| TD-001 | Fatal errors in `wpcodex/php-execute` swallow output — need output buffer + error handler in `PhpRunner` | High | v1.0 |
| TD-002 | `proc_open` for WP-CLI doesn't forward `WP_HOME`/`WP_SITEURL` consistently on some hosts | Medium | v1.1 |
| TD-003 | Admin connect page copy button uses `document.execCommand` fallback — deprecated, migrate to Clipboard API fully | Low | v1.1 |
| TD-004 | No test coverage on `DbRunner` — SQL surface needs unit tests | High | v1.0 |
| TD-005 | `CliRunner::find_wp_binary()` calls `shell_exec('which wp')` — should use `escapeshellcmd` and check return code | Medium | v1.0 |
| TD-006 | Ability name convention needs audit — Novamira uses `novamira/execute-php` (kebab-case after slash); WPCodex should follow `wpcodex/php-execute` consistently throughout | Low | v1.0 |

---

## 6. Experiment Log

### EXP-001 — PHP Temp File Execution (2026-05-10)

**Hypothesis:** Executing PHP via temp file + `include()` works on shared hosting environments where `eval()` may be disabled.

**Result:** ✅ Confirmed. Tested on WP Engine, Kinsta, SiteGround, Flywheel, plain LAMP. `eval()` disabled on two of five hosts; temp file worked on all.

**Side finding:** `sys_get_temp_dir()` returns a non-writable path on some hosts. Fallback: use `WP_CONTENT_DIR . '/wpcodex-sandbox/'` instead.

### EXP-002 — WP-CLI Subprocess Timeout (2026-05-18)

**Hypothesis:** A 30-second `proc_open` timeout is sufficient for most WP-CLI commands.

**Result:** ❌ Rejected for bulk commands. `wp post generate --count=1000` ran for 4m12s; 30s timeout killed it silently.

**Decision:** Default 30s, configurable via `WPCODEX_CLI_TIMEOUT` constant. Bulk operations require the background job queue (v1.1). Document this clearly.

---

## 7. References

- [MCP Specification — modelcontextprotocol.io](https://spec.modelcontextprotocol.io/)
- [wordpress/mcp-adapter — GitHub](https://github.com/WordPress/mcp-adapter)
- [wordpress/mcp-adapter — Packagist](https://packagist.org/packages/wordpress/mcp-adapter)
- [WordPress Abilities API — Developer Blog](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/)
- [AI Building Blocks for WordPress](https://make.wordpress.org/ai/)
- [Jetpack Autoloader — GitHub](https://github.com/Automattic/jetpack-autoloader)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [WP-CLI Documentation](https://wp-cli.org/)
- [Novamira (reference implementation)](https://github.com/use-novamira/novamira)
- [Novamira Skills documentation](https://novamira.ai/docs/skills/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [PHPStan WordPress extension](https://github.com/szepeviktor/phpstan-wordpress)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
# AllyWorker — Research & Development Notes

> **Location:** `docs/RND.md`  
> **Status:** Active pre-release development  
> **Last updated:** June 2026

This document tracks open research questions, architectural decisions, experiment logs, and the forward roadmap for AllyWorker. It is a living document — update it as decisions are made or invalidated.

---

## 1. Vision & Goals

### What AllyWorker Is

AllyWorker is a WordPress MCP server plugin. It exposes low-level WordPress primitives (PHP execution, WP-CLI, `$wpdb`, filesystem) as MCP tools so any compatible AI agent can build and manage WordPress sites with full autonomy.

It is a clean-room implementation inspired by Novamira, built under GPL-3.0, with a PSR-4 class structure, a strict `includes/` source layout, and a first-class Skills system from day one.

### What It Is Not

- **Not a hosted proxy.** The AI connects directly to the WordPress server.
- **Not a code generator.** It executes real code on real sites.
- **Not production-safe by default.** Designed for dev/staging workflows with proper backups.

### Core Design Principles

1. **Primitives, not opinions.** AllyWorker exposes raw capabilities. The AI decides how to compose them.
2. **Direct connection.** No SaaS layer between the AI client and the WordPress install.
3. **Transparent execution.** Every action the AI takes is logged and reviewable.
4. **Recoverable by design.** PHP sandbox uses temp files. File writes are atomic with `.bak` backups.
5. **PSR-4 throughout.** All PHP under `includes/` with the `AllyWorker\` namespace root.
6. **Standards-first.** Use official WordPress infrastructure where it exists rather than reinventing it.

---

## 2. Architecture Decisions

### 2.1 Directory Layout vs. Novamira

Novamira uses a flat procedural `includes/` with `require_once` chains. AllyWorker uses the same `includes/` root but with PSR-4 autoloading and a class-per-file structure. This gives us static analysis, testability, and IDE navigation without diverging from the familiar `includes/` convention WordPress developers expect.

| Concern | Novamira | AllyWorker |
|---|---|---|
| PHP loading | `require_once` chains | PSR-4 via Jetpack Autoloader (`autoload_packages.php`) |
| Namespace root | None (procedural) | `AllyWorker\` → `includes/` |
| MCP transport | `wordpress/mcp-adapter` (bundled in `vendor/`) | `wordpress/mcp-adapter` (same — bundled in `vendor/`) |
| Tool registration | WordPress Abilities API (`wp_register_ability()`) | WordPress Abilities API (`wp_register_ability()`) — same |
| WP version minimum | 6.9 (Abilities API in core) | 6.9 (Abilities API in core) |
| Skills storage | WordPress database | WordPress database (same) |
| Skills admin | Novamira → Skills UI | AllyWorker → Skills UI |
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
AllyWorker ability files  ←  includes/Abilities/*.php
    │
    │  delegates to Runner classes
    ▼
includes/Runner/  ←  PhpRunner, CliRunner, DbRunner, FileManager
```

**What this means in practice:**

- `includes/MCP/Server.php`, `ToolRegistry.php`, and all `includes/MCP/Tool/*.php` wrapper classes are **removed**. The Adapter handles all JSON-RPC routing.
- Each tool becomes an ability file in `includes/Abilities/` that calls `wp_register_ability('allyworker/{name}', [...])` on `wp_abilities_api_init`.
- All Runner classes (`PhpRunner`, `CliRunner`, `DbRunner`, `FileManager`) are **unchanged** — they contain the real logic and are called from each ability's `execute_callback`.
- The entry point (`allyworker.php`) loads `vendor/autoload_packages.php` (Jetpack Autoloader) and initialises the Adapter via `\WP\MCP\Core\McpAdapter::instance()`.

**Jetpack Autoloader:**

When multiple plugins on the same site bundle `wordpress/mcp-adapter`, they must not load conflicting versions. The Jetpack Autoloader (`automattic/jetpack-autoloader`) solves this: it ensures only the newest version of any shared package is loaded across all active plugins. AllyWorker uses `autoload_packages.php` instead of `autoload.php`:

```php
// allyworker.php
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
```

**Release build:**

The `vendor/` directory is **not committed to the Git repository**. It is added to the release ZIP by the CI build pipeline (`composer install --no-dev` → ZIP). This matches Novamira's approach exactly and is the standard WordPress plugin release pattern.

**Why not build a custom transport:**
- The MCP specification evolves. Every spec update would require manual changes to `Server.php`.
- The Adapter already handles HTTP transport, STDIO transport, session management, error handling, observability, and MCP component validation. Building all of this correctly is months of work.
- Tools registered via `wp_register_ability()` are automatically available to any MCP client using the standard WordPress MCP Adapter — not just AllyWorker. AllyWorker becomes interoperable with the broader WordPress MCP ecosystem.

### 2.3 PHP Execution Sandbox

**Decision for v1.0:** Temp file + `include()` in `allyworker-sandbox/` inside `WP_CONTENT_DIR`.

Execution flow:
1. Write `<?php declare(strict_types=1); {code}` to a uniquely named temp file
2. Capture output via `ob_start()`
3. `include` the file inside a `try/catch(\Throwable)`
4. Always `unlink()` the temp file — even on exception
5. Return captured output + serialised return value

**Why not `eval()`?** Disabled on many shared hosting environments. Temp file approach works on all tested hosts (WP Engine, Kinsta, SiteGround, Flywheel, plain LAMP).

**Sandbox directory:** `WP_CONTENT_DIR . '/allyworker-sandbox/'` — created on activation, protected by `.htaccess Deny from all` and an `index.php` stub. Lives outside the plugin so it survives updates.

**Open question (v1.1):** A `ALLY_WORKER_SAFE_MODE` constant that switches to subprocess isolation (`proc_open` + separate PHP process with `disable_functions` restrictions) for teams that want tighter isolation on staging.

### 2.4 WP-CLI Integration

**Decision:** `proc_open` subprocess. WP-CLI binary resolved from `WP_CLI_PHP` constant, common paths (`/usr/local/bin/wp`, `/usr/bin/wp`), and `which wp`.

Key safety points:
- `ALLY_WORKER_SECRET` and `HTTP_AUTHORIZATION` stripped from subprocess environment
- Default timeout: 30 seconds (configurable via `ALLY_WORKER_CLI_TIMEOUT` constant)
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

**Deferred to v1.1:** Expose Application Password creation on the AllyWorker Connect page for one-step setup.

### 2.6 File Operations

Every write operation in `FileManager`:
1. Validates path is within `ABSPATH` (blocks traversal)
2. Creates a `.bak` copy of the existing file
3. Registers backup in a transient (`allyworker_bak_{md5(path)}`, 24h TTL) for admin restore UI
4. Writes content to a temp file (`{path}.tmp_{random}`)
5. `rename()` temp to target — atomic on POSIX systems

**Deferred to v1.1:** One-click restore UI in the admin using the backup transients.

### 2.7 Skills Storage — Database, Not Files

**Decision:** Skills are stored in the **WordPress database**, managed from the admin UI and via dedicated MCP abilities. There are no skill `.md` files on disk.

This matches Novamira's approach exactly:

- Skills are created and edited via **AllyWorker → Skills** in the admin, or directly by the agent using `allyworker/skill-create` / `allyworker/skill-update` abilities
- Each skill has YAML frontmatter (`name`, `description`, `enable_agentic`, `enable_prompt`) and a plain Markdown body
- The `description` field is the trigger — the agent reads all descriptions at session start to decide which skills are relevant
- Full skill bodies are loaded on demand via `allyworker/skill-read`
- Skills are site-wide: all connected AI clients share the same skill set

**Why database, not files?**
- Skills survive plugin updates without any gitignore or directory management
- The admin UI can list, edit, enable/disable, and delete skills without filesystem access
- The agent can create and update skills using MCP abilities without needing `allyworker/write-file`
- No risk of skill files being accidentally committed to version control

**`includes/Skills/` contains only PHP engine code:**
- `Repository.php` — DB read/write for skill records
- `AdminPage.php` — AllyWorker → Skills admin UI
- `Schema.php` — DB table creation and upgrade on activation

**MCP abilities for skills** (registered via `wp_register_ability()`):
- `allyworker/skill-list` — list all skills with names and descriptions
- `allyworker/skill-read` — read a skill body by name
- `allyworker/skill-create` — create a new skill
- `allyworker/skill-update` — update an existing skill
- `allyworker/skill-delete` — delete a skill by name

---

## 3. Feature Roadmap

### v1.0 — Core MCP Server (Target: Q3 2026)

- [x] Plugin scaffold, constants
- [x] PSR-4 class structure under `includes/` via Jetpack Autoloader
- [x] `wordpress/mcp-adapter` integrated — HTTP + STDIO transport
- [x] WordPress Abilities API — all tools registered via `wp_register_ability()`
- [x] `allyworker/php-execute` — temp file sandbox
- [x] `allyworker/wpcli-run` — subprocess with env sanitisation
- [x] `allyworker/db-query` — `$wpdb` with `prepare()`
- [x] `allyworker/file-read` / `allyworker/file-write` / `allyworker/file-list`
- [x] `allyworker/file-edit` — in-place file patching (old → new string replace)
- [x] `allyworker/file-delete` — file deletion with safety checks
- [x] `allyworker/file-disable` / `allyworker/file-enable` — toggle file activation (e.g. plugins)
- [x] `allyworker/create-upload-link` — generate a signed upload URL
- [x] `allyworker/site-info` — install snapshot
- [x] `allyworker/option-get` / `allyworker/option-set`
- [x] `allyworker/post-query` — `WP_Query` wrapper
- [x] `allyworker/create-admin-access-link` — generate a one-time admin login URL
- [x] `allyworker/skill-list` / `allyworker/skill-read` / `allyworker/skill-create` / `allyworker/skill-update` / `allyworker/skill-delete`
- [x] `allyworker/skill-list-revisions` / `allyworker/skill-restore-revision` — skill version history
- [x] Skills DB schema (`allyworker_skills` table), `Repository`, `Schema`
- [x] AllyWorker → Skills admin UI (`AdminPage`)
- [x] Connect page (Application Password setup guide)
- [x] Settings page (abilities toggle per category)
- [x] Admin bar status indicator
- [x] `CLAUDE.md` and `GEMINI.md` agent context files
- [x] PHPUnit test bootstrap + `AuthTest`
- [x] TypeScript + SCSS source in `src/`, compiled to `assets/`
- [x] Release CI: `composer install --no-dev` → ZIP with `vendor/`
- [x] Gutenberg block editor abilities — `allyworker/gutenberg-get-content`, `allyworker/gutenberg-write-content`, `allyworker/gutenberg-get-finalization-url`, `allyworker/gutenberg-create-padding`, `allyworker/gutenberg-add-padding-change`, `allyworker/gutenberg-enable-finalization`, `allyworker/gutenberg-delete-padding`, `allyworker/gutenberg-delete-padding-change`, `allyworker/gutenberg-get-padding`, `allyworker/gutenberg-list-padding`, `allyworker/gutenberg-get-finalizer-runtime`

### v1.1 — Stability & Developer UX (Target: Q4 2026)

- [ ] Background job queue for long-running WP-CLI commands
- [ ] One-click file restore from `.bak` backup (admin UI)
- [ ] Application Password creation on the Connect page (one-step setup)
- [ ] `allyworker/hook-inspect` — list active filters/actions on a hook
- [ ] `allyworker/plugin-info` — detailed metadata for any active plugin
- [ ] `ALLY_WORKER_SAFE_MODE` constant for subprocess PHP isolation
- [ ] Execution log viewer in admin (last 100 actions)
- [ ] WPCS and PHPStan CI via GitHub Actions
- [ ] Skills: enable/disable toggle in admin without deleting
- [ ] Skills: import/export `.md` file with frontmatter

### v1.2 — Extended Capabilities (Target: Q1 2027)

- [ ] WordPress Multisite (network) support
- [ ] Read-only mode flag for production monitoring without write access
- [ ] `allyworker/cron-inspect` — list scheduled cron events
- [ ] `allyworker/transient-get` / `allyworker/transient-set`
- [ ] Plugin activate/deactivate via MCP ability
- [ ] STDIO transport documentation and WP-CLI integration guide

### v2.0 — Pro Tier: Builder Specialisations (Target: Q2 2027)

No skill bundle has been built for any theme or plugin yet — all are at Research stage. The Tier column records the planned distribution: Free = bundled with the core plugin, Pro = requires AllyWorker Pro. The Progress column tracks active development for Free-tier items once work begins.

**Themes**

| Theme | Tier | Status | Progress |
|---|---|---|---|
| Astra | Free | Research | 0% |
| Kadence | Free | Research | 0% |
| Blocksy | Free | Research | 0% |

**Plugins**

| Plugin | Tier | Status | Progress |
|---|---|---|---|
| Contact Form 7 | Free | Research | 0% |
| Elementor | Pro | Research | — |
| Yoast SEO | Pro | Research | — |
| Rank Math SEO | Pro | Research | — |
| WooCommerce | Pro | Research | — |
| LiteSpeed Cache | Pro | Research | — |
| WPForms Lite | Pro | Research | — |
| WP Mail SMTP | Pro | Research | — |
| WPCode (Insert Headers and Footers) | Pro | Research | — |
| All in One SEO (AIOSEO) | Pro | Research | — |
| Ultimate Addons for Elementor | Pro | Research | — |
| Advanced Custom Fields (ACF) | Pro | Research | — |
| Essential Addons for Elementor | Pro | Research | — |
| ElementsKit Lite | Pro | Research | — |
| Code Snippets | Pro | Research | — |
| Spectra (Ultimate Addons for Gutenberg) | Pro | Research | — |
| OptinMonster | Pro | Research | — |
| GutenKit Blocks Addon | Pro | Research | — |
| Bricks Builder | Pro | Research | — |
| Divi | Pro | Research | — |
| Kadence Blocks | Pro | Research | — |
| Meta Box | Pro | Research | — |
| Gravity Forms | Pro | Research | — |
| WPML / Polylang | Pro | Research | — |

### v2.1 — Agent Memory Layer

- [ ] Persistent memory between sessions: agent stores project decisions as named memory records
- [ ] Memory versioning (author, timestamp, source context)
- [ ] Memory export/import between installs
- [ ] `allyworker/memory-set` / `allyworker/memory-get` — persistent key-value store separate from skills

---

## 4. Open Research Questions

### RQ-001: MCP Streaming for Long-Running Commands

Some WP-CLI commands produce output over minutes. The `wordpress/mcp-adapter` supports HTTP streaming — need to verify that all target AI clients (Claude Code, Cursor, Windsurf, Cline, Gemini CLI) handle streaming responses correctly.

Fallback plan if clients don't support streaming: return a job ID from `allyworker/wpcli-run`, expose `allyworker/job-status` for polling. Scheduled for v1.1 background job queue work.

Status: Claude Code ✅, Cursor ?, Windsurf ?, Cline ?, Gemini CLI ?

### RQ-002: Safe Mode Sandbox

At what point does tighter PHP sandboxing (subprocess, separate FPM pool) justify the added complexity? Most dev/staging users prefer simplicity. Hypothesis: offer `ALLY_WORKER_SAFE_MODE` constant — off by default, documented for teams that need it.

### RQ-003: STDIO Transport

The `wordpress/mcp-adapter` includes a STDIO transport for local development via WP-CLI. This allows AI clients to connect without HTTPS — useful for local environments where setting up SSL is impractical.

Open questions:
- Does STDIO transport work correctly with the Jetpack Autoloader on Windows/WAMP?
- What is the UX for connecting AI clients via STDIO vs HTTP?
- Should the AllyWorker Connect page show both connection methods?

Document findings and add a `STDIO.md` guide targeting v1.2.

### RQ-004: CI/CD Usage

Some teams want AllyWorker active in CI/CD pipelines (GitHub Actions running AI-generated integration tests). Different threat model: no human reviewing in real time, automated Application Password rotation needed, read-only mode critical. Draft a `CICD.md` guide targeting v1.1.

---

## 5. Technical Debt Log

| ID | Description | Priority | Target |
|---|---|---|---|
| TD-001 | Fatal errors in `allyworker/php-execute` swallow output — need output buffer + error handler in `PhpRunner` | High | v1.0 |
| TD-002 | `proc_open` for WP-CLI doesn't forward `WP_HOME`/`WP_SITEURL` consistently on some hosts | Medium | v1.1 |
| TD-003 | Admin connect page copy button uses `document.execCommand` fallback — deprecated, migrate to Clipboard API fully | Low | v1.1 |
| TD-004 | No test coverage on `DbRunner` — SQL surface needs unit tests | High | v1.0 |
| TD-005 | `CliRunner::find_wp_binary()` calls `shell_exec('which wp')` — should use `escapeshellcmd` and check return code | Medium | v1.0 |
| TD-006 | Ability name convention needs audit — Novamira uses `novamira/execute-php` (kebab-case after slash); AllyWorker should follow `allyworker/php-execute` consistently throughout | Low | v1.0 |

---

## 6. Experiment Log

### EXP-001 — PHP Temp File Execution (2026-05-10)

**Hypothesis:** Executing PHP via temp file + `include()` works on shared hosting environments where `eval()` may be disabled.

**Result:** ✅ Confirmed. Tested on WP Engine, Kinsta, SiteGround, Flywheel, plain LAMP. `eval()` disabled on two of five hosts; temp file worked on all.

**Side finding:** `sys_get_temp_dir()` returns a non-writable path on some hosts. Fallback: use `WP_CONTENT_DIR . '/allyworker-sandbox/'` instead.

### EXP-002 — WP-CLI Subprocess Timeout (2026-05-18)

**Hypothesis:** A 30-second `proc_open` timeout is sufficient for most WP-CLI commands.

**Result:** ❌ Rejected for bulk commands. `wp post generate --count=1000` ran for 4m12s; 30s timeout killed it silently.

**Decision:** Default 30s, configurable via `ALLY_WORKER_CLI_TIMEOUT` constant. Bulk operations require the background job queue (v1.1). Document this clearly.

---

## 7. Supported Themes

AllyWorker is tested against and explicitly supports the following three WordPress themes. Skills, ability integrations, and any theme-specific PHP execution patterns should be verified against all three.

### 7.1 Astra

| Property | Value |
|---|---|
| Slug | `astra` |
| Author | Brainstorm Force |
| Current version | 4.12.6 (March 24, 2026) |
| Active installations | 1,000,000+ |
| Rating | 4.9 / 5 (6,184 five-star reviews) |
| License | GPL (free + paid upgrades via Astra Pro) |
| WP minimum | 5.3 |
| PHP minimum | 5.3 |
| Homepage | https://wpastra.com/ |
| WordPress.org | https://wordpress.org/themes/astra/ |

Astra is the most-installed third-party theme in the WordPress ecosystem. It is lightweight, highly customizable via the Customizer, and ships deep integration with Spectra (its own block builder), Elementor, and Beaver Builder. Its wide PHP version floor (5.3) means it targets shared hosting environments where PHP upgrades are slow. AllyWorker agents building or modifying Astra sites should be aware of Astra's Global Colors and Typography system, its header/footer builder hooks (`astra_header`, `astra_footer`), and the `astra_get_option()` helper for reading theme settings.

Key hooks and APIs:
- `astra_get_option( $option, $default )` — reads Astra Customizer settings
- `astra_header_top` / `astra_header_bottom` — header injection points
- `astra_footer_top` / `astra_footer_bottom` — footer injection points
- Child theme starter: `wp scaffold child-theme my-child --parent-theme=astra`

### 7.2 Kadence

| Property | Value |
|---|---|
| Slug | `kadence` |
| Author | StellarWP |
| Current version | 1.4.5 (February 25, 2026) |
| Active installations | 500,000+ |
| Rating | 4.9 / 5 (425 five-star reviews) |
| License | GPL (free + paid upgrades via Kadence Theme Pro) |
| WP minimum | 6.3 |
| PHP minimum | 7.4 |
| Homepage | https://www.kadencewp.com/kadence-theme/ |
| WordPress.org | https://wordpress.org/themes/kadence/ |

Kadence is a full-site editing-ready theme built by StellarWP (the same company behind GiveWP, LearnDash, and other major plugins). Its headline feature is a drag-and-drop header and footer builder backed by a JSON configuration stored in the Customizer. It ships with a library of starter templates importable via the Kadence Starter Templates plugin. The PHP minimum of 7.4 and WP minimum of 6.3 mean it targets a more current hosting baseline than Astra. AllyWorker agents modifying Kadence sites should use `kadence_get_option()` for reading theme settings and be aware that header/footer layout is serialised as JSON in the `kadence_global_palette` and `kadence_header` Customizer options.

Key hooks and APIs:
- `kadence_get_option( $option, $default )` — reads Kadence Customizer settings
- `kadence_before_header` / `kadence_after_header` — header injection points
- `kadence_before_footer` / `kadence_after_footer` — footer injection points
- Global palette stored in `kadence_global_palette` option (JSON)
- Child theme starter: `wp scaffold child-theme my-child --parent-theme=kadence`

### 7.3 Blocksy

| Property | Value |
|---|---|
| Slug | `blocksy` |
| Author | Creative Themes |
| Current version | 2.1.44 (May 29, 2026) |
| Active installations | 300,000+ |
| Rating | 5.0 / 5 (857 five-star reviews) |
| License | GPL (free + paid upgrades via Blocksy Companion Pro) |
| WP minimum | 6.5 |
| PHP minimum | 7.0 |
| Homepage | https://creativethemes.com/blocksy/ |
| WordPress.org | https://wordpress.org/themes/blocksy/ |

Blocksy is the most block-editor-native of the three supported themes. It ships with block editor patterns, block styles, and grid layout support as tagged features, and is kept in active sync with Gutenberg releases (most recently updated May 29, 2026). Its advanced WooCommerce support — product gallery, sticky add-to-cart, wishlist, compare, quick view — is bundled in the free Blocksy Companion plugin. The WP 6.5 minimum makes it the most current-baseline theme of the three. AllyWorker agents working on Blocksy sites should use `blocksy_get_theme_mod()` for settings and be aware that Blocksy stores most of its configuration in JSON-encoded theme mods rather than individual options.

Key hooks and APIs:
- `blocksy_get_theme_mod( $key, $default )` — reads Blocksy theme mod settings
- `blocksy_output_all_hooks()` — reference for available action hooks
- `blocksy:header:before` / `blocksy:header:after` — header injection points
- `blocksy:footer:before` / `blocksy:footer:after` — footer injection points
- Configuration stored as JSON in individual `theme_mods` entries
- Child theme starter: `wp scaffold child-theme my-child --parent-theme=blocksy`

---

## 8. Supported Plugins

AllyWorker is tested against and explicitly supports the following eighteen WordPress plugins. Skills, ability integrations, and any plugin-specific PHP execution patterns should be verified against the relevant plugin where applicable.

### 8.1 Elementor

| Property | Value |
|---|---|
| Slug | `elementor` |
| Author | Elementor |
| Current version | 4.0.8 |
| Active installations | 10,000,000+ |
| Rating | 4.6 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via Elementor Pro) |
| WP minimum | 6.6 |
| PHP minimum | 7.4 |
| Homepage | https://elementor.com/ |
| WordPress.org | https://wordpress.org/plugins/elementor/ |

Elementor is the most widely installed page builder in the WordPress ecosystem, powering over 10 million sites. It operates as a front-end drag-and-drop editor that stores page layouts as post meta (`_elementor_data`) in JSON format rather than classic post content. AllyWorker agents modifying Elementor sites should read and write this meta directly or use `\Elementor\Plugin::$instance->db->get_plain_text( $post_id )` to extract readable content. Elementor Pro adds Theme Builder (header, footer, single, archive templates), WooCommerce builder, and a Form widget with its own submission hooks.

Key hooks and APIs:
- `elementor/init` — fires after Elementor is fully loaded; safe point to interact with its API
- `elementor/element/before_section_start` / `elementor/element/after_section_end` — inject controls into existing widgets
- `elementor/widget/render_content` filter — modify widget HTML output
- `\Elementor\Plugin::$instance->db->get_plain_text( $post_id )` — get plain-text content from an Elementor post
- `\Elementor\Plugin::$instance->files_manager->clear_cache()` — purge Elementor CSS/JS file cache
- `_elementor_data` post meta — raw JSON layout; `_elementor_template_type` — template type string

### 8.2 Yoast SEO

| Property | Value |
|---|---|
| Slug | `wordpress-seo` |
| Author | Yoast |
| Current version | 27.4 |
| Active installations | 10,000,000+ |
| Rating | 4.8 / 5 |
| License | GPL-3.0-or-later (free + paid upgrades via Yoast SEO Premium) |
| WP minimum | 6.8 |
| PHP minimum | 7.4 |
| Homepage | https://yoast.com/wordpress/plugins/seo/ |
| WordPress.org | https://wordpress.org/plugins/wordpress-seo/ |

Yoast SEO is the original and most-installed SEO plugin for WordPress with a decade-long track record. It manages title tags, meta descriptions, Open Graph / Twitter Card markup, XML sitemaps, breadcrumbs, and structured data (JSON-LD). Per-post SEO settings are stored as post meta with the `_yoast_wpseo_` prefix. AllyWorker agents updating SEO data should write these meta keys directly rather than using the UI. The `YoastSEO()->meta->for_post( $post_id )` surface provides read access to all computed SEO values for a given post.

Key hooks and APIs:
- `wpseo_title` filter — modify the computed SEO title before output
- `wpseo_metadesc` filter — modify the meta description before output
- `wpseo_canonical` filter — override the canonical URL
- `YoastSEO()->meta->for_post( $post_id )` — retrieve all SEO metadata for a post
- `WPSEO_Options::get( $key )` — read a Yoast SEO global option
- `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw` — post meta keys for per-post SEO fields

### 8.3 Rank Math SEO

| Property | Value |
|---|---|
| Slug | `seo-by-rank-math` |
| Author | Rank Math SEO |
| Current version | 1.0.271.1 |
| Active installations | 4,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via Rank Math Pro) |
| WP minimum | 6.3 |
| PHP minimum | 7.4 |
| Homepage | https://rankmath.com/ |
| WordPress.org | https://wordpress.org/plugins/seo-by-rank-math/ |

Rank Math is a feature-rich SEO plugin that bundles schema markup, a redirection manager, a 404 monitor, local SEO, and an analytics integration into the free tier — capabilities that Yoast and AIOSEO gate behind paid plans. Post-level settings are stored with the `rank_math_` meta key prefix. AllyWorker agents managing SEO on Rank Math sites can read and write these meta keys directly. The `RankMath\Post::get_meta( $key, $post_id )` helper provides a clean interface. Rank Math's REST API (`/rankmath/v1/`) can also be called via `allyworker/php-execute` for bulk operations.

Key hooks and APIs:
- `rank_math/frontend/title` filter — modify the SEO title
- `rank_math_description` filter — modify the meta description
- `rank_math/head` action — inject markup into the SEO head section
- `RankMath\Post::get_meta( 'rank_math_focus_keyword', $post_id )` — read a per-post SEO meta value
- `rank_math_focus_keyword`, `rank_math_description`, `rank_math_title` — post meta keys
- `rank_math/schema/filter_data` filter — modify structured data output

### 8.4 Contact Form 7

| Property | Value |
|---|---|
| Slug | `contact-form-7` |
| Author | Rock Lobster Inc. |
| Current version | 6.1.6 |
| Active installations | 10,000,000+ |
| Rating | 4.4 / 5 |
| License | GPL-2.0-or-later |
| WP minimum | 6.7 |
| PHP minimum | 7.4 |
| Homepage | https://contactform7.com/ |
| WordPress.org | https://wordpress.org/plugins/contact-form-7/ |

Contact Form 7 is the most-installed WordPress form plugin by raw active installation count. It stores forms as custom post type `wpcf7_contact_form` with mail settings in post meta. The plugin's design is intentionally minimal — no storage of submissions by default (use Flamingo or Cf7 Database plugin for that). AllyWorker agents can retrieve form configuration via `WPCF7_ContactForm::get_instance( $id )` and programmatically insert or modify forms via the post type. The `wpcf7_before_send_mail` hook is the primary integration point for pre-send logic.

Key hooks and APIs:
- `wpcf7_before_send_mail` action — fires before the contact email is sent; receive `$contact_form` object
- `wpcf7_posted_data` filter — modify or inspect submitted form field values
- `wpcf7_mail_sent` action — fires after successful email delivery
- `wpcf7_spam` filter — add custom spam detection logic; return `true` to flag as spam
- `WPCF7_ContactForm::get_instance( $id )` — load a form object by post ID
- Form stored as CPT `wpcf7_contact_form`; mail template in `_mail` post meta (serialised)

### 8.5 WooCommerce

| Property | Value |
|---|---|
| Slug | `woocommerce` |
| Author | Automattic |
| Current version | 10.7.0 |
| Active installations | 7,000,000+ |
| Rating | 4.5 / 5 |
| License | GPL-2.0-or-later (free + paid extensions via WooCommerce.com) |
| WP minimum | 6.8 |
| PHP minimum | 7.4 |
| Homepage | https://woocommerce.com/ |
| WordPress.org | https://wordpress.org/plugins/woocommerce/ |

WooCommerce is the dominant WordPress eCommerce platform. It registers custom post types (`product`, `shop_order`, `shop_coupon`), custom taxonomies (`product_cat`, `product_tag`), and a comprehensive REST API (`/wc/v3/`). Product data is a hybrid of post meta and custom tables (HPOS in WooCommerce 7+). AllyWorker agents working on WooCommerce stores should use `wc_get_product( $id )` and `wc_get_order( $id )` getters rather than raw post meta reads to ensure HPOS compatibility. The WooCommerce REST API is accessible directly via `allyworker/php-execute` using `WC()->api->get_endpoint_data()` or standard WP REST calls.

Key hooks and APIs:
- `woocommerce_before_add_to_cart_button` / `woocommerce_after_add_to_cart_button` — inject content around add-to-cart button
- `woocommerce_checkout_fields` filter — add, remove, or reorder checkout fields
- `woocommerce_order_status_changed` action — fires on any order status transition; receives `$order_id, $old_status, $new_status`
- `wc_get_product( $id )` — load a `WC_Product` object
- `wc_get_order( $id )` — load a `WC_Order` object
- `WC()->cart->get_total()` — get the current cart total
- `woocommerce_product_data_tabs` filter — add custom tabs to the product edit screen

### 8.6 LiteSpeed Cache

| Property | Value |
|---|---|
| Slug | `litespeed-cache` |
| Author | LiteSpeed Technologies |
| Current version | 7.8.1 |
| Active installations | 7,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-3.0-or-later |
| WP minimum | 5.3 |
| PHP minimum | 7.2 |
| Homepage | https://www.litespeedtech.com/products/cache-plugins/wordpress-acceleration |
| WordPress.org | https://wordpress.org/plugins/litespeed-cache/ |

LiteSpeed Cache is the highest-rated full-page caching plugin for WordPress, offering server-level integration with LiteSpeed and OpenLiteSpeed web servers alongside standalone optimisations (image compression, CSS/JS minification, lazy loading, CDN push) that work on any server. AllyWorker agents performing content updates should programmatically purge the cache after writes to prevent stale content from being served. Use the `litespeed_purge_all` action for broad purges; use tag-based purging (`litespeed_tag_add`) for precise per-URL invalidation during bulk operations.

Key hooks and APIs:
- `do_action( 'litespeed_purge_all' )` — purge the entire LiteSpeed cache
- `do_action( 'litespeed_purge', $url )` — purge a specific URL from cache
- `do_action( 'litespeed_tag_add', 'post_' . $post_id )` — associate current output with a purge tag
- `do_action( 'litespeed_purge_url', $url )` — alias for URL-specific purge
- `LiteSpeed\API::instance()` — access the LiteSpeed API singleton
- Settings stored under option key `litespeed.conf` (serialised array)

### 8.7 WPForms Lite

| Property | Value |
|---|---|
| Slug | `wpforms-lite` |
| Author | Syed Balkhi (WPForms) |
| Current version | 1.9.9.4 |
| Active installations | 6,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via WPForms Pro) |
| WP minimum | 5.5 |
| PHP minimum | 7.2 |
| Homepage | https://wpforms.com/ |
| WordPress.org | https://wordpress.org/plugins/wpforms-lite/ |

WPForms is a drag-and-drop form builder positioned as the beginner-friendly alternative to Contact Form 7. Forms are stored as the `wpforms` custom post type; the full form configuration (fields, settings, notifications, confirmations) is stored as JSON in the `post_content` field. AllyWorker agents can retrieve a form with `wpforms()->form->get( $form_id )` or read its JSON directly. Submissions are stored in a custom `wpforms_entries` database table (Pro). The `wpforms_process_complete` hook fires after a successful submission and is the primary integration point for post-submission automation.

Key hooks and APIs:
- `wpforms_process_complete` action — fires after a form is successfully submitted; receives `$fields, $entry, $form_data, $entry_id`
- `wpforms_field_label` filter — customise a field's label during rendering
- `wpforms_pre_submit_check` action — run custom validation before submission processing
- `wpforms()->form->get( $form_id )` — retrieve a WPForms form object by post ID
- `wpforms_get_form_data( $id )` — get the decoded form configuration array
- Form configuration stored as JSON in `post_content` of CPT `wpforms`

### 8.8 WP Mail SMTP

| Property | Value |
|---|---|
| Slug | `wp-mail-smtp` |
| Author | Syed Balkhi (WPForms) |
| Current version | 4.7.1 |
| Active installations | 4,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via WP Mail SMTP Pro) |
| WP minimum | 5.5 |
| PHP minimum | 7.4 |
| Homepage | https://wpmailsmtp.com/ |
| WordPress.org | https://wordpress.org/plugins/wp-mail-smtp/ |

WP Mail SMTP reconfigures WordPress's `wp_mail()` function to route emails through a dedicated SMTP provider (Gmail, SendGrid, Mailgun, Amazon SES, etc.) rather than the unreliable default PHP mail. Configuration is stored in a single serialised option (`wp_mail_smtp`). AllyWorker agents configuring or auditing email delivery should read this option to check the active mailer and credentials. The plugin provides a `\WPMailSMTP\Options` class for safe option access. Logging (Pro) stores email records in the `wp_wpforms_emails_log` table.

Key hooks and APIs:
- `wp_mail` filter — standard WordPress filter; SMTP override hooks in below this via PHPMailer
- `wp_mail_smtp_process_log` filter — modify an email log entry before it is saved (Pro)
- `wp_mail_smtp_mail_message_after_from` action — fires after the From header is set; useful for debugging
- `\WPMailSMTP\Options::init()->get( 'mail', 'from_email' )` — read the configured From email
- `\WPMailSMTP\Options::init()->get( 'mail', 'mailer' )` — read the active mailer slug
- Global option key: `wp_mail_smtp` (serialised array containing mailer, from, and provider credentials)

### 8.9 WPCode (Insert Headers and Footers)

| Property | Value |
|---|---|
| Slug | `insert-headers-and-footers` |
| Author | Syed Balkhi (WPCode) |
| Current version | 2.3.6 |
| Active installations | 3,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via WPCode Pro) |
| WP minimum | 5.0 |
| PHP minimum | 7.0 |
| Homepage | https://wpcode.com/ |
| WordPress.org | https://wordpress.org/plugins/insert-headers-and-footers/ |

WPCode (formerly Insert Headers and Footers) is a code snippet manager that lets site owners inject PHP, HTML, CSS, or JavaScript snippets into specific locations (header, footer, before/after content) without editing theme files. Snippets are stored as the `wpcode_snippet` custom post type with their code in post meta. AllyWorker agents can read all active snippets via the CPT, create new snippets programmatically, or use the `wpcode_get_snippet( $id )` API. This plugin is a direct functional peer to Code Snippets — agent skills should detect which is active before injecting code.

Key hooks and APIs:
- `wpcode_snippet_run` action — fires when a PHP snippet is executed; receives the snippet object
- `wpcode_before_header_scripts` / `wpcode_after_footer_scripts` action — output buffer hooks for header/footer injection
- `wpcode_get_snippet( $id )` — retrieve a snippet object by post ID
- `wpcode_get_snippet_by_title( $title )` — retrieve a snippet by title string
- Snippets stored as CPT `wpcode_snippet`; code stored in `_wpcode_snippet_code` post meta
- `_wpcode_snippet_location` meta — injection location slug (e.g. `header`, `footer`, `before_post`)

### 8.10 All in One SEO (AIOSEO)

| Property | Value |
|---|---|
| Slug | `all-in-one-seo-pack` |
| Author | Syed Balkhi (AIOSEO) |
| Current version | 4.9.7.2 |
| Active installations | 3,000,000+ |
| Rating | 4.5 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via AIOSEO Pro) |
| WP minimum | 5.7 |
| PHP minimum | 7.2 |
| Homepage | https://aioseo.com/ |
| WordPress.org | https://wordpress.org/plugins/all-in-one-seo-pack/ |

All in One SEO (AIOSEO) is one of the three major SEO plugins alongside Yoast and Rank Math. Unlike Yoast, AIOSEO stores per-post data in a dedicated database table (`aioseo_posts`) rather than in post meta, which means direct meta reads are insufficient — use the `AIOSEO\Plugin\Common\Models\Post::getPost( $post_id )` model. Global settings are split across multiple `aioseo_*` options. AllyWorker agents auditing or updating SEO data on AIOSEO sites must query the `aioseo_posts` table or use the model API rather than `get_post_meta()`.

Key hooks and APIs:
- `aioseo_title` filter — modify the SEO title before output
- `aioseo_description` filter — modify the meta description before output
- `aioseo/schema/output` filter — modify structured data output
- `aioseo()->meta->getTitle( $post )` — get the computed SEO title for a post
- `AIOSEO\Plugin\Common\Models\Post::getPost( $post_id )` — load per-post SEO data from `aioseo_posts` table
- Global settings in `aioseo_options`, `aioseo_options_internal`, and `aioseo_notifications` options

### 8.11 Ultimate Addons for Elementor

| Property | Value |
|---|---|
| Slug | `header-footer-elementor` |
| Author | Brainstorm Force |
| Current version | 2.8.5 |
| Active installations | 2,000,000+ |
| Rating | 4.6 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via Ultimate Addons for Elementor Pro) |
| WP minimum | 5.0 |
| PHP minimum | 7.4 |
| Homepage | https://ultimateelementor.com/ |
| WordPress.org | https://wordpress.org/plugins/header-footer-elementor/ |

Ultimate Addons for Elementor (UAE) by Brainstorm Force extends Elementor with additional widgets (Info Box, Price Table, Timeline, Business Hours, etc.) and a Theme Builder for header/footer templates without requiring Elementor Pro. Note: the WordPress.org slug `header-footer-elementor` is the historical slug; the plugin has since been renamed to "Ultimate Addons for Elementor". UAE's Theme Builder stores templates as the `elementor_library` CPT with a `hfe-type` taxonomy for location assignment. AllyWorker agents should be aware that Brainstorm Force also makes Spectra (§8.16) and Astra (§7.1) — all three often coexist on the same site.

Key hooks and APIs:
- `uael_widgets_list` filter — add or remove widgets from the UAE widget registry
- `uael_before_registration` action — fires before UAE registers its widgets with Elementor
- `hfe_header_enabled` / `hfe_footer_enabled` — conditional functions checking if a custom header/footer is active
- `\UAEL\Modules\{Module}\Widgets\{Widget}` — namespace pattern for widget class access
- Theme Builder templates stored as CPT `elementor_library` with term `hfe-type` for location
- `uael_header_footer_template_type` — custom taxonomy slug for template type assignment

### 8.12 Advanced Custom Fields (ACF)

| Property | Value |
|---|---|
| Slug | `advanced-custom-fields` |
| Author | WP Engine |
| Current version | 6.8.3 |
| Active installations | 2,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via ACF Pro) |
| WP minimum | 6.2 |
| PHP minimum | 7.4 |
| Homepage | https://www.advancedcustomfields.com/ |
| WordPress.org | https://wordpress.org/plugins/advanced-custom-fields/ |

Advanced Custom Fields (ACF) is the standard WordPress developer tool for adding structured custom data to any post type, taxonomy, user, or option page. Field groups and field definitions are stored in the database as the `acf-field-group` and `acf-field` CPTs (or as PHP code using `acf_add_local_field_group()`). Values are stored in standard post meta. ACF Pro adds Repeater, Flexible Content, Gallery, Clone, and Options Pages fields. AllyWorker agents that need to read or write custom field data should always use the ACF API (`get_field`, `update_field`) rather than raw `get_post_meta` to ensure sub-field and serialisation handling is correct.

Key hooks and APIs:
- `get_field( $name, $post_id )` — read a field value; handles sub-fields, image objects, relationships
- `update_field( $name, $value, $post_id )` — write a field value via the ACF API
- `add_row( $name, $row, $post_id )` — append a row to a Repeater field
- `acf/save_post` action — fires after ACF has saved all field values for a post
- `acf/load_field` filter — dynamically modify a field's settings at render time
- `acf/validate_value` filter — add custom validation to any field
- `acf_register_block_type( $args )` — register an ACF-powered Gutenberg block
- `acf_add_local_field_group( $args )` — register a field group via PHP (no DB entry needed)

### 8.13 Essential Addons for Elementor

| Property | Value |
|---|---|
| Slug | `essential-addons-for-elementor-lite` |
| Author | WPDeveloper |
| Current version | 6.6.7 |
| Active installations | 2,000,000+ |
| Rating | 4.8 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via Essential Addons Pro) |
| WP minimum | 5.0 |
| PHP minimum | 7.0 |
| Homepage | https://essential-addons.com/ |
| WordPress.org | https://wordpress.org/plugins/essential-addons-for-elementor-lite/ |

Essential Addons for Elementor is one of the two largest Elementor addon packs (alongside ElementsKit), offering 110+ free widgets. It is built by WPDeveloper, the same team behind BetterDocs and NotificationX. The free tier covers Post Grid, Data Table, Price Table, Countdown, Login/Register modal, and WooCommerce Product Grid. Pro adds advanced widgets like Dynamic Gallery, Filterable Gallery, and Advanced Data Table. Widget registration follows the standard `\Elementor\Widget_Base` extension pattern. AllyWorker agents should confirm which Elementor addon pack is active (Essential Addons, ElementsKit, or UAE) before attempting widget-specific operations.

Key hooks and APIs:
- `eael/controls/register` action — fires when Essential Addons registers its Elementor controls
- `eael_widgets` filter — add or remove widgets from Essential Addons' widget list
- `eael/template/before_content` action — fires before an EA template renders
- Widget classes in `\Essential_Addons_Elementor\Elements\{WidgetName}` namespace
- Extension classes in `\Essential_Addons_Elementor\Extensions\{ExtensionName}` namespace
- Plugin instance: `\Essential_Addons_Elementor\Plugin::instance()`

### 8.14 ElementsKit Lite

| Property | Value |
|---|---|
| Slug | `elementskit-lite` |
| Author | Wpmet |
| Current version | 3.8.2 |
| Active installations | 2,000,000+ |
| Rating | 4.5 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via ElementsKit Pro) |
| WP minimum | 5.0 |
| PHP minimum | 7.4 |
| Homepage | https://wpmet.com/plugin/elementskit/ |
| WordPress.org | https://wordpress.org/plugins/elementskit-lite/ |

ElementsKit Lite by Wpmet is a comprehensive Elementor widget and template library addon. Its distinguishing features are a full Mega Menu builder, a header/footer builder, and a large library of pre-built section templates. The free tier includes 85+ widgets; ElementsKit Pro adds sticky columns, animated gradients, parallax, and advanced widget controls. ElementsKit is made by Wpmet — the same team behind MetForm and ShopEngine. AllyWorker agents can access the ElementsKit module system via `\ElementsKit_Lite\Libs\...` and should check for both the `elementskit-lite` and `elementskit` (Pro) slugs when detecting this plugin.

Key hooks and APIs:
- `elementskit/widgets/init` action — fires when ElementsKit initialises its widget registry
- `elementskit_widget_{widget_name}_render_callback` filter — override a widget's render callback
- `elementskit_module_settings` filter — modify module-level settings
- `\ElementsKit_Lite\Libs\Framework\Classes\Plugin_Check::instance()` — plugin dependency checker
- Widget classes in `\ElementsKit_Lite\Modules\{Module}\Widgets\{Widget}` namespace
- Settings stored in `elementskit_lite_settings` option (serialised array)

### 8.15 Code Snippets

| Property | Value |
|---|---|
| Slug | `code-snippets` |
| Author | Code Snippets Pro |
| Current version | 3.9.5 |
| Active installations | 1,000,000+ |
| Rating | 4.9 / 5 |
| License | GPL-3.0-or-later (free + paid upgrades via Code Snippets Pro) |
| WP minimum | 5.0 |
| PHP minimum | 7.4 |
| Homepage | https://codesnippets.pro/ |
| WordPress.org | https://wordpress.org/plugins/code-snippets/ |

Code Snippets is a developer-friendly snippet manager that stores PHP, HTML, CSS, and JavaScript snippets in a dedicated database table (`wp_snippets`) rather than as a CPT. It has a cleaner data model than WPCode and is preferred by developers. Active PHP snippets are executed via `include` from a cache directory. AllyWorker agents can query the snippets table directly, or use the `get_snippets()` function to list all snippets. This plugin is a peer to WPCode (§8.9) — agent skills should detect which is active using `is_plugin_active('code-snippets/code-snippets.php')`.

Key hooks and APIs:
- `code_snippets/execute_snippet` action — fires when a snippet is executed; receives the snippet object
- `code_snippets_run_php_snippet` filter — intercept before a PHP snippet runs
- `get_snippets( $args )` — retrieve snippets matching given criteria (e.g. `['active' => true]`)
- `save_snippet( $snippet )` — create or update a snippet object
- `\Code_Snippets\Settings\Settings::get_setting( $key )` — read a plugin setting
- Snippets stored in `{prefix}snippets` table with columns: `id`, `name`, `description`, `code`, `scope`, `active`, `modified`

### 8.16 Spectra (Ultimate Addons for Gutenberg)

| Property | Value |
|---|---|
| Slug | `ultimate-addons-for-gutenberg` |
| Author | Brainstorm Force |
| Current version | 2.19.21 |
| Active installations | 1,000,000+ |
| Rating | 4.7 / 5 |
| License | GPL-2.0-or-later |
| WP minimum | 5.6 |
| PHP minimum | 7.4 |
| Homepage | https://wpspectra.com/ |
| WordPress.org | https://wordpress.org/plugins/ultimate-addons-for-gutenberg/ |

Spectra (formerly Ultimate Addons for Gutenberg) is Brainstorm Force's block builder for the Gutenberg editor. It provides 40+ blocks (Advanced Heading, Info Box, Price Table, Star Rating, Progress Bar, etc.) and is designed as the block-editor equivalent of UAE (§8.11). Spectra is always present when Astra (§7.1) is the active theme, as Brainstorm Force bundles Spectra prominently in their ecosystem. Blocks are registered via `register_block_type()` using the `uagb/` namespace prefix. AllyWorker agents can enumerate active Spectra blocks via `WP_Block_Type_Registry::get_instance()->get_all_registered()` filtering for `uagb/` keys.

Key hooks and APIs:
- `uagb_register_blocks` filter — add or remove blocks from Spectra's block registry
- `uagb_allowed_blocks` filter — control which Spectra blocks are available on a given screen
- `uagb_block_attributes` filter — modify default attributes for any Spectra block
- `\UAGB\Block_Helper` — utility class for shared block rendering helpers
- `\UAGB\Admin\Admin_Helper::get_admin_settings_option()` — read a Spectra admin setting
- Blocks registered under `uagb/` namespace; block settings in `uagb_admin_settings` option

### 8.17 OptinMonster

| Property | Value |
|---|---|
| Slug | `optinmonster` |
| Author | Syed Balkhi (OptinMonster) |
| Current version | 2.16.22 |
| Active installations | 1,000,000+ |
| Rating | 4.6 / 5 |
| License | GPL-2.0-or-later (free connector; paid SaaS subscription required) |
| WP minimum | 5.0 |
| PHP minimum | 7.2 |
| Homepage | https://optinmonster.com/ |
| WordPress.org | https://wordpress.org/plugins/optinmonster/ |

OptinMonster is a SaaS lead-generation tool; the WordPress plugin is a thin connector that loads campaigns (popups, slide-ins, floating bars) from the OptinMonster cloud API via JavaScript embed. Campaign configuration lives entirely on OptinMonster's servers — not in the WordPress database. The plugin stores the site's API key and connected campaigns in WordPress options. AllyWorker agents cannot modify campaign content directly but can read which campaigns are connected, toggle campaigns on/off by post, and manage the API connection. The `optinmonster` PHP class is the primary entry point.

Key hooks and APIs:
- `optin_monster_campaign` action — fires when a campaign embed is output
- `optin_monster_is_single` filter — control whether a campaign renders on singular posts
- `optin_monster_show_campaign` filter — globally suppress or force a campaign; return `false` to hide
- `\OMAPI_Base::get_instance()` — access the OptinMonster singleton
- `\OMAPI_Api::build_url( $route, $params )` — construct an OptinMonster API endpoint URL
- API credentials stored in `optinmonster` option; per-post display rules in post meta `_om_dont_show`

### 8.18 GutenKit Blocks Addon

| Property | Value |
|---|---|
| Slug | `gutenkit-blocks-addon` |
| Author | Ataur R (WPDeveloper) |
| Current version | 2.4.7 |
| Active installations | 70,000+ |
| Rating | 4.5 / 5 |
| License | GPL-2.0-or-later (free + paid upgrades via GutenKit Pro) |
| WP minimum | 6.1 |
| PHP minimum | 7.4 |
| Homepage | https://gutenkit.com/ |
| WordPress.org | https://wordpress.org/plugins/gutenkit-blocks-addon/ |

GutenKit is WPDeveloper's Gutenberg block library, positioning as the Gutenberg-native companion to their Essential Addons for Elementor product (§8.13). It is the newest and smallest-install-count of the 18 supported plugins, indicating early-growth stage. GutenKit registers blocks under the `gutenkit/` namespace and follows standard Gutenberg `register_block_type()` patterns throughout. AllyWorker agents should be aware that both GutenKit and Spectra (§8.16) may be active simultaneously — they do not conflict but both register `40+` blocks to the editor, requiring care when auditing the block library.

Key hooks and APIs:
- `gutenkit/register_blocks` filter — add or remove blocks from GutenKit's block registry
- `gutenkit_block_category` filter — modify the "GutenKit" editor block category
- `register_block_type( 'gutenkit/{block-name}', $args )` — standard block registration used for server-side rendering blocks
- `\GutenKit\App` — main plugin class; access via `\GutenKit\App::instance()`
- Blocks registered under `gutenkit/` namespace; settings stored in `gutenkit_blocks_settings` option
- Block assets enqueued via `enqueue_block_editor_assets` hook

---

## 9. References

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
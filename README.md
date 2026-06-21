# AllyWorker

> **AI agent tools for WordPress — read, inspect, manage, and build via MCP.**

AllyWorker connects AI agents to your WordPress site through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). Any compatible AI client — Claude, Cursor, Codex, Windsurf, GitHub Copilot, and more — can inspect and interact with your site in real time using WordPress Application Passwords.

No proxy. No hosted service. The AI client connects directly to your server over HTTPS.

---

## Requirements

| | |
|---|---|
| WordPress | 6.9+ |
| PHP | 8.0+ |
| HTTPS | Required |

WordPress 6.9+ is required because AllyWorker uses the **WordPress Abilities API** (`wp_register_ability()`), which is part of WordPress core since 6.9.

---

## Quick Start

**1. Install & activate**
```bash
wp plugin install allyworker --activate
```

**2. Create an Application Password**
Go to **WordPress Admin → Users → Your Profile → Application Passwords**. Create a password and name it after your AI client (e.g. `Claude Code`).

**3. Connect your AI client**
Go to **AllyWorker → Connect**. Copy the MCP configuration for your client and paste it in. The configuration uses your site URL and Application Password — no separate secret key.

---

## MCP Abilities

AllyWorker registers the following abilities via the WordPress Abilities API. Each ability is exposed as an MCP tool through the bundled `wordpress/mcp-adapter`. All abilities are authenticated via **WordPress Application Passwords** over HTTPS.

### Free abilities

### Free abilities

| Ability | Description |
|---|---|
| `allyworker/file-read` | Read any file on the server |
| `allyworker/file-list` | List files in a directory |
| `allyworker/file-disable` | Disable a sandbox PHP file (rename to `.disabled`) |
| `allyworker/file-enable` | Re-enable a previously disabled sandbox file |
| `allyworker/create-upload-link` | Create a temporary upload endpoint and bearer token |
| `allyworker/site-info` | Full install snapshot: version, plugins, theme, options |
| `allyworker/option-get` | Get a WordPress option |
| `allyworker/option-set` | Set a WordPress option |
| `allyworker/post-query` | Query posts via `WP_Query` |
| `allyworker/create-admin-access-link` | Create a temporary one-time admin session link |
| `allyworker/skill-list` | List all skills with their names and trigger descriptions |
| `allyworker/skill-read` | Read a skill's full body by name |
| `allyworker/skill-create` | Create a new skill (name, description, body) |
| `allyworker/skill-update` | Update an existing skill |
| `allyworker/skill-delete` | Delete a skill by name |

### Pro abilities ([AllyWorker Pro](https://allyworker.com/pro/) required)

| Ability | Description |
|---|---|
| `allyworker/php-execute` | Run arbitrary PHP inside the WordPress process |
| `allyworker/wpcli-run` | Execute WP-CLI commands |
| `allyworker/db-query` | Run SQL queries via `$wpdb` |
| `allyworker/file-write` | Write or create files (atomic, with `.bak` backup) |
| `allyworker/file-edit` | Find-and-replace in a file |
| `allyworker/file-delete` | Delete a file or directory |

Pro abilities are injected via the `allyworker_abilities` filter when AllyWorker Pro is active. They appear in **AllyWorker → Ability Settings** alongside free abilities and can be enabled or disabled individually. See [SECURITY.md](./SECURITY.md).

---

## Security Model

- Authentication uses **WordPress Application Passwords** — native WordPress, revocable per client
- Every request validated by the `wordpress/mcp-adapter` against the WordPress user table
- Each ability has a `permission_callback` requiring `manage_options` capability (super-admin on Multisite)
- HTTPS enforced — plugin warns if SSL is not detected
- The sandbox directory (`wp-content/allyworker-sandbox/`) isolates PHP snippets; files that cause fatal errors are auto-disabled
- PHP file uploads via `create-upload-link` are restricted to the sandbox directory

**Pro abilities** (PHP execution, WP-CLI, direct SQL, filesystem writes) are intended for development and staging environments. See [SECURITY.md](./SECURITY.md).

---

## Agent Skills

Skills are short Markdown playbooks stored in the **WordPress database** and managed from **AllyWorker → Skills** in the admin. The agent reads only each skill's `description` field at session start to decide which skills are relevant; the full body is loaded on demand when the description matches the task.

Each skill has a small YAML frontmatter block:

```markdown
---
name: page-naming-conventions
description: How to name new pages on this site. Use whenever you create or rename a page.
enable_agentic: true
enable_prompt: false
---

# Page naming conventions

Slugs: lowercase, hyphen-separated.
Parent pages: services under /services/, everything else top-level.
```

- `enable_agentic: true` — the agent fires this skill automatically when the description matches
- `enable_prompt: true` — the skill appears in the AI client's prompt menu for explicit invocation

Skills are site-wide: every AI client connected to this site shares the same skill set. See [AGENT_SKILLS.md](./AGENT_SKILLS.md) for the full format, examples, and writing guide.

---

## Project Structure

```
allyworker/
├── allyworker.php                        # Plugin entry point (headers, constants, bootstrap)
│
├── includes/                          # PSR-4 autoloaded source (namespace: AllyWorker\)
│   ├── Plugin.php                     # Singleton bootstrap — loads MCP Adapter, registers abilities
│   ├── Admin/
│   │   ├── AdminMenu.php              # Top-level menu + asset enqueue
│   │   ├── SettingsPage.php           # Settings page (ability toggles)
│   │   └── ConnectPage.php            # Connect page (Application Password setup)
│   ├── Abilities/                     # One file per ability — each calls wp_register_ability()
│   │   ├── Core/
│   │   │   └── DiscoverAbilities.php
│   │   ├── Files/
│   │   │   ├── FileRead.php
│   │   │   ├── FileList.php
│   │   │   ├── FileDisable.php
│   │   │   ├── FileEnable.php
│   │   │   └── CreateUploadLink.php
│   │   ├── Site/
│   │   │   ├── SiteInfo.php
│   │   │   ├── OptionGet.php
│   │   │   ├── OptionSet.php
│   │   │   ├── PostQuery.php
│   │   │   └── CreateAdminAccessLink.php
│   │   ├── Skills/
│   │   │   ├── SkillList.php
│   │   │   ├── SkillRead.php
│   │   │   ├── SkillCreate.php
│   │   │   ├── SkillUpdate.php
│   │   │   ├── SkillDelete.php
│   │   │   ├── SkillListRevisions.php
│   │   │   └── SkillRestoreRevision.php
│   │   ├── Gutenberg/                 # Block Editor Queue abilities
│   │   └── Themes/
│   ├── Runner/
│   │   ├── PhpRunner.php              # Temp-file PHP sandbox (used by Pro)
│   │   ├── CliRunner.php              # WP-CLI proc_open wrapper (used by Pro)
│   │   ├── DbRunner.php               # $wpdb query interface (used by Pro)
│   │   └── FileManager.php            # Atomic read/write/list
│   ├── Skills/
│   │   ├── Repository.php             # DB read/write for skill records
│   │   ├── AdminPage.php              # AllyWorker → Skills admin UI
│   │   └── Schema.php                 # DB table creation + upgrade
│   └── Utils/
│       └── Requirements.php           # PHP/WP version checks
│
├── src/                               # Front-end source (compiled by wp-scripts → assets/)
│   ├── admin/
│   │   ├── index.js                   # Admin UI entry (React JSX)
│   │   └── admin.scss                 # Admin styles
│   └── frontend/
│       ├── index.js                   # Frontend entry (vanilla JS)
│       └── frontend.scss              # Frontend styles
│
├── assets/                            # Compiled output (committed, not edited directly)
│
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   └── Integration/
│
├── docs/
│   └── RND.md                         # Research & development notes
│
├── stubs/                             # WordPress stubs for static analysis
│
├── CLAUDE.md                          # AI agent context (Claude Code / Claude Desktop)
├── GEMINI.md                          # AI agent context (Gemini CLI)
├── AGENT_SKILLS.md                    # Skills system documentation
├── SECURITY.md                        # Security model + threat analysis
├── CONTRIBUTING.md                    # Contribution guide
├── CHANGELOG.txt
├── composer.json                      # PHP deps + PSR-4 autoload (includes wordpress/mcp-adapter)
├── phpstan.neon                       # Static analysis config
├── phpunit.xml                        # Test runner config
├── package.json                       # Node build scripts (@wordpress/scripts)
├── webpack.config.js                  # webpack config (extends @wordpress/scripts)
└── README.md
```

---

## Development Setup

```bash
# Clone into your WordPress plugins directory
git clone https://github.com/allyworker/allyworker.git wp-content/plugins/allyworker
cd wp-content/plugins/allyworker

# PHP dependencies (includes wordpress/mcp-adapter via Jetpack Autoloader)
composer install

# Node build tooling
npm install

# Build front-end assets
npm run build    # compile JSX + SCSS once
npm start        # watch mode for development
```

### Code Quality

```bash
composer lint        # PHPLint syntax check
composer analyze     # PHPStan level 8 + WordPress stubs
composer test        # PHPUnit
npm run lint         # wp-scripts lint-js + lint-style
npm run build        # webpack build (must pass before committing)
```

All PRs must pass all checks above. See [CONTRIBUTING.md](./CONTRIBUTING.md).

### Adding a Free Ability

1. Create `includes/Abilities/YourAbility.php` in namespace `AllyWorker\Abilities`
2. Extend `AbstractAbility` and implement all abstract methods
3. Add it to `Abilities::create_abilities()` in `includes/Abilities/Abilities.php`
4. Add a unit test in `tests/Unit/Abilities/YourAbilityTest.php`

### Adding a Pro Ability

Pro abilities live in [AllyWorker Pro](https://allyworker.com/pro/) under `includes/Abilities/Pro/`. They extend `AllyWorker\Abilities\AbstractAbility` and are injected via the `allyworker_abilities` filter — the Runner classes (`PhpRunner`, `CliRunner`, `DbRunner`, `FileManager`) remain in the free plugin for Pro to use.

---

## Roadmap

See [docs/RND.md](./docs/RND.md) for the full plan.

---

## License

[GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html)

# WPCodex

> **The AI operating system for WordPress developers. Full WordPress control for AI agents — via MCP.**

WPCodex is a WordPress plugin that turns your WordPress installation into a fully programmable environment for AI agents. It exposes PHP execution, WP-CLI commands, database access, and filesystem operations through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/), so any compatible AI client can build, debug, and manage your site in real time.

No proxy. No hosted service. The AI client connects directly to your server over HTTPS.

---

## Requirements

| | |
|---|---|
| WordPress | 6.9+ |
| PHP | 8.0+ |
| WP-CLI | 2.8+ *(optional, for CLI tools)* |
| HTTPS | Required |

WordPress 6.9+ is required because WPCodex uses the **WordPress Abilities API** (`wp_register_ability()`), which is part of WordPress core since 6.9.

---

## Quick Start

**1. Install & activate**
```bash
wp plugin install wpcodex --activate
```

**2. Create an Application Password**
Go to **WordPress Admin → Users → Your Profile → Application Passwords**. Create a password and name it after your AI client (e.g. `Claude Code`).

**3. Connect your AI client**
Go to **WPCodex → Connect**. Copy the MCP configuration for your client and paste it in. The configuration uses your site URL and Application Password — no separate secret key.

---

## MCP Abilities

WPCodex registers the following abilities via the WordPress Abilities API. Each ability is exposed as an MCP tool through the bundled `wordpress/mcp-adapter`.

| Ability | Description |
|---|---|
| `wpcodex/php-execute` | Run arbitrary PHP inside the WordPress process |
| `wpcodex/wpcli-run` | Execute WP-CLI commands |
| `wpcodex/db-query` | Run SQL queries via `$wpdb` |
| `wpcodex/file-read` | Read any file on the server |
| `wpcodex/file-write` | Write or create files (atomic, with `.bak` backup) |
| `wpcodex/file-list` | List files in a directory |
| `wpcodex/site-info` | Full install snapshot: version, plugins, theme, options |
| `wpcodex/option-get` | Get a WordPress option |
| `wpcodex/option-set` | Set a WordPress option |
| `wpcodex/post-query` | Query posts via `WP_Query` |
| `wpcodex/skill-list` | List all skills with their names and trigger descriptions |
| `wpcodex/skill-read` | Read a skill's full body by name |
| `wpcodex/skill-create` | Create a new skill (name, description, body) |
| `wpcodex/skill-update` | Update an existing skill |
| `wpcodex/skill-delete` | Delete a skill by name |

All abilities are authenticated via **WordPress Application Passwords** over HTTPS. See [SECURITY.md](./SECURITY.md).

---

## Security Model

WPCodex is designed for **development and staging environments**.

- Authentication uses **WordPress Application Passwords** — native WordPress, revocable per client
- Every request validated by the `wordpress/mcp-adapter` against the WordPress user table
- Each ability has a `permission_callback` requiring `manage_options` capability
- PHP execution uses a temp-file sandbox with automatic cleanup
- File writes are atomic and create `.bak` backups before overwriting
- Subprocess env vars are sanitised before WP-CLI calls
- HTTPS enforced — plugin warns if SSL is not detected

**Do not activate on production without understanding what arbitrary PHP execution means.** See [SECURITY.md](./SECURITY.md).

---

## Agent Skills

Skills are short Markdown playbooks stored in the **WordPress database** and managed from **WPCodex → Skills** in the admin. The agent reads only each skill's `description` field at session start to decide which skills are relevant; the full body is loaded on demand when the description matches the task.

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
wpcodex/
├── wpcodex.php                        # Plugin entry point (headers, constants, bootstrap)
│
├── includes/                          # PSR-4 autoloaded source (namespace: WPCodex\)
│   ├── Plugin.php                     # Singleton bootstrap — loads MCP Adapter, registers abilities
│   ├── Admin/
│   │   ├── AdminMenu.php              # Top-level menu + asset enqueue
│   │   ├── SettingsPage.php           # Settings page (ability toggles)
│   │   └── ConnectPage.php            # Connect page (Application Password setup)
│   ├── Abilities/                     # One file per ability — each calls wp_register_ability()
│   │   ├── PhpExecute.php
│   │   ├── WpCliRun.php
│   │   ├── DbQuery.php
│   │   ├── FileRead.php
│   │   ├── FileWrite.php
│   │   ├── FileList.php
│   │   ├── SiteInfo.php
│   │   ├── OptionGet.php
│   │   ├── OptionSet.php
│   │   ├── PostQuery.php
│   │   ├── SkillList.php
│   │   ├── SkillRead.php
│   │   ├── SkillCreate.php
│   │   ├── SkillUpdate.php
│   │   └── SkillDelete.php
│   ├── Runner/
│   │   ├── PhpRunner.php              # Temp-file PHP sandbox
│   │   ├── CliRunner.php              # WP-CLI proc_open wrapper
│   │   ├── DbRunner.php               # $wpdb query interface
│   │   └── FileManager.php            # Atomic read/write/list
│   ├── Skills/
│   │   ├── Repository.php             # DB read/write for skill records
│   │   ├── AdminPage.php              # WPCodex → Skills admin UI
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
│   ├── admin/
│   │   ├── admin.js
│   │   ├── admin.css
│   │   └── admin.asset.php
│   └── frontend/
│       ├── frontend.js
│       ├── frontend.css
│       └── frontend.asset.php
│
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   │   └── AbilityTest.php
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
git clone https://github.com/wpcodex/wpcodex.git wp-content/plugins/wpcodex
cd wp-content/plugins/wpcodex

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

### Adding a Custom Ability

1. Create `includes/Abilities/YourAbility.php` in namespace `WPCodex\Abilities`
2. Register on the `wp_abilities_api_init` hook:

```php
add_action( 'wp_abilities_api_init', function (): void {
    wp_register_ability( 'wpcodex/your-ability', [
        'label'               => __( 'Your Ability', 'wpcodex' ),
        'description'         => __( 'What this ability does for the agent.', 'wpcodex' ),
        'category'            => 'wpcodex',
        'input_schema'        => [
            'type'       => 'object',
            'properties' => [
                'param' => [ 'type' => 'string', 'description' => 'Parameter description.' ],
            ],
            'required'   => [ 'param' ],
        ],
        'execute_callback'    => static function ( array $args ): string {
            // Delegate to a Runner class.
            return ( new \WPCodex\Runner\YourRunner() )->run( $args['param'] );
        },
        'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        'meta'                => [
            'mcp' => [ 'public' => true, 'type' => 'tool' ],
        ],
    ] );
} );
```

---

## Roadmap

See [docs/RND.md](./docs/RND.md) for the full plan, including background job queue, multisite support, read-only mode, and the Pro builder specialisation tier.

---

## License

[GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html)
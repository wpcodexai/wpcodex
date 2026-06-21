# AllyWorker — Agent Context

## What This Is

AllyWorker gives AI agents **unrestricted control over a WordPress installation** via an MCP server plugin. With arbitrary PHP execution, WP-CLI access, full filesystem access, and database query support, an agent can do *anything* WordPress can do — install plugins, modify themes, query the database, call external APIs, and build custom functionality on the fly.

The abilities are intentionally unconstrained building blocks. The plugin turns a WordPress site into a fully programmable environment for AI.

Requires WordPress 6.9+ and PHP 8.0+.

**The MCP server is disabled by default.** It only activates when the site owner has added the following to `wp-config.php`:

```php
define( 'WP_ALLY_WORKER_ENABLE', true );
```

If this constant is absent, the plugin boots no MCP transport, registers no abilities, and exposes no REST endpoints. If you cannot connect, ask the site owner to confirm this constant is set.

---

## Session Start Protocol

At the start of **every** session, run these steps in order before doing anything else:

1. Call `allyworker/site-info` — get the live install snapshot (WP version, PHP version, active plugins, active theme, site URLs).
2. Call `allyworker/skill-list` — retrieve all skill names and descriptions.
3. For every skill where `enable_agentic` is `true`, call `allyworker/skill-read` to load the full body. These are your standing instructions for this site.
4. For task-specific skills (builders, plugins, workflows), load the relevant ones once the task is clear.

---

## Available Abilities

All abilities are registered via the WordPress Abilities API and exposed as MCP tools through `wordpress/mcp-adapter`. Authentication uses **WordPress Application Passwords**.

### Core WordPress abilities (free)

| Ability | Purpose |
|---|---|
| `allyworker/file-read` | Read a file. Pass absolute `path`. |
| `allyworker/file-list` | List a directory. Pass `path` and optional `recursive` (bool). |
| `allyworker/file-disable` | Disable a sandbox file. Pass `path` (must be inside `allyworker-sandbox/`). |
| `allyworker/file-enable` | Re-enable a disabled sandbox file. Pass `path`. |
| `allyworker/create-upload-link` | Create a temporary upload token. Pass `path` and optional `expires_in`, `max_bytes`, `overwrite`. |
| `allyworker/site-info` | Full install snapshot. No arguments. |
| `allyworker/option-get` | Get a WordPress option. Pass `option_name`. |
| `allyworker/option-set` | Set a WordPress option. Pass `option_name`, `option_value`, optional `autoload` (bool). |
| `allyworker/post-query` | Run a `WP_Query`. Pass `query_args` (object). Returns `found_posts` + `posts` array. |
| `allyworker/create-admin-access-link` | Create a temporary admin session token. Pass optional `user_id`, `expires_in`, `admin_path`. |

### Pro abilities (AllyWorker Pro required)

| Ability | Purpose |
|---|---|
| `allyworker/php-execute` | Run PHP code inside the WordPress process. Pass `code` (no opening tag). |
| `allyworker/wpcli-run` | Execute WP-CLI. Pass `command` without the leading `wp`. Optional `timeout` (int, seconds). |
| `allyworker/db-query` | Run SQL via `$wpdb`. Pass `sql` and optional `args` array. SELECT returns rows; mutations return affected count. |
| `allyworker/file-write` | Write a file atomically (`.bak` backup created first). Pass `path` and `content`. |
| `allyworker/file-edit` | Find-and-replace in a file. Pass `path`, `old_string`, `new_string`. |
| `allyworker/file-delete` | Delete a file or directory. Pass `path` and optional `recursive` (bool). |

### Skills abilities

| Ability | Purpose |
|---|---|
| `allyworker/skill-list` | Returns all skills with `name`, `description`, `enable_agentic`, `enable_prompt`. |
| `allyworker/skill-read` | Returns the full body of a skill. Pass `name`. |
| `allyworker/skill-create` | Creates a new skill. Pass `name`, `description`, `body`, optional `enable_agentic` (bool), `enable_prompt` (bool). |
| `allyworker/skill-update` | Updates an existing skill. Pass `name` and any fields to change. |
| `allyworker/skill-delete` | Deletes a skill. Pass `name`. |

---

## Skills System

Skills are Markdown playbooks stored in the **WordPress database**. They are **not** files on disk.

- `allyworker/skill-list` returns names and descriptions — read these to understand what standing instructions exist
- Load a skill body with `allyworker/skill-read` before performing the relevant task
- Skills with `enable_agentic: true` should be loaded at session start
- After a complex task, create a new skill documenting what you learned:

```
allyworker/skill-create
  name:           "elementor-header-patterns"
  description:    "Elementor header template IDs and container conventions for this site. Use when modifying the header."
  enable_agentic: true
  enable_prompt:  true
  body:           "# Elementor header patterns\n\nHeader template ID: 42\n..."
```

Skill body format — YAML frontmatter + Markdown:
```markdown
---
name: your-skill-name
description: One-line trigger. Write it so the agent knows when to fire this skill.
enable_agentic: true
enable_prompt: true
---

# Skill title

Your instructions here.
```

---

## Project Layout

```
allyworker/
├── allyworker.php       # Entry point — loads MCP Adapter, registers abilities
├── includes/            # PSR-4 root (namespace: AllyWorker\)
│   ├── Plugin.php
│   ├── Admin/           # AdminMenu, SettingsPage, ConnectPage
│   ├── Abilities/       # One file per ability — each calls wp_register_ability()
│   ├── Runner/          # PhpRunner, CliRunner, DbRunner, FileManager (execution logic)
│   ├── Skills/          # Repository, AdminPage, Schema (DB engine — no .md files)
│   └── Utils/           # Requirements
├── src/                 # Front-end source (React JSX + SCSS — no TypeScript)
│   ├── admin/           # src/admin/index.js → assets/admin/admin.js
│   └── frontend/        # src/frontend/index.js → assets/frontend/frontend.js
├── assets/              # Compiled output — do not edit directly
├── tests/               # PHPUnit test suites
├── docs/                # Developer documentation (RND.md, etc.)
└── stubs/               # WordPress PHP stubs for static analysis
```

---

## Code Quality

All changes must pass before committing:

```bash
composer lint        # PHPLint syntax check
composer analyze     # PHPStan level 8 + WordPress stubs (phpstan.neon)
composer test        # PHPUnit
npm run lint         # wp-scripts lint-js + lint-style
npm run build        # webpack via @wordpress/scripts (JSX + SCSS → assets/)
```

**Never modify `release-info.json` by hand** — updated programmatically on release.

---

## Coding Conventions

- **Namespace root:** `AllyWorker\` → `includes/`
- **Every PHP file:** `declare(strict_types=1);` at the top
- **PHP 8.0 minimum:** typed properties, typed parameters, return types on every method
- **WordPress Coding Standards** apply to all PHP
- **Ability naming:** `allyworker/kebab-case` — e.g. `allyworker/php-execute`, `allyworker/skill-list`
- **Hook names:** prefixed `allyworker_` — e.g. `add_action('allyworker_after_activate', ...)`
- **Option names:** prefixed `allyworker_` — e.g. `get_option('allyworker_setting_name')`
- **Transient keys:** prefixed `allyworker_transient_`
- **No JavaScript in `assets/`** — write source in `src/admin/index.js` or `src/frontend/index.js`
- **No CSS in `assets/`** — import your `.scss` inside the JS entry; webpack extracts it
- **No TypeScript** — plain JavaScript with JSX via `@wordpress/scripts`
- **Admin data to JS:** `wp_localize_script()` only — never echo raw PHP into `<script>` tags
- **Output escaping:** `esc_html()`, `esc_attr()`, `esc_url()` on every echoed value
- **Nonces:** `wp_nonce_field()` on every form; `check_admin_referer()` on every handler
- **Capability:** `current_user_can('manage_options')` at the top of every admin render and handler

---

## Adding a New Ability

1. Create `includes/Abilities/YourAbility.php` — namespace `AllyWorker\Abilities`
2. Register on the `wp_abilities_api_init` hook:

```php
add_action( 'wp_abilities_api_init', function (): void {
    wp_register_ability( 'allyworker/your-ability', [
        'label'               => __( 'Your Ability', 'allyworker' ),
        'description'         => __( 'What this ability does for the agent.', 'allyworker' ),
        'category'            => 'allyworker',
        'input_schema'        => [
            'type'       => 'object',
            'properties' => [
                'param' => [ 'type' => 'string', 'description' => 'Parameter description.' ],
            ],
            'required'   => [ 'param' ],
        ],
        'execute_callback'    => static function ( array $args ): string {
            return ( new \AllyWorker\Runner\YourRunner() )->run( $args['param'] );
        },
        'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        'meta'                => [
            'mcp' => [ 'public' => true, 'type' => 'tool' ],
        ],
    ] );
} );
```

3. Add a unit test in `tests/Unit/Abilities/YourAbilityTest.php`
4. Document the ability in [README.md](./README.md) MCP Abilities table
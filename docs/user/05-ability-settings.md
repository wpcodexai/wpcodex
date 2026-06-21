# Ability Settings

The Ability Settings page lets you enable or disable individual AllyWorker abilities. Disabling an ability removes it from the MCP tool registry so agents cannot call it, even when AI Abilities are globally enabled.

Navigate to **AllyWorker → Abilities Settings**.

---

## When to disable an ability

Disabling individual abilities is useful when you want to:

- **Restrict what an agent can do** — for example, allow file reads but block file writes on a shared staging environment.
- **Hide abilities from an agent's tool list** — fewer tools means less noise in the agent's context.
- **Temporarily block a risky operation** — disable `allyworker/php-execute` while someone else is using the site.

---

## How it works

Each ability has its own **Enabled / Disabled** toggle. The default state is **Enabled** for all abilities.

When you disable an ability:
- It is unregistered from the WordPress Abilities registry at boot time.
- The agent will not see it in `allyworker/discover-abilities`.
- If the agent tries to call it anyway (e.g. from a cached tool list), the MCP server returns a "tool not found" error.

Changes take effect immediately — no need to restart WordPress or the MCP server.

---

## Ability groups

Abilities are grouped by category on the settings page:

**Site**
- `allyworker/php-execute` — Execute arbitrary PHP
- `allyworker/wpcli-run` — Run WP-CLI commands
- `allyworker/db-query` — Run SQL queries
- `allyworker/site-info` — Read site info snapshot
- `allyworker/post-query` — Run WP_Query
- `allyworker/option-get` / `allyworker/option-set` — Read/write options
- `allyworker/create-admin-access-link` — Generate one-time admin login links

**Files**
- `allyworker/file-read` — Read files
- `allyworker/file-write` — Write files
- `allyworker/file-edit` — Edit files (find-and-replace)
- `allyworker/file-list` — List directory contents
- `allyworker/file-delete` — Delete files
- `allyworker/file-disable` / `allyworker/file-enable` — Toggle sandbox files
- `allyworker/create-upload-link` — Generate upload URLs

**Skills**
- `allyworker/skill-list` / `allyworker/skill-read` / `allyworker/skill-create` / `allyworker/skill-update` / `allyworker/skill-delete`
- `allyworker/skill-list-revisions` / `allyworker/skill-restore-revision`

**Gutenberg**
- `allyworker/gutenberg-get-content`
- `allyworker/gutenberg-write-content`
- `allyworker/gutenberg-create-pending-batch`
- `allyworker/gutenberg-add-pending-change`
- `allyworker/gutenberg-enable-batch-finalization`
- `allyworker/gutenberg-get-finalization-url`
- `allyworker/gutenberg-get-finalizer-runtime`

---

## Global vs individual control

| Switch | What it controls |
|---|---|
| **Configuration → Enable AI Abilities** | Enables or disables the entire MCP server. All abilities are unregistered when this is off. |
| **Ability Settings → individual toggle** | Enables or disables one specific ability while the server stays running. |

Use the global toggle when you want to pause all agent access. Use individual toggles for fine-grained control.

---

## Multisite

On a WordPress multisite network, only **super admins** can access AllyWorker settings and call AllyWorker abilities. Individual site administrators cannot enable or disable abilities on their own subsite.

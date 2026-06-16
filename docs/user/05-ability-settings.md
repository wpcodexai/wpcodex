# Ability Settings

The Ability Settings page lets you enable or disable individual WPWorker abilities. Disabling an ability removes it from the MCP tool registry so agents cannot call it, even when AI Abilities are globally enabled.

Navigate to **WPWorker → Abilities Settings**.

---

## When to disable an ability

Disabling individual abilities is useful when you want to:

- **Restrict what an agent can do** — for example, allow file reads but block file writes on a shared staging environment.
- **Hide abilities from an agent's tool list** — fewer tools means less noise in the agent's context.
- **Temporarily block a risky operation** — disable `wpworker/php-execute` while someone else is using the site.

---

## How it works

Each ability has its own **Enabled / Disabled** toggle. The default state is **Enabled** for all abilities.

When you disable an ability:
- It is unregistered from the WordPress Abilities registry at boot time.
- The agent will not see it in `wpworker/discover-abilities`.
- If the agent tries to call it anyway (e.g. from a cached tool list), the MCP server returns a "tool not found" error.

Changes take effect immediately — no need to restart WordPress or the MCP server.

---

## Ability groups

Abilities are grouped by category on the settings page:

**Site**
- `wpworker/php-execute` — Execute arbitrary PHP
- `wpworker/wpcli-run` — Run WP-CLI commands
- `wpworker/db-query` — Run SQL queries
- `wpworker/site-info` — Read site info snapshot
- `wpworker/post-query` — Run WP_Query
- `wpworker/option-get` / `wpworker/option-set` — Read/write options
- `wpworker/create-admin-access-link` — Generate one-time admin login links

**Files**
- `wpworker/file-read` — Read files
- `wpworker/file-write` — Write files
- `wpworker/file-edit` — Edit files (find-and-replace)
- `wpworker/file-list` — List directory contents
- `wpworker/file-delete` — Delete files
- `wpworker/file-disable` / `wpworker/file-enable` — Toggle sandbox files
- `wpworker/create-upload-link` — Generate upload URLs

**Skills**
- `wpworker/skill-list` / `wpworker/skill-read` / `wpworker/skill-create` / `wpworker/skill-update` / `wpworker/skill-delete`
- `wpworker/skill-list-revisions` / `wpworker/skill-restore-revision`

**Gutenberg**
- `wpworker/gutenberg-get-content`
- `wpworker/gutenberg-write-content`
- `wpworker/gutenberg-create-pending-batch`
- `wpworker/gutenberg-add-pending-change`
- `wpworker/gutenberg-enable-batch-finalization`
- `wpworker/gutenberg-get-finalization-url`
- `wpworker/gutenberg-get-finalizer-runtime`

---

## Global vs individual control

| Switch | What it controls |
|---|---|
| **Configuration → Enable AI Abilities** | Enables or disables the entire MCP server. All abilities are unregistered when this is off. |
| **Ability Settings → individual toggle** | Enables or disables one specific ability while the server stays running. |

Use the global toggle when you want to pause all agent access. Use individual toggles for fine-grained control.

---

## Multisite

On a WordPress multisite network, only **super admins** can access WPWorker settings and call WPWorker abilities. Individual site administrators cannot enable or disable abilities on their own subsite.

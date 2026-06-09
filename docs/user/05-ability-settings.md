# Ability Settings

The Ability Settings page lets you enable or disable individual WPCodex abilities. Disabling an ability removes it from the MCP tool registry so agents cannot call it, even when AI Abilities are globally enabled.

Navigate to **WPCodex → Abilities Settings**.

---

## When to disable an ability

Disabling individual abilities is useful when you want to:

- **Restrict what an agent can do** — for example, allow file reads but block file writes on a shared staging environment.
- **Hide abilities from an agent's tool list** — fewer tools means less noise in the agent's context.
- **Temporarily block a risky operation** — disable `wpcodex/php-execute` while someone else is using the site.

---

## How it works

Each ability has its own **Enabled / Disabled** toggle. The default state is **Enabled** for all abilities.

When you disable an ability:
- It is unregistered from the WordPress Abilities registry at boot time.
- The agent will not see it in `wpcodex/discover-abilities`.
- If the agent tries to call it anyway (e.g. from a cached tool list), the MCP server returns a "tool not found" error.

Changes take effect immediately — no need to restart WordPress or the MCP server.

---

## Ability groups

Abilities are grouped by category on the settings page:

**Site**
- `wpcodex/php-execute` — Execute arbitrary PHP
- `wpcodex/wpcli-run` — Run WP-CLI commands
- `wpcodex/db-query` — Run SQL queries
- `wpcodex/site-info` — Read site info snapshot
- `wpcodex/post-query` — Run WP_Query
- `wpcodex/option-get` / `wpcodex/option-set` — Read/write options
- `wpcodex/create-admin-access-link` — Generate one-time admin login links

**Files**
- `wpcodex/file-read` — Read files
- `wpcodex/file-write` — Write files
- `wpcodex/file-edit` — Edit files (find-and-replace)
- `wpcodex/file-list` — List directory contents
- `wpcodex/file-delete` — Delete files
- `wpcodex/file-disable` / `wpcodex/file-enable` — Toggle sandbox files
- `wpcodex/create-upload-link` — Generate upload URLs

**Skills**
- `wpcodex/skill-list` / `wpcodex/skill-read` / `wpcodex/skill-create` / `wpcodex/skill-update` / `wpcodex/skill-delete`
- `wpcodex/skill-list-revisions` / `wpcodex/skill-restore-revision`

**Gutenberg**
- `wpcodex/gutenberg-get-content`
- `wpcodex/gutenberg-write-content`
- `wpcodex/gutenberg-create-pending-batch`
- `wpcodex/gutenberg-add-pending-change`
- `wpcodex/gutenberg-enable-batch-finalization`
- `wpcodex/gutenberg-get-finalization-url`
- `wpcodex/gutenberg-get-finalizer-runtime`

---

## Global vs individual control

| Switch | What it controls |
|---|---|
| **Configuration → Enable AI Abilities** | Enables or disables the entire MCP server. All abilities are unregistered when this is off. |
| **Ability Settings → individual toggle** | Enables or disables one specific ability while the server stays running. |

Use the global toggle when you want to pause all agent access. Use individual toggles for fine-grained control.

---

## Multisite

On a WordPress multisite network, only **super admins** can access WPCodex settings and call WPCodex abilities. Individual site administrators cannot enable or disable abilities on their own subsite.

# Abilities Reference

Abilities are the tools WPCodex exposes to AI agents via the MCP server. Every ability requires the agent to be authenticated with an Application Password and AI Abilities to be enabled on the **Configuration** page.

All ability names follow the pattern `wpcodex/<name>`.

---

## Session start

### `wpcodex/discover-abilities`

Returns the full list of registered MCP tools plus a rich instruction block: WordPress/PHP version, locale, installed plugins (active/inactive), WordPress-native development guidelines, active theme, and the skill catalog.

**Call this first at the start of every agent session.** Agents that skip this step will not see environment context or available skills.

---

## Site abilities

### `wpcodex/php-execute`

Run arbitrary PHP code inside the live WordPress process.

| Input | Type | Description |
|---|---|---|
| `code` | string | PHP to execute. **Do not include the opening `<?php` tag.** |

The full WordPress environment is available: `$wpdb`, all functions, all active plugins. Output is captured and returned.

**Example use:** Query the database, call a plugin's API, inspect option values, run one-off data migrations.

> Always inspect before modifying. Read first, then write.

---

### `wpcodex/wpcli-run`

Execute a WP-CLI command.

| Input | Type | Description |
|---|---|---|
| `command` | string | The WP-CLI command **without** the leading `wp`. |
| `timeout` | integer (optional) | Timeout in seconds. |

**Examples:**
- `plugin list` — list installed plugins
- `post list --post_type=page --format=json` — list pages as JSON
- `cache flush` — flush the object cache

---

### `wpcodex/db-query`

Run a SQL query via `$wpdb`.

| Input | Type | Description |
|---|---|---|
| `sql` | string | SQL query. Use `%s`/`%d` placeholders for parameterised values. |
| `args` | array (optional) | Values for placeholders. |

SELECT returns rows. INSERT / UPDATE / DELETE returns affected row count.

---

### `wpcodex/site-info`

Returns a full install snapshot: WP version, PHP version, active plugins, active theme, site URL, and admin URL. No arguments.

---

### `wpcodex/option-get` / `wpcodex/option-set`

Get or set a WordPress option.

| Input | Type | Description |
|---|---|---|
| `option_name` | string | The option key. |
| `option_value` | mixed | (set only) The value to store. |
| `autoload` | boolean (optional) | Whether to autoload. |

---

### `wpcodex/post-query`

Run a `WP_Query`.

| Input | Type | Description |
|---|---|---|
| `query_args` | object | Standard `WP_Query` arguments. |

Returns `found_posts` count and a `posts` array.

---

### `wpcodex/create-admin-access-link`

Generates a one-time admin login link for a specified user.

| Input | Type | Description |
|---|---|---|
| `user_id` | integer | ID of the user to log in as. |
| `redirect_to` | string (optional) | Admin URL to redirect to after login. |

The link expires after one use. Useful when the agent needs a browser session for tasks it cannot perform via the API.

---

## File abilities

All file paths must be absolute. Writes create a `.bak` backup of the previous file before overwriting.

### `wpcodex/file-read`

Read a file and return its contents.

| Input | Type |
|---|---|
| `path` | Absolute path to the file. |

---

### `wpcodex/file-write`

Write (or create) a file atomically.

| Input | Type |
|---|---|
| `path` | Absolute path. |
| `content` | String content to write. |

---

### `wpcodex/file-edit`

Apply a targeted search-and-replace to an existing file.

| Input | Type | Description |
|---|---|---|
| `path` | string | Absolute path. |
| `old_string` | string | Exact text to find. |
| `new_string` | string | Replacement text. |

---

### `wpcodex/file-list`

List a directory.

| Input | Type | Description |
|---|---|---|
| `path` | string | Directory to list. |
| `recursive` | boolean (optional) | Whether to list recursively. |

---

### `wpcodex/file-delete`

Delete a file.

| Input | Type |
|---|---|
| `path` | Absolute path to the file. |

---

### `wpcodex/file-disable` / `wpcodex/file-enable`

Rename a sandbox PHP file to add or remove the `.disabled` extension, preventing or allowing it from being loaded. See the [Sandbox guide](./03-sandbox.md).

---

### `wpcodex/create-upload-link`

Generate a signed URL the agent can use to upload a file directly to `wp-content/uploads/`.

---

## Skills abilities

See the [Skills guide](./02-skills.md) for the full workflow. Short reference:

| Ability | Description |
|---|---|
| `wpcodex/skill-list` | List all skills (name, description, flags). |
| `wpcodex/skill-read` | Read the full body of a skill by name. |
| `wpcodex/skill-create` | Create a new skill. |
| `wpcodex/skill-update` | Update an existing skill. |
| `wpcodex/skill-delete` | Delete a skill. |
| `wpcodex/skill-list-revisions` | List revision history for a skill. |
| `wpcodex/skill-restore-revision` | Restore a skill to an earlier revision. |

---

## Gutenberg abilities

See the [Gutenberg guide](./04-gutenberg.md) for the full workflow. Short reference:

| Ability | Description |
|---|---|
| `wpcodex/gutenberg-get-content` | Read the block content of a post/page. |
| `wpcodex/gutenberg-write-content` | Queue a full content replacement (convenience wrapper). |
| `wpcodex/gutenberg-create-pending-batch` | Create a new batch for multi-post changes. |
| `wpcodex/gutenberg-add-pending-change` | Add one post's block change to an open batch. |
| `wpcodex/gutenberg-enable-batch-finalization` | Mark batch as ready for the browser finalizer. |
| `wpcodex/gutenberg-get-finalization-url` | Get the URL the user should open to finalize. |
| `wpcodex/gutenberg-get-finalizer-runtime` | Poll finalization status (online, progress). |

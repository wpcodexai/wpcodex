# Abilities Reference

Abilities are the tools WPWorker exposes to AI agents via the MCP server. Every ability requires the agent to be authenticated with an Application Password and AI Abilities to be enabled on the **Configuration** page.

All ability names follow the pattern `wpworker/<name>`.

---

## Session start

### `wpworker/discover-abilities`

Returns the full list of registered MCP tools plus a rich instruction block: WordPress/PHP version, locale, installed plugins (active/inactive), WordPress-native development guidelines, active theme, and the skill catalog.

**Call this first at the start of every agent session.** Agents that skip this step will not see environment context or available skills.

---

## Site abilities

### `wpworker/php-execute`

Run arbitrary PHP code inside the live WordPress process.

| Input | Type | Description |
|---|---|---|
| `code` | string | PHP to execute. **Do not include the opening `<?php` tag.** |

The full WordPress environment is available: `$wpdb`, all functions, all active plugins. Output is captured and returned.

**Example use:** Query the database, call a plugin's API, inspect option values, run one-off data migrations.

> Always inspect before modifying. Read first, then write.

---

### `wpworker/wpcli-run`

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

### `wpworker/db-query`

Run a SQL query via `$wpdb`.

| Input | Type | Description |
|---|---|---|
| `sql` | string | SQL query. Use `%s`/`%d` placeholders for parameterised values. |
| `args` | array (optional) | Values for placeholders. |

SELECT returns rows. INSERT / UPDATE / DELETE returns affected row count.

---

### `wpworker/site-info`

Returns a full install snapshot: WP version, PHP version, active plugins, active theme, site URL, and admin URL. No arguments.

---

### `wpworker/option-get` / `wpworker/option-set`

Get or set a WordPress option.

| Input | Type | Description |
|---|---|---|
| `option_name` | string | The option key. |
| `option_value` | mixed | (set only) The value to store. |
| `autoload` | boolean (optional) | Whether to autoload. |

---

### `wpworker/post-query`

Run a `WP_Query`.

| Input | Type | Description |
|---|---|---|
| `query_args` | object | Standard `WP_Query` arguments. |

Returns `found_posts` count and a `posts` array.

---

### `wpworker/create-admin-access-link`

Generates a one-time admin login link for a specified user.

| Input | Type | Description |
|---|---|---|
| `user_id` | integer | ID of the user to log in as. |
| `redirect_to` | string (optional) | Admin URL to redirect to after login. |

The link expires after one use. Useful when the agent needs a browser session for tasks it cannot perform via the API.

---

## File abilities

All file paths must be absolute. Writes create a `.bak` backup of the previous file before overwriting.

### `wpworker/file-read`

Read a file and return its contents.

| Input | Type |
|---|---|
| `path` | Absolute path to the file. |

---

### `wpworker/file-write`

Write (or create) a file atomically.

| Input | Type |
|---|---|
| `path` | Absolute path. |
| `content` | String content to write. |

---

### `wpworker/file-edit`

Apply a targeted search-and-replace to an existing file.

| Input | Type | Description |
|---|---|---|
| `path` | string | Absolute path. |
| `old_string` | string | Exact text to find. |
| `new_string` | string | Replacement text. |

---

### `wpworker/file-list`

List a directory.

| Input | Type | Description |
|---|---|---|
| `path` | string | Directory to list. |
| `recursive` | boolean (optional) | Whether to list recursively. |

---

### `wpworker/file-delete`

Delete a file.

| Input | Type |
|---|---|
| `path` | Absolute path to the file. |

---

### `wpworker/file-disable` / `wpworker/file-enable`

Rename a sandbox PHP file to add or remove the `.disabled` extension, preventing or allowing it from being loaded. See the [Sandbox guide](./03-sandbox.md).

---

### `wpworker/create-upload-link`

Generate a signed URL the agent can use to upload a file directly to `wp-content/uploads/`.

---

## Skills abilities

See the [Skills guide](./02-skills.md) for the full workflow. Short reference:

| Ability | Description |
|---|---|
| `wpworker/skill-list` | List all skills (name, description, flags). |
| `wpworker/skill-read` | Read the full body of a skill by name. |
| `wpworker/skill-create` | Create a new skill. |
| `wpworker/skill-update` | Update an existing skill. |
| `wpworker/skill-delete` | Delete a skill. |
| `wpworker/skill-list-revisions` | List revision history for a skill. |
| `wpworker/skill-restore-revision` | Restore a skill to an earlier revision. |

---

## Gutenberg abilities

See the [Gutenberg guide](./04-gutenberg.md) for the full workflow. Short reference:

| Ability | Description |
|---|---|
| `wpworker/gutenberg-get-content` | Read the block content of a post/page. |
| `wpworker/gutenberg-write-content` | Queue a full content replacement (convenience wrapper). |
| `wpworker/gutenberg-create-pending-batch` | Create a new batch for multi-post changes. |
| `wpworker/gutenberg-add-pending-change` | Add one post's block change to an open batch. |
| `wpworker/gutenberg-enable-batch-finalization` | Mark batch as ready for the browser finalizer. |
| `wpworker/gutenberg-get-finalization-url` | Get the URL the user should open to finalize. |
| `wpworker/gutenberg-get-finalizer-runtime` | Poll finalization status (online, progress). |

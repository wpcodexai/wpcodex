=== Worker AI — The AI operating system for WordPress developers ===
Contributors: wpworkerai
Tags: ai, mcp, artificial-intelligence, developer-tools, automation
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 0.6.0
Requires PHP: 8.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect AI agents to your WordPress site via MCP. Read files, inspect site state, manage options, query posts, write Gutenberg content, and build agent Skills — all from your AI client.

== Description ==

Worker AI connects AI agents to your WordPress site through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). Any compatible AI client — Claude, Cursor, Codex, Windsurf, GitHub Copilot, and more — can inspect and interact with your site in real time using WordPress Application Passwords.

No proxy. No hosted service. The AI client connects directly to your server over HTTPS.

= What AI agents can do with Worker AI (free) =

* **Read files** anywhere on the server — let the agent inspect theme files, plugin code, logs, and configuration
* **List directories** to understand the file structure of your installation
* **Get a full site snapshot** — WP version, PHP version, active plugins, active theme, site URLs
* **Query posts** using standard `WP_Query` arguments
* **Read WordPress options** via the Options API
* **Set WordPress options** — update site settings through the native options API
* **Upload files** via a temporary bearer-token endpoint (non-PHP files; PHP files restricted to the sandbox)
* **Manage sandbox files** — enable or disable PHP snippets in `wp-content/wpworker-sandbox/` without touching theme files
* **Create a temporary admin session link** for browser automation tools (e.g. Claude in Chrome)
* **Write Gutenberg block content** to any post, page, or template through a browser-based finalizer queue
* **Create and manage Skills** — Markdown playbooks stored in the database that give agents standing instructions about your site

= AI Skills system =

Skills are short Markdown playbooks stored in WordPress. They capture site-specific conventions, template IDs, naming rules, and multi-step workflows. The agent reads only each skill's description at session start and loads the full body on demand. Skills can be:

* Created and edited from **Worker AI → Skills** in the admin
* Created by the agent after completing a complex task (so it remembers next time)
* Restored to any prior version using built-in revision history
* Shared across all AI clients connected to the same site

= Gutenberg Block Editor Queue =

Writing Gutenberg blocks requires the JavaScript block registry running in a real browser. Worker AI solves this with a queue-and-finalize flow:

1. The agent queues block changes via the `wpworker/gutenberg-write-content` ability
2. You open the **Block Editor Queue** page in WordPress admin
3. Changes are applied automatically using the live block registry
4. The agent receives confirmation once finalization is complete

This works for any block — core blocks, theme blocks, ACF blocks, custom blocks.

= Security =

Every ability requires the `manage_options` capability (or super-admin on Multisite). Authentication uses WordPress Application Passwords — native WordPress, revocable per client, no separate secret key. You can individually disable any ability from **Worker AI → Ability Settings** without disabling the whole plugin.

The sandbox directory (`wp-content/wpworker-sandbox/`) isolates PHP snippets from the rest of the codebase. Files that throw a fatal error are auto-disabled so they do not break the site.

= Supported AI clients =

Claude Code, Claude Desktop, Cursor, VS Code, GitHub Copilot, Windsurf, Cline, Codex, Gemini CLI, Roo Code, Amazon Q, Zed, Kilo Code, OpenCode, Antigravity, and any client that supports MCP over HTTP with `@automattic/mcp-wordpress-remote`.

= Requirements =

* WordPress 6.9 or later (uses the WordPress Abilities API introduced in 6.9)
* PHP 8.0 or later
* HTTPS enabled on your server (or `WP_ENVIRONMENT_TYPE` set to `local` or `development` for local dev)

== Installation ==

= Automatic installation =

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **Worker AI**.
3. Click **Install Now**, then **Activate**.

= Manual installation =

1. Download the plugin zip from WordPress.org.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**, then **Activate**.

= First-time setup =

After activating the plugin, go to **Worker AI → Configuration** and follow the three steps:

1. **Enable AI Abilities** — click the toggle to start the MCP server.
2. **Create an Application Password** — click **Generate application password**. The password is embedded into the connection text automatically.
3. **Connect your AI client** — copy the connection prompt and paste it into your AI agent. The agent writes the correct config for your client.

See the full [Get Started guide](https://wpworker.ai/docs/get-started/) for detailed instructions per client.

== Frequently Asked Questions ==

= What is the difference between Worker AI free and Worker AI Pro? =

The free plugin gives agents read-only and structured-write access: reading files and site state, querying posts, managing options, uploading files, writing Gutenberg blocks, and managing Skills. [Worker AI Pro](https://wpworker.ai/pro/) adds the most powerful development abilities — arbitrary PHP execution, WP-CLI, direct SQL, and full filesystem write/edit/delete access. Pro is intended for development and staging environments where those capabilities are needed.

= Is Worker AI safe to use on a production site? =

The free plugin does not provide arbitrary code execution or unrestricted filesystem write access, so the risk profile is significantly lower than Pro. That said, any plugin that allows remote agents to set WordPress options or upload files should be used with awareness. Revoke Application Passwords for any agent you no longer use, and review **Worker AI → Ability Settings** to disable abilities you do not need.

= Does this work on shared hosting? =

Yes. The free plugin has no dependency on `proc_open()` or WP-CLI. All free abilities work on standard shared hosting with HTTPS enabled. (WP-CLI execution is a Pro-only ability that requires `proc_open()`.)

= How do I revoke an agent's access? =

Go to **Worker AI → Configuration**, scroll to the password table in Step 2, and click **Revoke** next to the password you want to remove. You can also revoke from **Users → Profile → Application Passwords**.

= Can I restrict which tools the agent can use? =

Yes. Go to **Worker AI → Ability Settings** to enable or disable individual abilities. For example, you can allow file reads but block option writes, or disable the upload link ability entirely.

= Does this work with Claude Code specifically? =

Yes. Claude Code is the primary supported client. The connection prompt generated in Step 3 is optimised for Claude Code. You can also use the JSON config tab for the exact `claude mcp add` snippet.

= What is a Skill? =

A Skill is a Markdown document stored in your WordPress database. It gives the AI agent standing instructions specific to your site — things like which template IDs to use, your naming conventions, or multi-step workflows. The agent reads the skill catalog at session start and loads individual skills when their description matches the current task.

= Does Worker AI send data to any external service? =

No. Worker AI only communicates between your server and the AI client connecting to it. There is no telemetry, no external API calls, and no hosted service.

= Where is the sandbox directory? =

The sandbox directory is `wp-content/wpworker-sandbox/`. PHP files placed here are loaded automatically on every WordPress request. Files that throw a fatal error are auto-disabled so they do not break the site. PHP file uploads via `create-upload-link` are restricted to this directory.

= How does Gutenberg content writing work? =

Because Gutenberg block serialization requires the JavaScript block registry in a real browser, content changes are queued and applied through the **Block Editor Queue** page in the WordPress admin. The agent queues a change, you open the queue page, and the change is applied automatically. This supports all registered blocks including third-party and ACF blocks.

= Does this work on WordPress Multisite? =

Yes. On multisite, only super admins can access Worker AI settings and call abilities. Individual site administrators do not have access.

= I get "HTTPS required" in Step 2. What do I do? =

WordPress Application Passwords require HTTPS. For local development, add this to `wp-config.php`:

`define( 'WP_ENVIRONMENT_TYPE', 'local' );`

For staging or production, enable an SSL certificate (Let's Encrypt is free on most hosts).

== Screenshots ==

1. Configuration page — three-step setup: enable abilities, generate an application password, connect your AI client
2. Skills admin — create, edit, and manage AI playbooks with revision history
3. Ability Settings — enable or disable individual abilities, including Pro abilities when Worker AI Pro is active
4. Block Editor Queue — review and finalize Gutenberg content changes queued by the agent
5. The admin bar "Worker AI ON" indicator — a persistent reminder that AI abilities are active

== Changelog ==

= 0.6.0 =
* Moved `php-execute`, `wpcli-run`, `db-query`, `file-write`, `file-edit`, and `file-delete` to [Worker AI Pro](https://wpworker.ai/pro/)
* Free plugin now focuses on read, inspect, structured-write, Skills, Gutenberg, and sandbox management abilities
* Added Pro ability section to Ability Settings page (shows Pro badge when Worker AI Pro is not active)

= 0.5.0 =
* Core abilities: `php-execute`, `wpcli-run`, `db-query`, `site-info`, `post-query`, `option-get`, `option-set`
* File abilities: `file-read`, `file-write`, `file-edit`, `file-list`, `file-delete`, `file-disable`, `file-enable`, `create-upload-link`
* Skills abilities: `skill-list`, `skill-read`, `skill-create`, `skill-update`, `skill-delete`, `skill-list-revisions`, `skill-restore-revision`
* Gutenberg abilities: `gutenberg-get-content`, `gutenberg-write-content`, `gutenberg-create-pending-batch`, `gutenberg-add-pending-change`, `gutenberg-enable-batch-finalization`, `gutenberg-get-finalization-url`, `gutenberg-get-finalizer-runtime`
* Skills admin page with revision history and restore
* Sandbox directory with crash recovery and auto-disable
* Block Editor Queue with SSE progress and poll fallback
* Ability Settings page — per-ability enable/disable
* Configuration page — guided 3-step setup with per-client JSON config snippets
* Admin bar indicator when AI abilities are active
* Multisite: super-admin-only access
* Multilingual plugin detection in agent environment instructions (WPML, Polylang, TranslatePress)

== Upgrade Notice ==

= 0.6.0 =
The `php-execute`, `wpcli-run`, `db-query`, `file-write`, `file-edit`, and `file-delete` abilities have moved to Worker AI Pro. If you rely on these, install Worker AI Pro before upgrading.

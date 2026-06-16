# Get Started with WPWorker

WPWorker connects AI agents (Claude, Cursor, Codex, and others) to your WordPress site through the [MCP protocol](https://modelcontextprotocol.io). Once connected, your AI agent can execute PHP, run WP-CLI commands, read and write files, manage skills, and modify Gutenberg content — all directly from the agent's chat interface.

---

## Requirements

- WordPress **6.9** or later
- PHP **8.0** or later
- HTTPS enabled (or `WP_ENVIRONMENT_TYPE` set to `local` or `development` for local dev)
- `composer install` must have been run in the plugin directory

---

## Installation

1. Upload the `wpworker` folder to `wp-content/plugins/`.
2. In the plugin directory, run:
   ```bash
   composer install
   npm install && npm run build
   ```
3. Go to **Plugins → Installed Plugins** in the WordPress admin and activate **WPWorker**.

After activation the **WPWorker** menu appears in the admin sidebar.

---

## 3-Step Setup (Configuration page)

Navigate to **WPWorker → Configuration**.

### Step 1 — Enable AI Abilities

Click **Enable AI Abilities**. This activates the MCP server and registers all abilities so agents can connect.

> **Security note.** When enabled, agents can execute PHP and perform filesystem operations. Use on development or staging only — not on live production sites without deliberate precautions. A red "WPWorker ON" badge will appear in the admin bar as a persistent reminder.

### Step 2 — Create an Application Password

Click **Generate application password**. The password is created and embedded into the connection text in Step 3 automatically.

If you prefer to manage passwords yourself, go to **Users → Profile → Application Passwords**, enter a name like `Claude Code`, and click **Add New Application Password**. The password is shown only once — copy it before leaving the page.

**Tips:**
- Create one password per AI client (Claude Desktop, Claude Code, Cursor, etc.) so you can revoke them individually.
- To revoke, scroll to the password table at the bottom of Step 2 and click **Revoke**.

### Step 3 — Connect Your AI Client

After generating a password, Step 3 reveals the **connection prompt**. Copy it and paste it directly into your AI agent. The agent will automatically write the correct MCP config for your client.

**Supported clients out of the box:** Claude Code, Claude Desktop, Codex, Cursor, VS Code, GitHub Copilot, Windsurf, Cline, Gemini CLI, Roo Code, Amazon Q, Zed, Kilo Code, OpenCode, Antigravity.

If the prompt-based setup does not work for your client, click **Need the JSON config for a specific client?** to see the raw JSON snippet for each client's config file.

**npx-free option:** If Node/npx is not available on your machine, click **Configs above not working?** for a direct HTTP transport snippet (Claude Code and Codex only).

---

## Verify the connection

After the agent writes and applies the config:

1. The agent will restart or reload the MCP session.
2. Ask it to list the available tools. You should see all `wpworker/*` abilities.
3. Run a quick test: ask the agent to call `wpworker/discover-abilities`. It should return a list of abilities plus environment info for your site.

---

## Turn abilities off

- From the **Configuration** page: click **Disable AI Abilities**.
- From the admin bar: click **WPWorker ON → Turn off AI Abilities**.

Disabling abilities unregisters all MCP tools so agents can no longer execute code on the site, even if they still have a valid application password.

---

## What's next

| Guide | What it covers |
|---|---|
| [Abilities Reference](./01-abilities.md) | Every `wpworker/*` ability, its inputs, and usage examples |
| [Skills](./02-skills.md) | Writing and managing AI playbooks |
| [Sandbox](./03-sandbox.md) | Persisting PHP code across requests |
| [Gutenberg / Block Editor](./04-gutenberg.md) | Writing Gutenberg content via the Block Editor Queue |
| [Ability Settings](./05-ability-settings.md) | Enabling or disabling individual abilities |

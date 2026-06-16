# WPWorker User Guide

WPWorker gives AI agents full control over a WordPress site through the MCP protocol.

## Guides

1. [Get Started](./00-get-started.md) — Install the plugin, enable abilities, generate a password, connect your AI client
2. [Abilities Reference](./01-abilities.md) — Every `wpworker/*` tool, its inputs, and usage notes
3. [Skills](./02-skills.md) — Write and manage AI playbooks stored in the database
4. [Sandbox](./03-sandbox.md) — Persist PHP code across requests using the sandbox directory
5. [Gutenberg / Block Editor](./04-gutenberg.md) — Write Gutenberg block content via the browser-based finalizer
6. [Ability Settings](./05-ability-settings.md) — Enable or disable individual abilities

## Quick reference

| Task | Guide |
|---|---|
| First-time setup | [Get Started](./00-get-started.md) |
| Connect Claude Code / Cursor / Codex | [Get Started → Step 3](./00-get-started.md#step-3--connect-your-ai-client) |
| Execute PHP on the server | [Abilities → php-execute](./01-abilities.md#wpworkerphp-execute) |
| Write persistent code | [Sandbox](./03-sandbox.md) |
| Give the agent site-specific instructions | [Skills](./02-skills.md) |
| Update page content with Gutenberg blocks | [Gutenberg](./04-gutenberg.md) |
| Block the agent from calling a specific tool | [Ability Settings](./05-ability-settings.md) |
| Revoke an AI client's access | [Get Started → Step 2](./00-get-started.md#step-2--create-an-application-password) |

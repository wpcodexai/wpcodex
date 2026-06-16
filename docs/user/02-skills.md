# Skills

Skills are Markdown playbooks stored in the WordPress database. They give AI agents standing instructions — conventions, patterns, and knowledge specific to your site — without repeating them in every conversation.

---

## How skills work

When an agent calls `wpworker/discover-abilities`, the response includes a **skill catalog**: the names and descriptions of all skills with `enable_agentic` set to true. The agent then calls `wpworker/skill-read` for any skill relevant to the current task.

Skills can also be exposed as **MCP prompt resources** (for agents that support them) when `enable_prompt` is true.

---

## Managing skills in the admin

Go to **WPWorker → Skills**.

### Create a skill

Click **Add New Skill** and fill in:

| Field | Description |
|---|---|
| **Name** | A slug-style identifier, e.g. `elementor-header-patterns`. Used by the agent to read the skill. |
| **Description** | One sentence that tells the agent *when* to load this skill. Write it so the agent can decide from the catalog alone. |
| **Body** | The full Markdown instructions. |
| **Enable for agents** | Toggle on to include in the `discover-abilities` catalog. |
| **Enable as prompt** | Toggle on to expose as an MCP prompt resource. |

### Edit and delete

Click the skill name to edit it. Each save creates a revision automatically — see [Revisions](#revisions) below.

Click **Delete** to remove a skill permanently.

### Revisions

Every time you save a skill, a snapshot is stored. To browse or restore:

1. Open the skill for editing.
2. Click **Revision history** to see all saved versions with timestamps.
3. Click **Restore** next to any revision to roll back.

---

## Managing skills via the agent

Agents can create, update, and delete skills directly using the skills abilities.

### Create

```
wpworker/skill-create
  name:           "elementor-header-patterns"
  description:    "Elementor header template IDs and container conventions. Load when modifying the header."
  body:           "# Elementor header patterns\n\nHeader template ID: 42\n..."
  enable_agentic: true
  enable_prompt:  false
```

### Read

```
wpworker/skill-read
  name: "elementor-header-patterns"
```

### Update

```
wpworker/skill-update
  name:        "elementor-header-patterns"
  description: "Updated description."
```

### Delete

```
wpworker/skill-delete
  name: "elementor-header-patterns"
```

### List revisions / restore

```
wpworker/skill-list-revisions
  name: "elementor-header-patterns"

wpworker/skill-restore-revision
  name:        "elementor-header-patterns"
  revision_id: 7
```

---

## Skill body format

Skills use a YAML frontmatter + Markdown format:

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

The frontmatter is optional when creating via the Skills admin page — those fields are set by the form. It is required when the agent creates or updates a skill via the API.

---

## When to create a skill

Create a skill after completing any complex task where you want the agent to remember what it learned:

- Template IDs, post IDs, or custom field keys specific to this site
- Naming conventions or code patterns for your theme or plugins
- Multi-step workflows the agent should follow (e.g. how to deploy, how to clear caches)
- Recurring instructions you would otherwise type at the start of every conversation

**Example prompt to your agent:**
> "Create a skill documenting what you learned about the ACF field groups on this site. Set enable_agentic to true."

---

## External skills (plugin-provided)

Other WordPress plugins can register their own skill sources via the `wpworker_skill_sources` filter. These appear in the skill catalog with a source badge (e.g. `[My Plugin]`). External skills can be read by the agent but cannot be overwritten via `wpworker/skill-create` — they are managed by the plugin that provides them.

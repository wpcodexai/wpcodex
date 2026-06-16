---
name: skill-creator
description: Guidance for creating and refining Worker AI skills — Markdown playbooks stored in WordPress that give the agent specialist knowledge for recurring tasks. Use when the user asks to "create a skill", "make a skill", "add a skill for X", or wants to extend the agent with reusable WordPress-specific knowledge.
enable_prompt: true
enable_agentic: true
---

# Skill Creator

Guidance for creating effective Worker AI skills.

## What a Worker AI Skill Is

A Worker AI skill is a single Markdown document — YAML frontmatter plus a body — stored in the WordPress database. When its `description` matches the user's request, the agent loads the body and gains specialised procedural knowledge for the task.

Skills are flat: no bundled scripts, references, or asset directories. Everything must live in the single body. Hard limit: 1 MB; aim for under 5 000 words.

### Anatomy

```
---
name: <slug>
description: <one-line trigger — the only field the agent reads to decide whether to load the skill>
enable_prompt: true|false   # Expose as an MCP prompt? Default true.
enable_agentic: true|false  # Include in the catalog the agent sees? Default true.
---

<Markdown body — instructions, examples, templates.>
```

## Core Principles

### Concise is key

The body loads into the context window every time the skill fires. Challenge every paragraph: "Does the agent really need this?" If the answer is "probably," cut it.

### Description is the trigger

Write it so a stranger can tell both *what the skill does* and *when to invoke it*. Include concrete phrases the user is likely to say.

- Bad: `"Helps with posts."`
- Good: `"Bulk-rewrite WordPress post excerpts in the Acme brand voice. Use when the user asks to revise excerpts, fix on-brand voice, or generate missing excerpts."`

### Match instruction specificity to task fragility

- High freedom → prose guidance.
- Medium freedom → pseudocode or annotated examples.
- Low freedom (fragile/destructive) → literal commands + "do not deviate" wording.

## Creating a Skill

Use `wpworker/skill-create` to create and `wpworker/skill-update` to patch fields.

### Workflow

1. Ask the user for 1–3 concrete examples of requests this skill should handle.
2. Identify what belongs in the body: business rules, naming conventions, content style, schema quirks, preferred ordering of fragile operations, specific `wpworker/*` abilities to call.
3. Call `wpworker/skill-create` with `name`, `description`, and `body`.
4. Verify with `wpworker/skill-read`.
5. After the user tries the skill, patch only the changed fields with `wpworker/skill-update`.

## What to Put in the Body

**Include:**
- Domain-specific knowledge the agent cannot derive from inspecting the site.
- Step-by-step procedures for fragile or multi-step operations.
- Concrete input → expected output examples.
- Templates to reuse verbatim.
- Specific `wpworker/*` abilities to call for this task.

**Exclude:**
- Generic WordPress tutorials — the agent already knows these.
- Meta sections like "When to Use This Skill" — the description did that job.
- Changelogs, author notes, installation instructions.
- Long preambles restating what the skill is about.

## Iteration

After the user tries the skill, ask: "What did the agent do wrong, miss, or over-explain?" Patch only the changed fields. If the same failure recurs, add a concrete counter-example rather than just restating the rule.

---
applyTo: 'wp-content\plugins\wpworker',
title: 'Copilot / Agent Instructions Template',
scope: 'repository',
created: 2026-06-05
---

# Purpose

This file is a template and guide to author a project-specific `instructions.md` that the `agent-customization` skill will load. Use it to capture persistent preferences, safety rules, coding patterns, and repository conventions the agent must follow.

# When to create an instructions file

- You want the agent to follow consistent, project-specific rules (naming, security, testing, publishing).
- You want the agent to avoid repeating manual corrections across sessions.

# What to include (recommended sections)

- **Scope**: Where the rules apply (entire repo, `includes/`, admin UI, build scripts).
- **Hard Rules**: Non-negotiable constraints (never expose API keys, require `declare(strict_types=1);`, capability checks for admin actions).
- **Preferences**: Preferred styles (1-line summaries, wording, variable naming, indentation, BEM class prefixes).
- **Security & Safety**: Things that require explicit confirmation (DB migrations, destructive file writes, arbitrary PHP execution).
- **Testing & Linting**: Required commands to run before commit (`composer lint`, `composer test`, `npm run build`).
- **Examples**: Small before/after examples for common transformations the agent will make.

# Drafting process (how to extract rules)

1. Review recent conversation or PR comments for repeated corrections or explicit preferences.
2. Extract bullet-sized rules (one idea per bullet). If none found, write a short checklist of defaults to confirm with maintainers.
3. Mark rules as `Hard rule` or `Preference`.
4. Add example snippets when a rule might be ambiguous.

# Clarifying questions (ask the maintainers)

- Should this apply repository-wide or only to `includes/` PHP files?
- Which formatting tools are authoritative (PHPCS, php-cs-fixer)?
- Are there operations that require manual approval (DB writes, remote deploys)?

# Iteration and verification

- After drafting, load the instruction in the `agent-customization` skill and run a small test prompt (e.g., "Create a new ability file following rules").
- Update the file when conventions change; prefer small diffs and explicit versioning in the header.

# Minimal example (paste into a new `instructions.md` when ready)

---
name: copilot-instructions
description: Project-wide agent instructions: PHP strict types, no secrets in source, run linters before commit.
---

1. Always include `declare(strict_types=1);` at the top of new PHP files. (Hard rule)
2. Do not commit secrets or API keys; read from options/constants and fail with a clear error if missing. (Hard rule)
3. Run `composer lint` and `composer test` before creating a PR. (Preference)
4. For destructive operations (DB migrations, arbitrary PHP exec), ask for explicit confirmation in the conversation. (Hard rule)
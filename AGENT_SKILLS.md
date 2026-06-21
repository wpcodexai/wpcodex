# AllyWorker — Agent Skills

> The Skills system gives your AI agent a persistent, site-specific knowledge base — naming conventions, builder patterns, custom post type schemas, step-by-step workflows. Skills fire automatically when the task matches, or on explicit invocation from the AI client.

---

## What Is a Skill?

A skill is a Markdown document stored in the **WordPress database**, managed from **AllyWorker → Skills** in the admin. It has a small YAML frontmatter block at the top and a plain Markdown body below.

The agent reads only each skill's `description` field at the start of a session — the full body stays dormant. When your prompt matches a description, the agent loads that body into context and follows it. This keeps costs low: descriptions are cheap to scan, bodies are loaded only when needed.

**Without skills:** The agent re-explores your install every session, makes assumptions, produces inconsistent output.

**With skills:** The agent knows your conventions before it writes a single line. No re-explaining. No surprises.

Skills are stored in WordPress and are **site-wide**: every AI client connected to this site reads the same skill set. Author once, every admin's AI follows them.

---

## Skill Format

Every skill is one document — YAML frontmatter on top, Markdown body below:

```markdown
---
name: page-naming-conventions
description: How to name new pages on this site (titles, slugs, parent pages). Use whenever you create or rename a page.
enable_agentic: true
enable_prompt: false
---

# Page naming conventions

Titles: sentence case. No trailing site name (the theme appends it).
Slugs: lowercase, hyphen-separated. Drop articles (a, an, the).
Parent pages: services under /services/, case studies under /work/, everything else top-level.
SEO meta description: always present, 140–155 characters.
Featured image: warn before publish if missing.
```

### Frontmatter Fields

| Field | Required | Description |
|---|---|---|
| `name` | Yes | Unique slug used by the MCP abilities. Auto-derived from the title if omitted. |
| `description` | Yes | **The trigger.** The only field the agent reads to decide whether to fire the skill. Write it so a stranger can tell at a glance what the skill does and when to invoke it. |
| `enable_agentic` | No (default: `true`) | When `true`, the description goes into the agent's automatic catalog. The agent fires this skill on its own when the description matches. |
| `enable_prompt` | No (default: `true`) | When `true`, the skill appears as a named entry in the AI client's prompt menu for explicit invocation. |

### Two Modes

**Agentic** (`enable_agentic: true`) — The skill fires automatically. You never invoke it. Use for conventions that should apply on every relevant task: naming rules, tone guidelines, security constraints.

**Prompt** (`enable_prompt: true`) — You invoke it explicitly from the AI client's slash-command or prompt menu. Use for deliberate workflows: a weekly audit, a bulk update procedure, a one-time migration.

**Both** (default) — Works either way. A sensible default when you're unsure.

---

## Skill Body Format

The body is plain Markdown. There is no required heading structure — write what the agent needs to follow. Some useful patterns:

```markdown
---
name: woocommerce-product-conventions
description: WooCommerce product structure, pricing rules, and stock management for this site. Use whenever you create, edit, or bulk-update products.
enable_agentic: true
enable_prompt: true
---

# WooCommerce product conventions

## Products
- Type: Simple Product unless explicitly stated otherwise.
- SKU format: `[CATEGORY-CODE]-[PRODUCT-CODE]-[VARIANT]`. Example: `SVC-CONSULT-PRO`.
- Images: min 1200×1200px. Always set both the featured image and the product gallery.
- Short description: 1–2 sentences, plain text, no HTML.

## Pricing
- Never update prices directly in the database. Always via `update_post_meta` with `_price`, `_regular_price`, `_sale_price`.
- Sale prices must include `_sale_price_dates_from` and `_sale_price_dates_to`.

## Orders
- Never set order status to `completed` manually — it triggers fulfilment automation.
- Custom statuses: `wc-awaiting-approval` (quotes), `wc-partial-payment`.

## Reference
List product categories: `wp term list product_cat --fields=term_id,name`
```

**Keep skills focused.** One skill, one concern. Do not put WooCommerce rules in an Elementor skill.

**Keep skills short.** Every word in the body competes with your prompt for the agent's context. Most skills fit in 200–800 words. Hard limit: 1 MB (don't get close).

**Include the why.** "Never publish directly — it triggers the client notification email" prevents silent mistakes that a rule alone would not.

**Use real values.** "Main nav menu ID: 42" beats "there is a main navigation menu."

---

## Built-in Skill Examples

### Agentic only: naming conventions

Fires silently whenever the AI creates or renames anything. You never invoke it.

```markdown
---
name: naming-conventions
description: Slug, CSS class, option name, and transient naming rules for this project. Use whenever you create or rename any WordPress object.
enable_agentic: true
enable_prompt: false
---

# Naming conventions

## Slugs
- Lowercase, hyphen-separated. No underscores. Example: `our-services`, not `our_services`.
- Page slugs reflect the full URL path: `/services/consulting` → slug `consulting`, parent `services`.
- Blog post slugs include the year: `2026-launch-announcement`.

## CSS classes
- BEM methodology: `.block__element--modifier`.
- Custom prefix: `wpx-`. Example: `.wpx-hero__title`.
- Never add inline styles via the builder — always use classes.

## WordPress identifiers
- Custom post type names: singular, lowercase, prefixed. Example: `wpx_service`.
- Option names: prefixed with `allyworker_`. Example: `allyworker_footer_disclaimer`.
- Transient keys: prefixed with `allyworker_transient_`.
```

---

### Prompt only: weekly site audit

Runs only when you invoke it from the AI client's prompt menu.

```markdown
---
name: weekly-site-audit
description: Walk through a weekly site check — broken internal links, missing alt text, slow pages, recent security notices.
enable_prompt: true
enable_agentic: false
---

# Weekly site audit

Run these checks in order, reporting findings as you go.

1. Broken internal links: scan published `post_content` for hrefs to this domain, HEAD-check each one.
2. Missing alt text: list attachments used in published posts with empty alt.
3. Slow pages: top 10 slowest URLs from any available logs or transient cache data.
4. Recent security notices in WordPress core, plugins, and themes.
```

---

### Both modes: creating a new page

The agent fires it automatically when the task matches and you can also invoke it explicitly.

```markdown
---
name: new-page-workflow
description: Step-by-step procedure for creating a new page on this site. Use whenever you create a page.
enable_agentic: true
enable_prompt: true
---

# Creating a new page

## Before you start
- Ask for the page title, slug, and parent page (if any).
- Validate the slug against the naming-conventions skill.
- Check the slug is not already in use: `wp post list --post_type=page --post_status=any --field=post_name | grep "{slug}"`

## Create the post record
```php
$id = wp_insert_post( [
    'post_type'   => 'page',
    'post_title'  => '[PAGE TITLE]',
    'post_name'   => '[slug]',
    'post_parent' => [PARENT_ID or 0],
    'post_status' => 'draft',
] );
```

## Set the page template
- Default: `default` (full-width layout).
- Landing pages: `template-landing.php`.
```php
update_post_meta( $id, '_wp_page_template', '[template]' );
```

## After creating
- Report the new post ID and admin edit URL.
- Tell the user: "Page `[slug]` created as draft (ID: {id}). Open it in [BUILDER] to add content."

## Rules
- Always `draft` — never `publish` directly.
- Always confirm the parent hierarchy before inserting.
```

---

## How the Agent Uses Skills

### At session start

The agent calls `allyworker/skill-list` to retrieve all skill names and descriptions. Bodies stay in the database — not loaded yet.

```
allyworker/skill-list
→ [
    { "name": "naming-conventions",      "description": "Slug, CSS class...",     "enable_agentic": true  },
    { "name": "woocommerce-conventions", "description": "WooCommerce product...", "enable_agentic": true  },
    { "name": "weekly-site-audit",       "description": "Walk through a weekly..","enable_agentic": false }
  ]
```

### When a task matches

When your prompt matches a description, the agent calls `allyworker/skill-read` to load the full body:

```
User:  "Create a new page for our services"
Agent: allyworker/skill-read  name: "naming-conventions"
       allyworker/skill-read  name: "new-page-workflow"
       → follows both skill bodies

User:  "Add a WooCommerce product"
Agent: allyworker/skill-read  name: "woocommerce-conventions"
       → follows the product conventions
```

---

## Managing Skills

### From the admin UI

Go to **AllyWorker → Skills** to create, edit, enable/disable, and delete skills through a built-in editor. Upload a `.md` file with frontmatter or write inline.

Disable a skill (toggle off) to hide it from the agent without deleting the body. Useful when iterating on a skill that is misbehaving.

### Via MCP abilities

The agent can create and manage skills directly using the dedicated skill abilities:

| Ability | What it does |
|---|---|
| `allyworker/skill-list` | Returns all skill names and descriptions |
| `allyworker/skill-read` | Returns the full body of a skill by name |
| `allyworker/skill-create` | Creates a new skill (name, description, body, flags) |
| `allyworker/skill-update` | Updates an existing skill's body or frontmatter |
| `allyworker/skill-delete` | Deletes a skill by name |

**To have the agent write a new skill from what it just learned:**

> "You've rebuilt our Elementor header template. Write a skill that captures the template IDs, container patterns, and global token names you used."

The agent calls `allyworker/skill-create` with a properly formatted frontmatter + body and the skill is immediately available to all connected clients.

---

## Writing Good Skills

1. **The description is the trigger.** Write it so a stranger can tell what the skill does and when to invoke it. Include the phrases your team actually uses. Bad: "Helps with posts." Better: "Rewrite WordPress post excerpts in the Acme brand voice — concise, second-person, action-oriented. Use when revising excerpts, fixing tone, or generating missing ones."

2. **Be specific.** "Pages use `template-full-width.php`" beats "pages can have templates."

3. **Include the why.** "Never publish directly — it triggers the client notification email" prevents mistakes a rule alone would not.

4. **Use real values.** "Main nav menu ID: 42" beats "there is a main menu."

5. **One skill, one concern.** Don't mix WooCommerce rules into the Elementor skill.

6. **Keep it short.** Most skills fit in 200–800 words. Cut anything the agent can infer by inspecting the site.

7. **Match specificity to risk.** For destructive or irreversible actions, spell out exact steps with explicit "do not deviate" wording. For tasks with valid alternatives, write prose guidance instead.

8. **Skills are flat.** Everything the agent needs lives in the single body. No companion files, no references directory, no bundled scripts.

9. **Let the agent write them.** After a complex task, ask the agent to document what it learned. It calls `allyworker/skill-create` and the skill is live immediately.

10. **Keep them current.** Skills go stale. After a major rebuild or plugin upgrade, review and update the relevant skills.

---

## Pro Skill Bundles

AllyWorker Pro ships pre-built, maintained skill bundles for major builders and plugins. Pro bundles combine specialised MCP abilities (real PHP tools that act on those integrations directly) with expert-authored skill bodies — updated with each plugin release.

| Bundle | What the agent gets |
|---|---|
| Elementor Pro | Widget reference, dynamic tags, global kit schema, loop builder patterns |
| Bricks Builder | Element library, query loops, global styles, dynamic data |
| ACF Pro | All field types, flexible content, options pages, block fields |
| WooCommerce | HPOS compatibility, variable products, order hooks, REST API |
| Divi | Module system, global presets, layout pack conventions, Theme Builder |
| Kadence Blocks | Block patterns, global palette, reusable templates, header builder |
| Gravity Forms | All field types, conditional logic, webhooks, payment integrations |
| WPML | Translation workflow, language switcher, string translation, ACF WPML |
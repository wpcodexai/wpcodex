# Gap Analysis: novamira vs wpcodex

Comprehensive function-by-function audit comparing the novamira reference implementation against wpcodex.

---

## Bugs Fixed (this session)

### 1. `DiscoverAbilities::build_instructions()` — sparse environment context

**File:** `includes/Abilities/Core/DiscoverAbilities.php`

**Before:** Only returned site URL, sandbox path, a short abilities list, and a 3-line rules section.

**After (fixed):** Now matches `novamira_build_server_instructions()` — includes:
- WordPress + PHP version, locale
- Multilingual plugin detection (WPML, Polylang, TranslatePress) via new `get_active_languages()`
- Full installed plugins inventory with active/inactive status via `get_plugins()` + `is_plugin_active()`
- WordPress-native development guidelines (CPT, taxonomies, post meta, Options API, plugin-ownership rules)
- Active theme + building context via new `build_building_context_lines()` (warns against mixing page builders with Gutenberg)

### 2. `Helpers::ability_permission()` — no multisite support

**File:** `includes/Utils/Helpers.php`

**Before:** Always checked `current_user_can('manage_options')`, which only checks the current subsite on multisite.

**After (fixed):** Uses `is_multisite() ? is_super_admin() : current_user_can('manage_options')` — matches novamira's `novamira_current_user_can_manage()`.

---

## Intentional Architecture Divergences

These differences between novamira and wpcodex are **by design** — wpcodex made a deliberate architectural choice in each case.

### Skills storage: DB table vs CPT

| novamira | wpcodex |
|---|---|
| `novamira_skill` custom post type (`includes/skills/cpt.php`) | Dedicated `wpcodex_skills` DB table (`includes/Skills/Schema.php` + `Repository.php`) |
| `'content'` key | `'body'` key |
| No revision history | Full revision history via `wpcodex_skill_revisions` table + `SkillListRevisions` / `SkillRestoreRevision` abilities |

wpcodex's DB table approach adds proper revision/restore support that novamira lacks.

### `gutenberg-write-content` ability

| novamira | wpcodex |
|---|---|
| Direct `wp_update_post()` server-side write — only accepts novamira-owned dynamic-only blocks (`save: null`) | Always uses the batch queue + JS finalizer, even for single blocks |
| Calls `serialize_dynamic_blocks()` + `spec_to_parsed_block()` locally | No server-side block serializer needed — JS finalizer in the browser handles serialization |
| Returns `written: true` immediately | Returns `batch_id`, `item_id`, SSE/poll URLs, `finalization_url` |

wpcodex's `WriteContent` is a convenience batch-wrapper, not a direct write path. The `serialize_dynamic_blocks()` and `spec_to_parsed_block()` functions in novamira's `bootstrap.php` have **no equivalent needed** in wpcodex because serialization happens client-side.

### Ability management: AbilityPolicy vs ability rules

| novamira | wpcodex |
|---|---|
| `novamira_get_ability_rules()` / `novamira_update_ability_rules()` — per-ability enable/disable stored in a single option | `AbilityPolicy` class reads per-ability `wpcodex_ability_enabled_{slug}` options |
| Hub-protection flags: `novamira_ability_is_hub_protected()` | No hub-protection concept |
| Production/domain locking via `novamira_is_enabled()` — locks out non-whitelisted domains | No domain locking; use `AbilitiesSettingsPage` to disable abilities instead |

### Admin notices

| novamira | wpcodex |
|---|---|
| Push model: hooks into `admin_notices` directly | Pull model: `SkillsPage` calls `Notices::pending_reload_notice()` and renders inline |
| Per-user redirect notices via `novamira_skill_admin_notice_{user_id}` transient | No per-user redirect notices (CRUD feedback is handled inline in `SkillsPage` responses) |
| Reload notice TTL: 60 seconds | Reload notice TTL: 5 minutes |

### `SandboxLoader::collect_files()` — excludes `index.php`

wpcodex explicitly excludes `index.php` from sandbox loading (the directory stub file). novamira does not exclude it, but the file content is harmless. wpcodex's behaviour is slightly more correct.

### Sources: slug normalization

Both `Sources::find()` and `Sources::exists_in_external_source()` in wpcodex normalize slugs via `Parser::normalize_slug()` before comparison. novamira does an exact string match. wpcodex is more robust for agent-supplied skill names.

---

## Files in novamira Not in wpcodex (by design)

| novamira file | Reason absent in wpcodex |
|---|---|
| `includes/updater.php` | wpcodex uses its own update mechanism |
| `includes/pro-upsell.php` | No pro tier in wpcodex |
| `includes/skills/cpt.php` | wpcodex uses DB table instead of CPT |
| `includes/skills/templates/*.php` | wpcodex handles skill CRUD in `SkillsPage.php` |
| `helpers.php` — production detection, domain locking | Replaced by `AbilityPolicy` disable/enable flow |
| `helpers.php` — `novamira_app_passwords_status()` | Not surfaced in wpcodex admin UI |
| `bootstrap.php` — `serialize_dynamic_blocks()` / `spec_to_parsed_block()` | Not needed — wpcodex uses JS finalizer, not server-side block serialization |

## Files in wpcodex Not in novamira

| wpcodex file | Purpose |
|---|---|
| `includes/Admin/AbilityPolicy.php` | Per-ability disable/enable (replaces novamira's ability rules + domain locking) |
| `includes/Admin/AbilitiesSettingsPage.php` | UI for ability policy |
| `includes/Admin/ConfigurationPage.php` | Site configuration page |
| `includes/Admin/SandboxPage.php` | Sandbox management UI |
| `includes/Skills/Schema.php` | DB schema management for skills + revisions tables |
| `includes/Skills/Repository.php` | DB-based skill CRUD with revision support |
| `includes/Abilities/Skills/SkillListRevisions.php` | List skill revision history |
| `includes/Abilities/Skills/SkillRestoreRevision.php` | Restore a skill to a prior revision |
| `includes/Utils/GutenbergHelpers.php` | `shape_blocks()` — compact agent-readable block tree |
| `includes/Tools/Mcp.php` | MCP server configuration |

---

## Minor Differences (no fix required)

| Location | novamira | wpcodex | Assessment |
|---|---|---|---|
| `Parser::unescape_content()` | `unescape_content()` | `unescape()` | Equivalent logic; different name only |
| `Parser::render_skill_md()` | Uses `$skill['slug']` for `name:` frontmatter | Uses `$skill['name']` | Both correct for their storage model |
| `Sources::discoverable()` | Checks `$skill['content']` non-empty | Checks `$skill['body']` non-empty | Consistent with storage key difference |
| `DiscoverAbilities` output key | `novamira_instructions` | `instructions` | Intentional rename |
| `Prompts::register()` hook priority | Default 10 | Priority 500 | wpcodex ensures skills load after abilities |

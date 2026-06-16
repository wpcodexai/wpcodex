# Rename Guide: WPCodex → Worker AI

Complete reference for renaming the plugin from `wpcodex` to `worker-ai` / `wpworker`. 

---

## Fixed Values

| Item | Old | New |
|---|---|---|
| Plugin name | `WPCodex` | `Worker AI` |
| WP.org slug | `wpcodex` | `worker-ai` |
| Folder name | `wpcodex/` | `worker-ai/` |
| Main PHP file | `wpcodex.php` | `worker-ai.php` |
| Text domain | `wpcodex` | `worker-ai` |
| Website | — | `https://wpworker.ai` |
| PHP namespace | `WPCodex\` | `WPWorker\` |
| Internal prefix | `wpcodex_` | `wpworker_` |

---

## Plugin Header (worker-ai.php)

```php
/**
 * Plugin Name:       Worker AI
 * Plugin URI:        https://wpworker.ai
 * Description:       Connect AI agents to your WordPress site via MCP.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Aminul Islam
 * Author URI:        https://wpworker.ai
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       worker-ai
 * Domain Path:       /languages
 */
```

---

## Folder Structure

```
worker-ai/                          ← matches WP.org slug exactly
├── worker-ai.php                   ← matches slug exactly
├── composer.json
├── package.json
├── readme.txt
├── README.md
├── includes/
│   └── (namespace WPWorker\)
├── src/
├── assets/
├── languages/
│   ├── worker-ai.pot               ← matches text domain
│   ├── worker-ai-en_US.po
│   └── worker-ai-en_US.mo
└── vendor/
```

---

## 1. PHP Namespace

```php
// Before
namespace WPCodex\Abilities;
use WPCodex\Runner\PhpRunner;

// After
namespace WPWorker\Abilities;
use WPWorker\Runner\PhpRunner;
```

---

## 2. Constants

```php
// Before
WPCODEX_FILE
WPCODEX_VERSION
WPCODEX_DIR
WPCODEX_URL
WPCODEX_BASENAME
WPCODEX_SANDBOX_DIR

// After
WPWORKER_FILE
WPWORKER_VERSION
WPWORKER_DIR
WPWORKER_URL
WPWORKER_BASENAME        // returns: worker-ai/worker-ai.php
WPWORKER_SANDBOX_DIR     // wp-content/wpworker-sandbox/
```

---

## 3. Enable MCP Constant (wp-config.php)

```php
// Before
define( 'WP_CODEX_ENABLE_MCP', true );

// After
define( 'WP_WORKER_ENABLE_MCP', true );
```

---

## 4. Text Domain — Critical

Text domain is `worker-ai` (hyphen, not underscore) in **every** translation call:

```php
// ✅ Correct
__( 'Label', 'worker-ai' )
esc_html__( 'Label', 'worker-ai' )
_e( 'Label', 'worker-ai' )

// ❌ Wrong — translations will never load
__( 'Label', 'wpworker' )
__( 'Label', 'wpcodex' )
```

---

## 5. WordPress Options

```php
// Before
get_option( 'wpcodex_setting_name' );
update_option( 'wpcodex_setting_name', $value );

// After
get_option( 'wpworker_setting_name' );
update_option( 'wpworker_setting_name', $value );
```

---

## 6. Hooks & Filters

```php
// Before
do_action( 'wpcodex_after_activate' );
add_filter( 'wp_codex_abilities', $callback );
apply_filters( 'wp_codex_abilities', $abilities );

// After
do_action( 'wpworker_after_activate' );
add_filter( 'wpworker_abilities', $callback );
apply_filters( 'wpworker_abilities', $abilities );
```

---

## 7. Transients

```php
// Before
set_transient( 'wpcodex_transient_key', $value );
get_transient( 'wpcodex_transient_key' );

// After
set_transient( 'wpworker_transient_key', $value );
get_transient( 'wpworker_transient_key' );
```

---

## 8. Ability Names

```php
// Before
'wpcodex/file-read'
'wpcodex/site-info'
'wpcodex/skill-list'
'wpcodex/php-execute'
'wpcodex/astra-get-settings'

// After
'wpworker/file-read'
'wpworker/site-info'
'wpworker/skill-list'
'wpworker/php-execute'
'wpworker/astra-get-settings'
```

---

## 9. Ability Categories

```php
// Before
wp_register_ability_category( 'wpcodex', [...] );
wp_register_ability_category( 'wpcodex-skills', [...] );
wp_register_ability_category( 'wpcodex-gutenberg', [...] );
wp_register_ability_category( 'wpcodex-general', [...] );
wp_register_ability_category( 'wpcodex-site', [...] );
wp_register_ability_category( 'wpcodex-themes', [...] );
wp_register_ability_category( 'wpcodex-astra', [...] );

// After
wp_register_ability_category( 'wpworker', [...] );
wp_register_ability_category( 'wpworker-skills', [...] );
wp_register_ability_category( 'wpworker-gutenberg', [...] );
wp_register_ability_category( 'wpworker-general', [...] );
wp_register_ability_category( 'wpworker-site', [...] );
wp_register_ability_category( 'wpworker-themes', [...] );
wp_register_ability_category( 'wpworker-astra', [...] );
```

---

## 10. MCP Server Config

```php
// Before (Mcp.php)
$config['server_id']    = 'wpcodex';
$config['server_route'] = 'wpcodex';
$config['server_name']  = 'WPCodex';

// After
$config['server_id']    = 'wpworker';
$config['server_route'] = 'wpworker';
$config['server_name']  = 'Worker AI';

// MCP URL changes:
// Before: https://yoursite.com/wp-json/wpcodex/mcp
// After:  https://yoursite.com/wp-json/wpworker/mcp
```

> **Note:** Any AI client already connected with the old MCP URL must update their config.

---

## 11. Database Table

```php
// Before
$wpdb->prefix . 'wpcodex_skills'
// Full: wp_wpcodex_skills

// After
$wpdb->prefix . 'wpworker_skills'
// Full: wp_wpworker_skills
```

> **Migration required** if upgrading an existing install — add a `maybe_rename_table()` call in `Schema::maybe_upgrade()`.

---

## 12. Sandbox Directory

```
// Before
wp-content/wpcodex-sandbox/

// After
wp-content/wpworker-sandbox/
```

---

## 13. JS & CSS Handles

```php
// Before
wp_enqueue_script( 'wpcodex-admin', ... );
wp_enqueue_style( 'wpcodex-admin', ... );
wp_localize_script( 'wpcodex-admin', 'wpcodexData', [ ... ] );

// After
wp_enqueue_script( 'wpworker-admin', ... );
wp_enqueue_style( 'wpworker-admin', ... );
wp_localize_script( 'wpworker-admin', 'wpworkerData', [ ... ] );
```

---

## 14. Admin Screen ID

```php
// Before (depends on menu slug)
'toplevel_page_wpcodex'

// After
'toplevel_page_wpworker'
```

---

## 15. Activation & Deactivation Hooks

```php
// Before
register_activation_hook( WPCODEX_FILE, [ \WPCodex\Plugin::class, 'activate' ] );
register_deactivation_hook( WPCODEX_FILE, [ \WPCodex\Plugin::class, 'deactivate' ] );

// After
register_activation_hook( WPWORKER_FILE, [ \WPWorker\Plugin::class, 'activate' ] );
register_deactivation_hook( WPWORKER_FILE, [ \WPWorker\Plugin::class, 'deactivate' ] );
```

---

## 16. composer.json

```json
{
  "name": "wpworker/worker-ai",
  "autoload": {
    "psr-4": {
      "WPWorker\\": "includes/"
    }
  }
}
```

---

## 17. package.json

```json
{
  "name": "worker-ai",
  "version": "1.0.0"
}
```

---

## 18. Languages Folder

```
languages/
├── worker-ai.pot           ← matches text domain
├── worker-ai-en_US.po
└── worker-ai-en_US.mo
```

---

## 19. Pro Plugin (worker-ai-pro)

| Item | Old | New |
|---|---|---|
| Folder | `wpcodex-pro/` | `worker-ai-pro/` |
| Main file | `wpcodex-pro.php` | `worker-ai-pro.php` |
| Namespace | `WPCodexPro\` | `WPWorkerPro\` |
| Constants | `WPCODEX_PRO_*` | `WPWORKER_PRO_*` |
| Text domain | `wpcodex-pro` | `worker-ai-pro` |
| Options | `wpcodex_pro_*` | `wpworker_pro_*` |
| Hooks | `wpcodex_pro_*` | `wpworker_pro_*` |
| Ability names | `wpcodex-pro/*` | `wpworker-pro/*` |
| Filter | `wp_codex_abilities` | `wpworker_abilities` |
| Free dependency check | `wpcodex/wpcodex.php` | `worker-ai/worker-ai.php` |

---

## Find & Replace Order

Run across the entire codebase in this exact order to avoid partial replacements:

```
1.  WPCodexPro        →  WPWorkerPro
2.  WPCodex           →  WPWorker
3.  WPCODEX_PRO       →  WPWORKER_PRO
4.  WPCODEX           →  WPWORKER
5.  wpcodex-pro       →  worker-ai-pro
6.  wp_codex          →  wpworker
7.  wpcodex           →  wpworker
8.  WP Codex          →  Worker AI
9.  WPCodex (strings) →  Worker AI
10. text-domain: wpworker  →  text-domain: worker-ai
11. 'wpworker'        →  'worker-ai'   (in __() calls only)
```

> **Warning:** Step 11 must be surgical — only apply to translation function calls (`__()`, `_e()`, `esc_html__()`, etc.), not to option names, hook names, or prefixes.

---

## Complete Checklist

```
□ Folder renamed              worker-ai/
□ Main file renamed           worker-ai.php
□ Plugin Name header          Worker AI
□ Plugin URI header           https://wpworker.ai
□ Text Domain header          worker-ai
□ All __() calls              'worker-ai'
□ .pot/.po/.mo files          worker-ai.*
□ Constants                   WPWORKER_*
□ Enable MCP constant         WP_WORKER_ENABLE_MCP
□ PHP namespace               WPWorker\
□ composer.json               WPWorker\\ / worker-ai
□ package.json                worker-ai
□ Internal prefix             wpworker_
□ Options                     wpworker_*
□ Hooks / filters             wpworker_* / wpworker_*
□ Transients                  wpworker_transient_*
□ DB table                    wp_wpworker_skills
□ DB migration                maybe_rename_table() added
□ Sandbox directory           wpworker-sandbox/
□ MCP route / ID              wpworker
□ MCP server name             Worker AI
□ JS / CSS handles            wpworker-*
□ JS localized object         wpworkerData
□ Admin screen ID             toplevel_page_wpworker
□ Activation hooks            WPWORKER_FILE / WPWorker\Plugin
□ Ability names               wpworker/*
□ Ability categories          wpworker-*
□ Pro folder                  worker-ai-pro/
□ Pro main file               worker-ai-pro.php
□ Pro namespace               WPWorkerPro\
□ Pro constants               WPWORKER_PRO_*
□ Pro text domain             worker-ai-pro
□ Pro dependency check        worker-ai/worker-ai.php
□ Pro filter                  wpworker_abilities
□ CLAUDE.md updated           new prefixes documented
```

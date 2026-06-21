# Rename Guide: Worker AI → AllyWorker

Complete reference for renaming the plugin from `worker-ai` / `wpworker` to `allyworker`.

---

## Fixed Values

| Item | Old | New |
|---|---|---|
| Plugin name | `Worker AI` | `AllyWorker` |
| WP.org slug | `worker-ai` | `allyworker` |
| Folder name | `worker-ai/` | `allyworker/` |
| Main PHP file | `worker-ai.php` | `allyworker.php` |
| Text domain | `worker-ai` | `allyworker` |
| Website | `https://wpworker.ai` | `https://allyworker.com` |
| GitHub username | `wpworkerai` | `allyworker` |
| WP.org username | `wpworkerai` | `allyworker` |
| PHP namespace | `WPWorker\` | `AllyWorker\` |
| Internal prefix | `wpworker_` | `allyworker_` | 

---

## Plugin Header (allyworker.php)

```php
/**
 * Plugin Name:       AllyWorker
 * Plugin URI:        https://allyworker.com
 * Description:       Connect AI agents to your WordPress site via MCP.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            AllyWorker Team
 * Author URI:        https://allyworker.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       allyworker
 * Domain Path:       /languages
 */
```

---

## Folder Structure

```
allyworker/                         ← matches WP.org slug exactly
├── allyworker.php                  ← matches slug exactly
├── composer.json
├── package.json
├── readme.txt
├── README.md
├── includes/
│   └── (namespace AllyWorker\)
├── src/
├── assets/
├── languages/
│   ├── allyworker.pot              ← matches text domain
│   ├── allyworker-en_US.po
│   └── allyworker-en_US.mo
└── vendor/
```

---

## 1. PHP Namespace

```php
// Before
namespace WPWorker\Abilities;
use WPWorker\Runner\PhpRunner;

// After
namespace AllyWorker\Abilities;
use AllyWorker\Runner\PhpRunner;
```

---

## 2. Constants

```php
// Before
WPWORKER_FILE
WPWORKER_VERSION
WPWORKER_DIR
WPWORKER_URL
WPWORKER_BASENAME
WPWORKER_SANDBOX_DIR

// After
ALLY_WORKER_FILE
ALLY_WORKER_VERSION
ALLY_WORKER_DIR
ALLY_WORKER_URL
ALLY_WORKER_BASENAME        // returns: allyworker/allyworker.php
ALLY_WORKER_SANDBOX_DIR     // wp-content/wp-allyworker-sandbox/
```

---

## 3. Enable MCP Constant (wp-config.php)

```php
// Before
define( 'WP_WORKER_ENABLE_MCP', true );

// After
define( 'WP_ALLY_WORKER_ENABLE', true );
```

---

## 4. Text Domain — Critical

Text domain is `allyworker` in **every** translation call:

```php
// ✅ Correct
__( 'Label', 'allyworker' )
esc_html__( 'Label', 'allyworker' )
_e( 'Label', 'allyworker' )

// ❌ Wrong — translations will never load
__( 'Label', 'wpworker' )
__( 'Label', 'worker-ai' )
```

---

## 5. WordPress Options

```php
// Before
get_option( 'wpworker_setting_name' );
update_option( 'wpworker_setting_name', $value );

// After
get_option( 'allyworker_setting_name' );
update_option( 'allyworker_setting_name', $value );
```

---

## 6. Hooks & Filters

```php
// Before
do_action( 'wpworker_after_activate' );
add_filter( 'wp_worker_abilities', $callback );
apply_filters( 'wp_worker_abilities', $abilities );

// After
do_action( 'allyworker_after_activate' );
add_filter( 'allyworker_abilities', $callback );
apply_filters( 'allyworker_abilities', $abilities );
```

---

## 7. Transients

```php
// Before
set_transient( 'wpworker_transient_key', $value );
get_transient( 'wpworker_transient_key' );

// After
set_transient( 'allyworker_transient_key', $value );
get_transient( 'allyworker_transient_key' );
```

---

## 8. Ability Names

```php
// Before
'wpworker/file-read'
'wpworker/site-info'
'wpworker/skill-list'
'wpworker/php-execute'
'wpworker/astra-get-settings'

// After
'allyworker/file-read'
'allyworker/site-info'
'allyworker/skill-list'
'allyworker/php-execute'
'allyworker/astra-get-settings'
```

---

## 9. Ability Categories

```php
// Before
wp_register_ability_category( 'wpworker', [...] );
wp_register_ability_category( 'wpworker-skills', [...] );
wp_register_ability_category( 'wpworker-gutenberg', [...] );
wp_register_ability_category( 'wpworker-general', [...] );
wp_register_ability_category( 'wpworker-site', [...] );
wp_register_ability_category( 'wpworker-themes', [...] );
wp_register_ability_category( 'wpworker-astra', [...] );

// After
wp_register_ability_category( 'allyworker', [...] );
wp_register_ability_category( 'allyworker-skills', [...] );
wp_register_ability_category( 'allyworker-gutenberg', [...] );
wp_register_ability_category( 'allyworker-general', [...] );
wp_register_ability_category( 'allyworker-site', [...] );
wp_register_ability_category( 'allyworker-themes', [...] );
wp_register_ability_category( 'allyworker-astra', [...] );
```

---

## 10. MCP Server Config

```php
// Before (Mcp.php)
$config['server_id']    = 'wpworker';
$config['server_route'] = 'wpworker';
$config['server_name']  = 'Worker AI';

// After
$config['server_id']    = 'allyworker';
$config['server_route'] = 'allyworker';
$config['server_name']  = 'AllyWorker';

// MCP URL changes:
// Before: https://yoursite.com/wp-json/wpworker/mcp
// After:  https://yoursite.com/wp-json/allyworker/mcp
```

> **Note:** Any AI client already connected with the old MCP URL must update their config.

---

## 11. Database Table

```php
// Before
$wpdb->prefix . 'wpworker_skills'
// Full: wp_wpworker_skills

// After
$wpdb->prefix . 'allyworker_skills'
// Full: wp_allyworker_skills
```

> **Migration required** if upgrading an existing install — add a `maybe_rename_table()` call in `Schema::maybe_upgrade()`.

---

## 12. Sandbox Directory

```
// Before
wp-content/wpworker-sandbox/

// After
wp-content/wp-allyworker-sandbox/
```

---

## 13. JS & CSS Handles

```php
// Before
wp_enqueue_script( 'wpworker-admin', ... );
wp_enqueue_style( 'wpworker-admin', ... );
wp_localize_script( 'wpworker-admin', 'wpworkerData', [ ... ] );

// After
wp_enqueue_script( 'allyworker-admin', ... );
wp_enqueue_style( 'allyworker-admin', ... );
wp_localize_script( 'allyworker-admin', 'allyworkerData', [ ... ] );
```

---

## 14. Admin Screen ID

```php
// Before
'toplevel_page_wpworker'

// After
'toplevel_page_allyworker'
```

---

## 15. Activation & Deactivation Hooks

```php
// Before
register_activation_hook( WPWORKER_FILE, [ \WPWorker\Plugin::class, 'activate' ] );
register_deactivation_hook( WPWORKER_FILE, [ \WPWorker\Plugin::class, 'deactivate' ] );

// After
register_activation_hook( ALLY_WORKER_FILE, [ \AllyWorker\Plugin::class, 'activate' ] );
register_deactivation_hook( ALLY_WORKER_FILE, [ \AllyWorker\Plugin::class, 'deactivate' ] );
```

---

## 16. composer.json

```json
{
  "name": "allyworker/allyworker",
  "autoload": {
    "psr-4": {
      "AllyWorker\\": "includes/"
    }
  }
}
```

---

## 17. package.json

```json
{
  "name": "allyworker",
  "version": "1.0.0"
}
```

---

## 18. Languages Folder

```
languages/
├── allyworker.pot          ← matches text domain
├── allyworker-en_US.po
└── allyworker-en_US.mo
```

---

## 19. Pro Plugin (allyworker-pro)

| Item | Old | New |
|---|---|---|
| Folder | `worker-ai-pro/` | `allyworker-pro/` |
| Main file | `worker-ai-pro.php` | `allyworker-pro.php` |
| Namespace | `WPWorkerPro\` | `AllyWorkerPro\` |
| Constants | `WPWORKER_PRO_*` | `ALLY_WORKER_PRO_*` |
| Text domain | `worker-ai-pro` | `allyworker-pro` |
| Options | `wpworker_pro_*` | `allyworker_pro_*` |
| Hooks | `wpworker_pro_*` | `allyworker_pro_*` |
| Ability names | `wpworker-pro/*` | `allyworker-pro/*` |
| Filter | `wp_worker_abilities` | `allyworker_abilities` |
| Free dependency check | `worker-ai/worker-ai.php` | `allyworker/allyworker.php` |

---

## Find & Replace Order

Run across the entire codebase in this exact order to avoid partial replacements:

```
1.  WPWorkerPro        →  AllyWorkerPro
2.  WPWorker           →  AllyWorker
3.  WPWORKER_PRO       →  ALLY_WORKER_PRO
4.  WPWORKER           →  ALLY_WORKER
5.  worker-ai-pro      →  allyworker-pro
6.  worker-ai          →  allyworker
7.  wp_worker          →  allyworker
8.  wpworker           →  allyworker
9.  Worker AI          →  AllyWorker
```

> **Warning:** Step 9 must be surgical — only apply to display strings, not to option names, hook names, or prefixes.

---

## Complete Checklist

```
□ Folder renamed              allyworker/
□ Main file renamed           allyworker.php
□ Plugin Name header          AllyWorker
□ Plugin URI header           https://allyworker.com
□ Text Domain header          allyworker
□ All __() calls              'allyworker'
□ .pot/.po/.mo files          allyworker.*
□ Constants                   ALLY_WORKER_*
□ Enable MCP constant         WP_ALLY_WORKER_ENABLE
□ PHP namespace               AllyWorker\
□ composer.json               AllyWorker\\ / allyworker
□ package.json                allyworker
□ Internal prefix             allyworker_
□ Options                     allyworker_*
□ Hooks / filters             allyworker_*
□ Transients                  allyworker_transient_*
□ DB table                    wp_allyworker_skills
□ DB migration                maybe_rename_table() added
□ Sandbox directory           wp-allyworker-sandbox/
□ MCP route / ID              allyworker
□ MCP server name             AllyWorker
□ JS / CSS handles            allyworker-*
□ JS localized object         allyworkerData
□ Admin screen ID             toplevel_page_allyworker
□ Activation hooks            ALLY_WORKER_FILE / AllyWorker\Plugin
□ Ability names               allyworker/*
□ Ability categories          allyworker-*
□ Pro folder                  allyworker-pro/
□ Pro main file               allyworker-pro.php
□ Pro namespace               AllyWorkerPro\
□ Pro constants               ALLY_WORKER_PRO_*
□ Pro text domain             allyworker-pro
□ Pro dependency check        allyworker/allyworker.php
□ Pro filter                  allyworker_abilities
□ CLAUDE.md updated           new prefixes documented
```

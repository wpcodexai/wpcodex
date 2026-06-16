<?php
/**
 * PHPUnit bootstrap for WPWorker tests.
 *
 * Provides:
 *  - WordPress constants required by production code
 *  - Minimal WP class stubs (WP_Error, WP_Post)
 *  - Real global implementations of WordPress functions so tests that do NOT
 *    use Brain\Monkey can call them directly; Brain\Monkey tests override them
 *    via Patchwork on a per-test basis
 *  - A minimal WordPress hooks system (add_filter / apply_filters / add_action)
 *    backed by $GLOBALS['_wp_filter']
 *
 * NOTE: \Brain\Monkey\setUp() is intentionally NOT called here.
 * Each test class that needs Brain\Monkey calls it in its own setUp().
 *
 * @package WPWorker\Tests
 */

declare(strict_types=1);

define("ARRAY_A", "ARRAY_A");
define("ARRAY_N", "ARRAY_N");
define("MINUTE_IN_SECONDS", 60);
define("DAY_IN_SECONDS", 86400);
define("WEEK_IN_SECONDS", 604800);
define("MONTH_IN_SECONDS", 2592000);
define("YEAR_IN_SECONDS", 31536000);

if (!defined("ABSPATH")) {
    $_test_abspath = sys_get_temp_dir() . "/wpworker-test-abspath/";
    // Create the stub upgrade.php so Schema::create_table() can require_once it.
    @mkdir($_test_abspath . "wp-admin/includes/", 0755, true);
    if (!file_exists($_test_abspath . "wp-admin/includes/upgrade.php")) {
        file_put_contents(
            $_test_abspath . "wp-admin/includes/upgrade.php",
            "<?php // stub"
        );
    }
    define("ABSPATH", $_test_abspath);
    unset($_test_abspath);
}

if (!defined("WPWORKER_SANDBOX_DIR")) {
    $_test_sandbox = sys_get_temp_dir() . "/wpworker-test-sandbox/";
    @mkdir($_test_sandbox, 0755, true);
    define("WPWORKER_SANDBOX_DIR", $_test_sandbox);
    unset($_test_sandbox);
}

// Class stubs

if (!class_exists("WP_Error")) {
    class WP_Error
    {
        public function __construct(
            private string $code = "",
            private string $message = "",
            private mixed $data = null
        ) {
        }
        public function get_error_code(): string
        {
            return $this->code;
        }
        public function get_error_message(): string
        {
            return $this->message;
        }
        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

if (!class_exists("WP_Post")) {
    /**
     * Minimal WP_Post stub used by tests for GutenbergStorage::target_title() etc.
     */
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = "";
        public string $post_content = "";
        public string $post_status = "draft";
        public string $post_type = "post";
        public string $post_author = "0";

        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                $this->$key = $value; // @phpstan-ignore-line
            }
        }
    }
}

// Minimal WordPress hooks system

$GLOBALS["_wp_filter"] = [];
$GLOBALS["_wpworker_transients"] = [];
$GLOBALS["_wpworker_options"] = [];

if (!function_exists("add_filter")) {
    function add_filter(
        string $hook_name,
        callable|array|string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): true {
        $GLOBALS["_wp_filter"][$hook_name][$priority][] = $callback;
        return true;
    }
}
if (!function_exists("add_action")) {
    function add_action(
        string $hook_name,
        callable|array|string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): true {
        return add_filter($hook_name, $callback, $priority, $accepted_args);
    }
}
if (!function_exists("apply_filters")) {
    function apply_filters(
        string $hook_name,
        mixed $value,
        mixed ...$args
    ): mixed {
        if (empty($GLOBALS["_wp_filter"][$hook_name])) {
            return $value;
        }
        ksort($GLOBALS["_wp_filter"][$hook_name]);
        foreach ($GLOBALS["_wp_filter"][$hook_name] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array(
                    $callback,
                    array_merge([$value], $args)
                );
            }
        }
        return $value;
    }
}
if (!function_exists("do_action")) {
    function do_action(string $hook_name, mixed ...$args): void
    {
        if (empty($GLOBALS["_wp_filter"][$hook_name])) {
            return;
        }
        ksort($GLOBALS["_wp_filter"][$hook_name]);
        foreach ($GLOBALS["_wp_filter"][$hook_name] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }
}
if (!function_exists("has_filter")) {
    function has_filter(
        string $hook_name,
        callable|bool $callback = false
    ): bool|int {
        return !empty($GLOBALS["_wp_filter"][$hook_name]);
    }
}

// WordPress function stubs
// These are global fallbacks. Brain\Monkey tests override them via Patchwork.

if (!function_exists("__")) {
    function __(string $text, string $domain = "default"): string
    {
        return $text;
    }
}
if (!function_exists("_e")) {
    function _e(string $text, string $domain = "default"): void
    {
        echo $text;
    }
}
if (!function_exists("esc_html")) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
}
if (!function_exists("esc_attr")) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
}
if (!function_exists("esc_html__")) {
    function esc_html__(string $text, string $domain = "default"): string
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
}
if (!function_exists("esc_attr__")) {
    function esc_attr__(string $text, string $domain = "default"): string
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
}
if (!function_exists("esc_url")) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, "UTF-8");
    }
}
if (!function_exists("sanitize_title")) {
    function sanitize_title(
        string $title,
        string $fallback_title = "",
        string $context = "save"
    ): string {
        $title = strtolower($title);
        $title = preg_replace("/[^a-z0-9\-]+/", "-", $title) ?? "";
        $title = trim($title, "-");
        return "" !== $title ? $title : $fallback_title;
    }
}
if (!function_exists("sanitize_text_field")) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}
if (!function_exists("sanitize_key")) {
    function sanitize_key(string $key): string
    {
        return preg_replace("/[^a-z0-9_\-]/", "", strtolower($key)) ?? "";
    }
}
if (!function_exists("wp_kses_post")) {
    function wp_kses_post(string $data): string
    {
        return $data;
    }
}
if (!function_exists("wp_json_encode")) {
    function wp_json_encode(
        mixed $data,
        int $flags = 0,
        int $depth = 512
    ): string|false {
        return json_encode($data, $flags, $depth);
    }
}
if (!function_exists("wp_is_writable")) {
    function wp_is_writable(string $path): bool
    {
        return is_writable($path);
    }
}
if (!function_exists("wp_mkdir_p")) {
    function wp_mkdir_p(string $target): bool
    {
        return is_dir($target) || @mkdir($target, 0755, true);
    }
}
if (!function_exists("wp_admin_notice")) {
    function wp_admin_notice(string $message, array $args = []): void
    {
    }
}
if (!function_exists("current_user_can")) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        return true;
    }
}
if (!function_exists("is_user_logged_in")) {
    function is_user_logged_in(): bool
    {
        return false;
    }
}
if (!function_exists("is_wp_error")) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists("get_transient")) {
    function get_transient(string $transient): mixed
    {
        return $GLOBALS["_wpworker_transients"][$transient] ?? false;
    }
}
if (!function_exists("set_transient")) {
    function set_transient(
        string $transient,
        mixed $value,
        int $expiration = 0
    ): bool {
        $GLOBALS["_wpworker_transients"][$transient] = $value;
        return true;
    }
}
if (!function_exists("delete_transient")) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS["_wpworker_transients"][$transient]);
        return true;
    }
}
if (!function_exists("get_option")) {
    function get_option(string $option, mixed $default_value = false): mixed
    {
        return $GLOBALS["_wpworker_options"][$option] ?? $default_value;
    }
}
if (!function_exists("update_option")) {
    function update_option(
        string $option,
        mixed $value,
        string|bool $autoload = true
    ): bool {
        $GLOBALS["_wpworker_options"][$option] = $value;
        return true;
    }
}
if (!function_exists("delete_option")) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS["_wpworker_options"][$option]);
        return true;
    }
}
if (!function_exists("dbDelta")) {
    /** Stub: production code requires ABSPATH/wp-admin/includes/upgrade.php which defines the real one. */
    function dbDelta(string|array $queries = "", bool $execute = true): array
    {
        return [];
    }
}
if (!function_exists("register_shutdown_function")) {
    // Built-in PHP function — already exists; this guard is just for safety.
}
if (!function_exists("wp_verify_nonce")) {
    function wp_verify_nonce(string $nonce, string|int $action = -1): int|false
    {
        return 1;
    }
}
if (!function_exists("wp_nonce_field")) {
    function wp_nonce_field(
        string|int $action = -1,
        string $name = "_wpnonce",
        bool $referer = true,
        bool $display = true
    ): string {
        return "";
    }
}
if (!function_exists("check_admin_referer")) {
    function check_admin_referer(
        string|int $action = -1,
        string $query_arg = "_wpnonce"
    ): int|false {
        return 1;
    }
}

// Load Composer autoloader

require_once dirname(__DIR__) . "/vendor/autoload.php";

// NOTE: \Brain\Monkey\setUp() is intentionally NOT called globally here.
// Each test class that uses Brain\Monkey must call Monkey\setUp() in setUp()
// and Monkey\tearDown() in tearDown().

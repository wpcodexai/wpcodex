<?php
/**
 * PHPUnit bootstrap for WPCodex tests.
 *
 * @package WPCodex\Tests
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private string $code = '', private string $message = '', private array $data = [] ) {}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): array {
			return $this->data;
		}
	}
}

// Load Composer autoloader (includes PSR-4 map for WPCodex\ and WPCodex\Tests\).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Bootstrap Brain\Monkey for WordPress function mocking.
// Brain\Monkey stubs common WP functions (add_action, get_option, etc.)
// so unit tests never need a live WordPress install.
\Brain\Monkey\setUp();
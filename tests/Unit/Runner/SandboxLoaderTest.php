<?php
/**
 * Unit tests for SandboxLoader.
 *
 * @package WPCodex\Tests\Unit\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Runner\SandboxLoader;

/**
 * Class SandboxLoaderTest
 *
 * Uses a real temp directory so filesystem behaviour (glob, file_exists,
 * rename, etc.) is tested without mocking. WordPress functions are stubbed
 * via Brain\Monkey.
 */
final class SandboxLoaderTest extends TestCase {

	/** Temporary sandbox directory created fresh for every test. */
	private string $sandbox_dir;

	/** Absolute path to the .crashed marker inside the sandbox. */
	private string $crashed_file;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Create a clean sandbox directory for this test.
		$this->sandbox_dir  = sys_get_temp_dir() . '/wpcodex-sandbox-test-' . uniqid( '', true ) . '/';
		$this->crashed_file = $this->sandbox_dir . '.crashed';

		mkdir( $this->sandbox_dir, 0755, true );

		// Point the constant to our temp directory.
		if ( ! defined( 'WPCODEX_SANDBOX_DIR' ) ) {
			define( 'WPCODEX_SANDBOX_DIR', $this->sandbox_dir );
		}

		// Stub WordPress functions used by SandboxLoader.
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_admin_notice' )->justReturn( null );
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( mixed $v, int $f = 0 ): string|false => json_encode( $v, $f )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->remove_dir( $this->sandbox_dir );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// __construct — hook registration
	// -------------------------------------------------------------------------

	public function test_constructor_registers_admin_notices_hook(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_notices', \Mockery::type( 'array' ) );

		new SandboxLoader();
	}

	// -------------------------------------------------------------------------
	// load() — sandbox directory missing
	// -------------------------------------------------------------------------

	public function test_load_does_nothing_when_sandbox_dir_missing(): void {
		$this->remove_dir( $this->sandbox_dir );

		// No files to load — should complete silently with no errors.
		$loader = new SandboxLoader();
		$loader->load();

		// Assertion: no PHP errors / exceptions thrown.
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// load() — abilities disabled (simple path)
	// -------------------------------------------------------------------------

	public function test_load_simple_path_loads_php_files_when_abilities_disabled(): void {
		Functions\when( 'get_option' )->justReturn( false ); // abilities disabled

		// Write a PHP file that sets a global flag.
		$file = $this->sandbox_dir . 'plugin.php';
		file_put_contents( $file, '<?php $GLOBALS["sandbox_loaded"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertTrue( $GLOBALS['sandbox_loaded'] ?? false );
		unset( $GLOBALS['sandbox_loaded'] );
	}

	public function test_load_simple_path_skips_index_php(): void {
		Functions\when( 'get_option' )->justReturn( false );

		file_put_contents( $this->sandbox_dir . 'index.php', '<?php $GLOBALS["index_loaded"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertFalse( $GLOBALS['index_loaded'] ?? false );
	}

	// -------------------------------------------------------------------------
	// load() — abilities enabled, safe mode
	// -------------------------------------------------------------------------

	public function test_load_skips_all_files_when_crashed_marker_exists(): void {
		Functions\when( 'get_option' )->justReturn( true ); // abilities enabled

		// Create .crashed marker.
		file_put_contents( $this->crashed_file, json_encode( [ 'message' => 'fatal', 'sandbox_file' => 'test.php' ] ) );

		// Write a PHP file that would set a flag if loaded.
		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php $GLOBALS["crash_test_loaded"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertFalse( $GLOBALS['crash_test_loaded'] ?? false );
	}

	public function test_load_skips_all_files_when_safe_mode_query_param_set(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$_GET['wpcodex_safe_mode'] = '1';

		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php $GLOBALS["safe_mode_test"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		unset( $_GET['wpcodex_safe_mode'] );

		$this->assertFalse( $GLOBALS['safe_mode_test'] ?? false );
	}

	// -------------------------------------------------------------------------
	// load() — abilities enabled, normal load with crash recovery
	// -------------------------------------------------------------------------

	public function test_load_with_crash_recovery_loads_php_files(): void {
		Functions\when( 'get_option' )->justReturn( true ); // abilities enabled

		$file = $this->sandbox_dir . 'feature.php';
		file_put_contents( $file, '<?php $GLOBALS["feature_loaded"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertTrue( $GLOBALS['feature_loaded'] ?? false );
		unset( $GLOBALS['feature_loaded'] );
	}

	public function test_load_with_crash_recovery_skips_index_php(): void {
		Functions\when( 'get_option' )->justReturn( true );

		file_put_contents( $this->sandbox_dir . 'index.php', '<?php $GLOBALS["index_cr_loaded"] = true;' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertFalse( $GLOBALS['index_cr_loaded'] ?? false );
	}

	public function test_load_with_crash_recovery_loads_multiple_files_in_order(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$GLOBALS['load_order'] = [];

		file_put_contents( $this->sandbox_dir . 'a-first.php',  '<?php $GLOBALS["load_order"][] = "a";' );
		file_put_contents( $this->sandbox_dir . 'b-second.php', '<?php $GLOBALS["load_order"][] = "b";' );

		$loader = new SandboxLoader();
		$loader->load();

		$this->assertSame( [ 'a', 'b' ], $GLOBALS['load_order'] );
		unset( $GLOBALS['load_order'] );
	}

	public function test_load_does_nothing_when_sandbox_is_empty(): void {
		Functions\when( 'get_option' )->justReturn( true );

		// Sandbox exists but has no PHP files.
		$loader = new SandboxLoader();
		$loader->load(); // Should complete silently.

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// maybe_show_crash_notice()
	// -------------------------------------------------------------------------

	public function test_crash_notice_not_shown_when_no_crashed_marker(): void {
		Functions\expect( 'wp_admin_notice' )->never();

		$loader = new SandboxLoader();
		$loader->maybe_show_crash_notice();
	}

	public function test_crash_notice_not_shown_when_user_lacks_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_admin_notice' )->never();

		file_put_contents( $this->crashed_file, json_encode( [ 'message' => 'fatal', 'sandbox_file' => 'test.php' ] ) );

		$loader = new SandboxLoader();
		$loader->maybe_show_crash_notice();
	}

	public function test_crash_notice_shown_when_crashed_marker_exists(): void {
		file_put_contents(
			$this->crashed_file,
			json_encode( [ 'message' => 'Call to undefined function', 'sandbox_file' => '/path/to/plugin.php' ] )
		);

		Functions\expect( 'wp_admin_notice' )->once();

		$loader = new SandboxLoader();
		$loader->maybe_show_crash_notice();
	}

	public function test_crash_notice_includes_file_name_and_error_message(): void {
		file_put_contents(
			$this->crashed_file,
			json_encode( [ 'message' => 'fatal error here', 'sandbox_file' => '/sandbox/broken.php' ] )
		);

		$captured_notice = '';

		Functions\when( 'wp_admin_notice' )->alias(
			static function ( string $notice ) use ( &$captured_notice ): void {
				$captured_notice = $notice;
			}
		);

		$loader = new SandboxLoader();
		$loader->maybe_show_crash_notice();

		$this->assertStringContainsString( 'broken.php',      $captured_notice );
		$this->assertStringContainsString( 'fatal error here', $captured_notice );
	}

	public function test_crash_notice_shown_even_with_malformed_crashed_file(): void {
		// Write invalid JSON — should degrade gracefully, still show a notice.
		file_put_contents( $this->crashed_file, 'not-json' );

		Functions\expect( 'wp_admin_notice' )->once();

		$loader = new SandboxLoader();
		$loader->maybe_show_crash_notice();
	}

	// -------------------------------------------------------------------------
	// collect_files() — via reflection (private method)
	// -------------------------------------------------------------------------

	public function test_collect_files_excludes_non_php_files(): void {
		Functions\when( 'get_option' )->justReturn( true );

		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php' );
		file_put_contents( $this->sandbox_dir . 'readme.md',  '# readme' );
		file_put_contents( $this->sandbox_dir . '.crashed',   '{}' ); // should be excluded

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'collect_files' );
		$method->setAccessible( true );

		/** @var list<string> $files */
		$files = $method->invoke( $loader );
		$names = array_map( 'basename', $files );

		$this->assertContains( 'plugin.php', $names );
		$this->assertNotContains( 'readme.md', $names );
		$this->assertNotContains( '.crashed',  $names );
	}

	public function test_collect_files_excludes_index_php(): void {
		file_put_contents( $this->sandbox_dir . 'index.php',  '<?php // silence' );
		file_put_contents( $this->sandbox_dir . 'feature.php', '<?php' );

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'collect_files' );
		$method->setAccessible( true );

		/** @var list<string> $files */
		$files = $method->invoke( $loader );
		$names = array_map( 'basename', $files );

		$this->assertNotContains( 'index.php',  $names );
		$this->assertContains(    'feature.php', $names );
	}

	public function test_collect_files_returns_empty_array_for_empty_sandbox(): void {
		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'collect_files' );
		$method->setAccessible( true );

		/** @var list<string> $files */
		$files = $method->invoke( $loader );

		$this->assertSame( [], $files );
	}

	// -------------------------------------------------------------------------
	// is_safe_mode() — via reflection (private method)
	// -------------------------------------------------------------------------

	public function test_is_safe_mode_false_when_no_marker_and_no_param(): void {
		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $loader ) );
	}

	public function test_is_safe_mode_true_when_crashed_marker_exists(): void {
		file_put_contents( $this->crashed_file, '{}' );

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $loader ) );
	}

	public function test_is_safe_mode_true_when_query_param_is_1(): void {
		$_GET['wpcodex_safe_mode'] = '1';

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader );
		unset( $_GET['wpcodex_safe_mode'] );

		$this->assertTrue( $result );
	}

	public function test_is_safe_mode_false_when_query_param_is_not_1(): void {
		$_GET['wpcodex_safe_mode'] = '0';

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader );
		unset( $_GET['wpcodex_safe_mode'] );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full = $dir . $entry;
			is_dir( $full ) ? $this->remove_dir( $full . '/' ) : unlink( $full );
		}
		rmdir( $dir );
	}
}
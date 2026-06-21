<?php
/**
 * Unit tests for SandboxLoader.
 *
 * Uses ALLY_WORKER_SANDBOX_DIR (defined in bootstrap) as the real temp sandbox so
 * filesystem behaviour (glob, file_exists, etc.) is exercised without mocking.
 * WordPress functions are stubbed via Brain\Monkey where needed.
 *
 * @package AllyWorker\Tests\Unit\Runner
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test setup/teardown requires direct FS calls; WP_Filesystem is not available in unit test context

namespace AllyWorker\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AllyWorker\Runner\SandboxLoader;

/**
 * @covers \AllyWorker\Runner\SandboxLoader
 */
final class SandboxLoaderTest extends TestCase {

	/**
	 * Absolute path to the test sandbox directory.
	 * Equals ALLY_WORKER_SANDBOX_DIR so the SandboxLoader constant and the test
	 * work on the same directory.
	 */
	private string $sandbox_dir;

	/** Absolute path to the .crashed marker inside the sandbox. */
	private string $crashed_file;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// ALLY_WORKER_SANDBOX_DIR is defined in bootstrap — use it directly.
		$this->sandbox_dir  = ALLY_WORKER_SANDBOX_DIR;
		$this->crashed_file = $this->sandbox_dir . '.crashed';

		// Reset hooks from previous tests.
		$GLOBALS['_wp_filter'] = [];

		// Ensure directory exists and is clean.
		@mkdir( $this->sandbox_dir, 0755, true );
		$this->clean_sandbox();

		// Stub WordPress functions used by SandboxLoader.
		// NOTE: add_action is NOT stubbed here so tests that use
		// Functions\expect('add_action') work correctly.
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( mixed $v, int $f = 0 ): string|false => json_encode( $v, $f )
		);
	}

	protected function tearDown(): void {
		$this->clean_sandbox();
		$GLOBALS['_wp_filter'] = [];
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── load() — sandbox directory missing ────────────────────────────────────

	public function test_load_does_nothing_when_sandbox_dir_missing(): void {
		// Temporarily remove the sandbox directory.
		$this->remove_dir( $this->sandbox_dir );

		$loader = new SandboxLoader();
		$loader->load(); // should complete silently

		// Recreate for tearDown.
		@mkdir( $this->sandbox_dir, 0755, true );

		$this->assertTrue( true );
	}

	// ── load() — abilities disabled (simple path) ─────────────────────────────

	public function test_load_simple_path_loads_php_files_when_abilities_disabled(): void {
		Functions\when( 'get_option' )->justReturn( false ); // abilities disabled

		$file = $this->sandbox_dir . 'plugin.php';
		file_put_contents( $file, '<?php $GLOBALS["sandbox_loaded"] = true;' );

		( new SandboxLoader() )->load();

		$this->assertTrue( $GLOBALS['sandbox_loaded'] ?? false );
		unset( $GLOBALS['sandbox_loaded'] );
	}

	public function test_load_simple_path_skips_index_php(): void {
		Functions\when( 'get_option' )->justReturn( false );

		file_put_contents( $this->sandbox_dir . 'index.php', '<?php $GLOBALS["index_loaded"] = true;' );

		( new SandboxLoader() )->load();

		$this->assertFalse( $GLOBALS['index_loaded'] ?? false );
	}

	// ── load() — abilities enabled, safe mode ─────────────────────────────────

	public function test_load_skips_all_files_when_crashed_marker_exists(): void {
		Functions\when( 'get_option' )->justReturn( true ); // abilities enabled

		file_put_contents( $this->crashed_file, json_encode( [ 'message' => 'fatal', 'sandbox_file' => 'test.php' ] ) );
		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php $GLOBALS["crash_test_loaded"] = true;' );

		( new SandboxLoader() )->load();

		$this->assertFalse( $GLOBALS['crash_test_loaded'] ?? false );
	}

	public function test_load_skips_all_files_when_safe_mode_query_param_set(): void {
		Functions\when( 'get_option' )->justReturn( true );
		$_GET['allyworker_safe_mode'] = '1';

		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php $GLOBALS["safe_mode_test"] = true;' );
		( new SandboxLoader() )->load();

		unset( $_GET['allyworker_safe_mode'] );

		$this->assertFalse( $GLOBALS['safe_mode_test'] ?? false );
	}

	// ── load() — abilities enabled, crash recovery ────────────────────────────

	public function test_load_with_crash_recovery_loads_php_files(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$file = $this->sandbox_dir . 'feature.php';
		file_put_contents( $file, '<?php $GLOBALS["feature_loaded"] = true;' );

		( new SandboxLoader() )->load();

		$this->assertTrue( $GLOBALS['feature_loaded'] ?? false );
		unset( $GLOBALS['feature_loaded'] );
	}

	public function test_load_with_crash_recovery_skips_index_php(): void {
		Functions\when( 'get_option' )->justReturn( true );

		file_put_contents( $this->sandbox_dir . 'index.php', '<?php $GLOBALS["index_cr_loaded"] = true;' );

		( new SandboxLoader() )->load();

		$this->assertFalse( $GLOBALS['index_cr_loaded'] ?? false );
	}

	public function test_load_with_crash_recovery_loads_multiple_files_in_order(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$GLOBALS['load_order'] = [];
		file_put_contents( $this->sandbox_dir . 'a-first.php',  '<?php $GLOBALS["load_order"][] = "a";' );
		file_put_contents( $this->sandbox_dir . 'b-second.php', '<?php $GLOBALS["load_order"][] = "b";' );

		( new SandboxLoader() )->load();

		$this->assertSame( [ 'a', 'b' ], $GLOBALS['load_order'] );
		unset( $GLOBALS['load_order'] );
	}

	public function test_load_does_nothing_when_sandbox_is_empty(): void {
		Functions\when( 'get_option' )->justReturn( true );

		( new SandboxLoader() )->load();

		$this->assertTrue( true );
	}

	// ── maybe_show_crash_notice() ─────────────────────────────────────────────

	public function test_crash_notice_not_shown_when_no_crashed_marker(): void {
		Functions\expect( 'wp_admin_notice' )->never();

		( new SandboxLoader() )->maybe_show_crash_notice();
	}

	public function test_crash_notice_not_shown_when_user_lacks_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_admin_notice' )->never();

		file_put_contents( $this->crashed_file, json_encode( [ 'message' => 'fatal', 'sandbox_file' => 'test.php' ] ) );

		( new SandboxLoader() )->maybe_show_crash_notice();
	}

	public function test_crash_notice_shown_when_crashed_marker_exists(): void {
		file_put_contents(
			$this->crashed_file,
			json_encode( [ 'message' => 'Call to undefined function', 'sandbox_file' => '/path/to/plugin.php' ] )
		);

		Functions\expect( 'wp_admin_notice' )->once();

		( new SandboxLoader() )->maybe_show_crash_notice();
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

		( new SandboxLoader() )->maybe_show_crash_notice();

		$this->assertStringContainsString( 'broken.php',       $captured_notice );
		$this->assertStringContainsString( 'fatal error here', $captured_notice );
	}

	public function test_crash_notice_shown_even_with_malformed_crashed_file(): void {
		file_put_contents( $this->crashed_file, 'not-json' );

		Functions\expect( 'wp_admin_notice' )->once();

		( new SandboxLoader() )->maybe_show_crash_notice();
	}

	// ── collect_files() — via reflection ─────────────────────────────────────

	public function test_collect_files_excludes_non_php_files(): void {
		Functions\when( 'get_option' )->justReturn( true );

		file_put_contents( $this->sandbox_dir . 'plugin.php', '<?php' );
		file_put_contents( $this->sandbox_dir . 'readme.md',  '# readme' );
		file_put_contents( $this->sandbox_dir . '.crashed',   '{}' );

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
		file_put_contents( $this->sandbox_dir . 'index.php',   '<?php // silence' );
		file_put_contents( $this->sandbox_dir . 'feature.php', '<?php' );

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'collect_files' );
		$method->setAccessible( true );

		/** @var list<string> $files */
		$files = $method->invoke( $loader );
		$names = array_map( 'basename', $files );

		$this->assertNotContains( 'index.php',  $names );
		$this->assertContains( 'feature.php', $names );
	}

	public function test_collect_files_returns_empty_array_for_empty_sandbox(): void {
		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'collect_files' );
		$method->setAccessible( true );

		/** @var list<string> $files */
		$files = $method->invoke( $loader );
		$this->assertSame( [], $files );
	}

	// ── is_safe_mode() — via reflection ──────────────────────────────────────

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
		$_GET['allyworker_safe_mode'] = '1';

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader );
		unset( $_GET['allyworker_safe_mode'] );

		$this->assertTrue( $result );
	}

	public function test_is_safe_mode_false_when_query_param_is_not_1(): void {
		$_GET['allyworker_safe_mode'] = '0';

		$loader = new SandboxLoader();
		$method = new \ReflectionMethod( SandboxLoader::class, 'is_safe_mode' );
		$method->setAccessible( true );

		$result = $method->invoke( $loader );
		unset( $_GET['allyworker_safe_mode'] );

		$this->assertFalse( $result );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Remove all files from the sandbox directory (but keep the directory itself).
	 * Also clears hidden files like .crashed.
	 */
	private function clean_sandbox(): void {
		if ( ! is_dir( $this->sandbox_dir ) ) {
			return;
		}
		// Visible files
		foreach ( glob( $this->sandbox_dir . '*' ) ?: [] as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
		// Hidden files (e.g. .crashed)
		foreach ( glob( $this->sandbox_dir . '.*' ) ?: [] as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Fully remove a directory and its contents.
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full = $dir . $entry;
			is_dir( $full ) ? $this->remove_dir( $full . DIRECTORY_SEPARATOR ) : unlink( $full );
		}
		rmdir( $dir );
	}
}

<?php
/**
 * Unit tests for PhpRunner.
 *
 * @package WPCodex\Tests\Unit\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Runner\PhpRunner;

/**
 * Class PhpRunnerTest
 */
final class PhpRunnerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constant used inside PhpRunner.
		if ( ! defined( 'WPCODEX_SANDBOX_DIR' ) ) {
			define( 'WPCODEX_SANDBOX_DIR', sys_get_temp_dir() . '/wpcodex-test-sandbox/' );
		}

		// Ensure sandbox dir exists.
		if ( ! is_dir( WPCODEX_SANDBOX_DIR ) ) {
			mkdir( WPCODEX_SANDBOX_DIR, 0755, true );
		}

		// Reset singleton between tests.
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->reset_singleton();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	public function test_returns_no_output_for_empty_code(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->returnArg();

		$result = PhpRunner::instance()->run( '// no output' );
		$this->assertSame( '[No output]', $result );
	}

	public function test_captures_echo_output(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'echo "hello world";' );
		$this->assertSame( 'hello world', $result );
	}

	public function test_captures_return_value(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->justReturn( '"test_value"' );

		$result = PhpRunner::instance()->run( 'return "test_value";' );
		$this->assertStringContainsString( '[Return value]', $result );
	}

	public function test_returns_error_string_on_exception(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'throw new \RuntimeException("test error");' );
		$this->assertStringContainsString( '[WPCodex PHP Error]', $result );
		$this->assertStringContainsString( 'test error', $result );
	}

	public function test_temp_file_is_deleted_after_run(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$before = count( glob( WPCODEX_SANDBOX_DIR . 'exec_*.php' ) ?: [] );
		PhpRunner::instance()->run( 'echo "test";' );
		$after  = count( glob( WPCODEX_SANDBOX_DIR . 'exec_*.php' ) ?: [] );

		$this->assertSame( $before, $after, 'Temp file was not cleaned up.' );
	}

	public function test_temp_file_is_deleted_after_exception(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$before = count( glob( WPCODEX_SANDBOX_DIR . 'exec_*.php' ) ?: [] );
		PhpRunner::instance()->run( 'throw new \Exception("boom");' );
		$after  = count( glob( WPCODEX_SANDBOX_DIR . 'exec_*.php' ) ?: [] );

		$this->assertSame( $before, $after, 'Temp file was not cleaned up after exception.' );
	}

	public function test_throws_runtime_exception_when_sandbox_not_writable(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$this->expectException( \RuntimeException::class );
		PhpRunner::instance()->run( 'echo "test";' );
	}

	public function test_singleton_returns_same_instance(): void {
		$a = PhpRunner::instance();
		$b = PhpRunner::instance();
		$this->assertSame( $a, $b );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( PhpRunner::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}
}
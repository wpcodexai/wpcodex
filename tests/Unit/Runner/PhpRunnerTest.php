<?php
/**
 * Unit tests for PhpRunner.
 *
 * PhpRunner::run() returns a structured array (not a plain string) since the
 * API was updated.  These tests verify the array keys and values.
 *
 * @package WPCodex\Tests\Unit\Runner
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test setup requires direct FS calls; WP_Filesystem is not available in unit test context

namespace WPCodex\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Runner\PhpRunner;

/**
 * @covers \WPCodex\Runner\PhpRunner
 */
final class PhpRunnerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Ensure sandbox dir exists (WPCODEX_SANDBOX_DIR is defined in bootstrap).
		if ( ! is_dir( WPCODEX_SANDBOX_DIR ) ) {
			mkdir( WPCODEX_SANDBOX_DIR, 0755, true );
		}

		$this->reset_singleton();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->reset_singleton();
		parent::tearDown();
	}

	// ── Structured result keys ────────────────────────────────────────────────

	public function test_run_returns_array_with_required_keys(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( '// no-op' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success',           $result );
		$this->assertArrayHasKey( 'return_value',      $result );
		$this->assertArrayHasKey( 'output',            $result );
		$this->assertArrayHasKey( 'errors',            $result );
		$this->assertArrayHasKey( 'error_message',     $result );
		$this->assertArrayHasKey( 'error_class',       $result );
		$this->assertArrayHasKey( 'execution_time_ms', $result );
	}

	// ── Output capture ────────────────────────────────────────────────────────

	public function test_returns_empty_output_for_no_output_code(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( '// no output' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '', $result['output'] );
	}

	public function test_captures_echo_output(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'echo "hello world";' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'hello world', $result['output'] );
	}

	// ── Return value ──────────────────────────────────────────────────────────

	public function test_captures_return_value(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'return "test_value";' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'test_value', $result['return_value'] );
	}

	public function test_return_value_is_null_for_code_with_no_return(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'echo "hi";' );

		$this->assertNull( $result['return_value'] );
	}

	// ── Exception handling ────────────────────────────────────────────────────

	public function test_returns_failure_result_on_exception(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'throw new \RuntimeException("test error");' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'test error', $result['error_message'] );
		$this->assertSame( 'RuntimeException', $result['error_class'] );
		$this->assertSame( '', $result['output'] );
	}

	public function test_error_class_reflects_actual_exception_type(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'throw new \LogicException("boom");' );

		$this->assertSame( 'LogicException', $result['error_class'] );
	}

	// ── Execution time ────────────────────────────────────────────────────────

	public function test_execution_time_ms_is_non_negative_float(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( true );

		$result = PhpRunner::instance()->run( 'echo 1;' );

		$this->assertIsFloat( $result['execution_time_ms'] );
		$this->assertGreaterThanOrEqual( 0.0, $result['execution_time_ms'] );
	}

	// ── Temp-file cleanup ─────────────────────────────────────────────────────

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

	// ── Sandbox not writable ──────────────────────────────────────────────────

	public function test_throws_runtime_exception_when_sandbox_not_writable(): void {
		Functions\when( 'wp_mkdir_p' )->justReturn( true );
		Functions\when( 'wp_is_writable' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$this->expectException( \RuntimeException::class );
		PhpRunner::instance()->run( 'echo "test";' );
	}

	// ── Singleton ─────────────────────────────────────────────────────────────

	public function test_singleton_returns_same_instance(): void {
		$a = PhpRunner::instance();
		$b = PhpRunner::instance();
		$this->assertSame( $a, $b );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( PhpRunner::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}
}

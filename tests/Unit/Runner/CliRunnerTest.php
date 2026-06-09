<?php
/**
 * Unit tests for WPCodex\Runner\CliRunner.
 *
 * run() itself spawns a real subprocess, so we only test the parts that are
 * unit-testable: the singleton, the RuntimeException when WP-CLI is absent,
 * and the safe_env() credential-stripping via reflection.
 *
 * @package WPCodex\Tests\Unit\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Runner\CliRunner;

/**
 * @covers \WPCodex\Runner\CliRunner
 */
class CliRunnerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		$this->reset_singleton();
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Singleton ─────────────────────────────────────────────────────────────

	public function test_instance_returns_same_object(): void {
		$a = CliRunner::instance();
		$b = CliRunner::instance();
		$this->assertSame( $a, $b );
	}

	public function test_instance_returns_cli_runner(): void {
		$this->assertInstanceOf( CliRunner::class, CliRunner::instance() );
	}

	// ── run() throws when WP-CLI is not available ─────────────────────────────

	/**
	 * Only runs when neither /usr/local/bin/wp, /usr/bin/wp, nor
	 * ABSPATH/wp-cli.phar exist and `which wp` returns nothing.
	 * On CI with WP-CLI installed the test is skipped.
	 */
	public function test_run_throws_when_wp_cli_not_found(): void {
		if ( $this->wp_cli_exists_anywhere() ) {
			$this->markTestSkipped( 'WP-CLI is installed on this system; skipping not-found test.' );
		}

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/WP-CLI not found/i' );

		CliRunner::instance()->run( 'eval "echo 1;"' );
	}

	// ── safe_env strips credentials ───────────────────────────────────────────

	public function test_safe_env_strips_http_authorization(): void {
		putenv( 'HTTP_AUTHORIZATION=Bearer secret' );

		$env = $this->call_safe_env();

		$this->assertArrayNotHasKey( 'HTTP_AUTHORIZATION', $env );

		putenv( 'HTTP_AUTHORIZATION' );
	}

	public function test_safe_env_strips_wpcodex_secret(): void {
		putenv( 'WPCODEX_SECRET=topsecret' );

		$env = $this->call_safe_env();

		$this->assertArrayNotHasKey( 'WPCODEX_SECRET', $env );

		putenv( 'WPCODEX_SECRET' );
	}

	public function test_safe_env_preserves_normal_env_vars(): void {
		// Use a custom env var to avoid platform differences with 'PATH'.
		$key = 'WPCODEX_TEST_PRESERVE_' . strtoupper( bin2hex( random_bytes( 4 ) ) );
		$val = 'test_value_' . bin2hex( random_bytes( 4 ) );

		putenv( "{$key}={$val}" );

		$env = $this->call_safe_env();

		$this->assertIsArray( $env );
		$this->assertArrayHasKey( $key, $env );
		$this->assertSame( $val, $env[ $key ] );

		putenv( $key ); // unset
	}

	public function test_safe_env_returns_array(): void {
		$env = $this->call_safe_env();
		$this->assertIsArray( $env );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function reset_singleton(): void {
		$ref = new \ReflectionProperty( CliRunner::class, 'instance' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}

	/**
	 * Call the private safe_env() method via reflection.
	 *
	 * @return array<string, string>
	 */
	private function call_safe_env(): array {
		$method = new \ReflectionMethod( CliRunner::class, 'safe_env' );
		$method->setAccessible( true );
		/** @var array<string, string> */
		return $method->invoke( CliRunner::instance() );
	}

	private function wp_cli_exists_anywhere(): bool {
		$candidates = [
			ABSPATH . 'wp-cli.phar',
			'/usr/local/bin/wp',
			'/usr/bin/wp',
		];
		foreach ( $candidates as $p ) {
			if ( file_exists( $p ) && is_executable( $p ) ) {
				return true;
			}
		}
		$which = trim( (string) @shell_exec( 'which wp 2>/dev/null' ) );
		return $which !== '' && file_exists( $which );
	}
}

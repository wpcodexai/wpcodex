<?php
/**
 * Unit tests for DbRunner.
 *
 * @package AllyWorker\Tests\Unit\Runner
 */

declare( strict_types=1 );

namespace AllyWorker\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AllyWorker\Runner\DbRunner;

/**
 * Class DbRunnerTest
 */
class DbRunnerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->reset_singleton();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a $wpdb mock with controllable behaviour.
	 *
	 * @param array<string, mixed> $overrides Properties to override on the mock.
	 * @return object Mock $wpdb object.
	 */
	private function make_wpdb( array $overrides = [] ): object {
		$wpdb = new class( $overrides ) {
			public string $last_error = '';
			public array  $overrides;

			public function __construct( array $overrides ) {
				$this->overrides = $overrides;
			}

			public function prepare( string $sql, mixed ...$args ): string {
				// Minimal vsprintf-style substitute for tests.
				return vsprintf( str_replace( [ '%s', '%d' ], '%s', $sql ), $args );
			}

			public function get_results( string $sql, string $output = '' ): ?array {
				return $this->overrides['get_results'] ?? [];
			}

			public function query( string $sql ): int|false {
				return $this->overrides['query'] ?? 1;
			}
		};

		return $wpdb;
	}

	private function set_global_wpdb( object $wpdb ): void {
		$GLOBALS['wpdb'] = $wpdb;
	}

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( DbRunner::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	public function test_select_returns_json_array(): void {
		Functions\when( 'wp_json_encode' )->alias( static fn( $v, $f = 0 ) => json_encode( $v, $f ) );

		$wpdb = $this->make_wpdb( [ 'get_results' => [ [ 'id' => 1, 'title' => 'Hello' ] ] ] );
		$this->set_global_wpdb( $wpdb );

		$result  = DbRunner::instance()->query( 'SELECT * FROM posts' );
		$decoded = json_decode( $result, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( 1, $decoded[0]['id'] );
	}

	public function test_select_returns_empty_array_for_no_rows(): void {
		Functions\when( 'wp_json_encode' )->alias( static fn( $v, $f = 0 ) => json_encode( $v, $f ) );

		$wpdb = $this->make_wpdb( [ 'get_results' => [] ] );
		$this->set_global_wpdb( $wpdb );

		$result  = DbRunner::instance()->query( 'SELECT * FROM posts WHERE 1=0' );
		$decoded = json_decode( $result, true );

		$this->assertIsArray( $decoded );
		$this->assertEmpty( $decoded );
	}

	public function test_insert_returns_affected_row_count(): void {
		$wpdb = $this->make_wpdb( [ 'query' => 1 ] );
		$this->set_global_wpdb( $wpdb );

		$result = DbRunner::instance()->query( 'INSERT INTO posts (post_title) VALUES ("Test")' );
		$this->assertStringContainsString( 'Rows affected: 1', $result );
	}

	public function test_update_returns_affected_row_count(): void {
		$wpdb = $this->make_wpdb( [ 'query' => 3 ] );
		$this->set_global_wpdb( $wpdb );

		$result = DbRunner::instance()->query( 'UPDATE posts SET post_status = "draft"' );
		$this->assertStringContainsString( 'Rows affected: 3', $result );
	}

	public function test_throws_on_select_error(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();

		$wpdb             = $this->make_wpdb( [ 'get_results' => null ] );
		$wpdb->last_error = 'Table does not exist';
		$this->set_global_wpdb( $wpdb );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Table does not exist' );
		DbRunner::instance()->query( 'SELECT * FROM nonexistent' );
	}

	public function test_throws_on_mutation_error(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();

		$wpdb             = $this->make_wpdb( [ 'query' => false ] );
		$wpdb->last_error = 'Syntax error';
		$this->set_global_wpdb( $wpdb );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Syntax error' );
		DbRunner::instance()->query( 'INVALID SQL' );
	}

	public function test_singleton_returns_same_instance(): void {
		$a = DbRunner::instance();
		$b = DbRunner::instance();
		$this->assertSame( $a, $b );
	}

	public function test_show_is_treated_as_select(): void {
		Functions\when( 'wp_json_encode' )->alias( static fn( $v, $f = 0 ) => json_encode( $v, $f ) );

		$wpdb = $this->make_wpdb( [ 'get_results' => [ [ 'Tables_in_db' => 'posts' ] ] ] );
		$this->set_global_wpdb( $wpdb );

		$result = DbRunner::instance()->query( 'SHOW TABLES' );
		$this->assertStringStartsWith( '[', $result );
	}
}
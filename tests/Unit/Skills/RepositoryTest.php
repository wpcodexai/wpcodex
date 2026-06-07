<?php
/**
 * Unit tests for Skills\Repository.
 *
 * @package WPCodex\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Skills;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Skills\Repository;
use WPCodex\Skills\Schema;

/**
 * Class RepositoryTest
 *
 * Uses a $wpdb mock so no real DB connection is needed.
 */
class RepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub Schema::table_name().
		// We can't mock static methods directly, so we'll use a real wpdb mock.
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'sanitize_title' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );

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

	private function make_wpdb( array $config = [] ): object {
		return new class( $config ) {
			public string $last_error = '';
			public int    $insert_id  = 0;
			private array $config;

			public function __construct( array $config ) {
				$this->config = $config;
			}

			public function prepare( string $sql, mixed ...$args ): string {
				return $sql; // Return as-is for test purposes.
			}

			public function get_results( string $sql, string $output = '' ): ?array {
				return $this->config['get_results'] ?? [];
			}

			public function get_row( string $sql, string $output = '' ): mixed {
				return $this->config['get_row'] ?? null;
			}

			public function insert( string $table, array $data, array $format = [] ): int|false {
				$result           = $this->config['insert'] ?? 1;
				$this->insert_id  = $result ? 42 : 0;
				return $result;
			}

			public function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ): int|false {
				return $this->config['update'] ?? 1;
			}

			public function delete( string $table, array $where, array $format = [] ): int|false {
				return $this->config['delete'] ?? 1;
			}
		};
	}

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( Repository::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	// -------------------------------------------------------------------------
	// all()
	// -------------------------------------------------------------------------

	public function test_all_returns_array_of_skills(): void {
		$wpdb = $this->make_wpdb( [
			'get_results' => [
				[ 'id' => '1', 'name' => 'test-skill', 'description' => 'A skill', 'enable_agentic' => '1', 'enable_prompt' => '0', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01' ],
			],
		] );
		$GLOBALS['wpdb']       = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$skills = Repository::instance()->all();
		$this->assertCount( 1, $skills );
		$this->assertSame( 'test-skill', $skills[0]['name'] );
		$this->assertTrue( $skills[0]['enable_agentic'] );
		$this->assertFalse( $skills[0]['enable_prompt'] );
	}

	public function test_all_returns_empty_array_when_no_skills(): void {
		$wpdb = $this->make_wpdb( [ 'get_results' => [] ] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$this->assertSame( [], Repository::instance()->all() );
	}

	// -------------------------------------------------------------------------
	// find()
	// -------------------------------------------------------------------------

	public function test_find_returns_skill_when_found(): void {
		$row  = [ 'id' => '1', 'name' => 'my-skill', 'description' => 'desc', 'body' => 'body content', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01' ];
		$wpdb = $this->make_wpdb( [ 'get_row' => $row ] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$skill = Repository::instance()->find( 'my-skill' );
		$this->assertNotNull( $skill );
		$this->assertSame( 'my-skill', $skill['name'] );
		$this->assertTrue( $skill['enable_agentic'] );
	}

	public function test_find_returns_null_when_not_found(): void {
		$wpdb = $this->make_wpdb( [ 'get_row' => null ] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$this->assertNull( Repository::instance()->find( 'nonexistent' ) );
	}

	// -------------------------------------------------------------------------
	// create()
	// -------------------------------------------------------------------------

	public function test_create_returns_insert_id_on_success(): void {
		// First call to find() returns null (no duplicate), then insert succeeds.
		$call_count = 0;
		$wpdb       = new class( $call_count ) {
			public string $last_error = '';
			public int    $insert_id  = 42;
			private int   $calls      = 0;

			public function __construct( int &$count ) {
				$this->calls = &$count;
			}

			public function prepare( string $sql, mixed ...$args ): string {
				return $sql;
			}

			public function get_row( string $sql, string $output = '' ): mixed {
				return null; // No duplicate found.
			}

			public function insert( string $table, array $data, array $format = [] ): int {
				return 1;
			}
		};
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->create( [
			'name'           => 'new-skill',
			'description'    => 'A new skill',
			'body'           => '# Body',
			'enable_agentic' => true,
			'enable_prompt'  => true,
		] );

		$this->assertSame( 42, $result );
	}

	public function test_create_returns_wp_error_on_duplicate(): void {
		$wpdb = $this->make_wpdb( [
			'get_row' => [ 'id' => '1', 'name' => 'existing', 'description' => 'x', 'body' => 'x', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ],
		] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->create( [
			'name'           => 'existing',
			'description'    => 'desc',
			'body'           => 'body',
			'enable_agentic' => true,
			'enable_prompt'  => true,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_duplicate', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// update()
	// -------------------------------------------------------------------------

	public function test_update_returns_true_on_success(): void {
		$wpdb = $this->make_wpdb( [
			'get_row' => [ 'id' => '1', 'name' => 'skill', 'description' => 'old', 'body' => 'old', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ],
			'update'  => 1,
		] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->update( 'skill', [ 'description' => 'new' ] );
		$this->assertTrue( $result );
	}

	public function test_update_returns_wp_error_when_not_found(): void {
		$wpdb = $this->make_wpdb( [ 'get_row' => null ] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->update( 'missing', [ 'description' => 'x' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_not_found', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// delete()
	// -------------------------------------------------------------------------

	public function test_delete_returns_true_on_success(): void {
		$wpdb = $this->make_wpdb( [
			'get_row' => [ 'id' => '1', 'name' => 'skill', 'description' => 'd', 'body' => 'b', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ],
			'delete'  => 1,
		] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->delete( 'skill' );
		$this->assertTrue( $result );
	}

	public function test_delete_returns_wp_error_when_not_found(): void {
		$wpdb = $this->make_wpdb( [ 'get_row' => null ] );
		$GLOBALS['wpdb']         = $wpdb;
		$GLOBALS['wpdb']->prefix = 'wp_';

		$result = Repository::instance()->delete( 'ghost' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_not_found', $result->get_error_code() );
	}

	public function test_singleton_returns_same_instance(): void {
		$a = Repository::instance();
		$b = Repository::instance();
		$this->assertSame( $a, $b );
	}
}
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
 * @covers \WPCodex\Skills\Repository
 */
class RepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'sanitize_title' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $v ): bool => $v instanceof \WP_Error );

		$this->reset_singleton();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->reset_singleton();
		parent::tearDown();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Build a $wpdb mock with controllable behaviour.
	 *
	 * @param array<string, mixed> $config Keys: get_results, get_row, insert, update, delete.
	 */
	private function make_wpdb( array $config = [] ): object {
		return new class( $config ) {
			public string $prefix     = 'wp_';
			public string $last_error = '';
			public int    $insert_id  = 0;
			private array $cfg;

			public function __construct( array $config ) {
				$this->cfg = $config;
			}

			public function prepare( string $sql, mixed ...$args ): string {
				return $sql;
			}

			public function get_results( string $sql, string $output = '' ): ?array {
				return $this->cfg['get_results'] ?? [];
			}

			public function get_row( string $sql, string $output = '' ): mixed {
				return $this->cfg['get_row'] ?? null;
			}

			public function insert( string $table, array $data, array $format = [] ): int|false {
				$result          = $this->cfg['insert'] ?? 1;
				$this->insert_id = $result ? 42 : 0;
				return $result;
			}

			public function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ): int|false {
				return $this->cfg['update'] ?? 1;
			}

			public function delete( string $table, array $where, array $format = [] ): int|false {
				return $this->cfg['delete'] ?? 1;
			}

			public function get_var( ?string $query = null ): string|null {
				// Returns null → prune_revisions() sees count=0 and exits early.
				return $this->cfg['get_var'] ?? null;
			}

			public function query( string $sql ): int|false {
				return $this->cfg['query'] ?? 1;
			}
		};
	}

	private function set_wpdb( object $wpdb ): void {
		$GLOBALS['wpdb'] = $wpdb;
	}

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( Repository::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	// ── all() ─────────────────────────────────────────────────────────────────

	public function test_all_returns_array_of_skills(): void {
		$this->set_wpdb( $this->make_wpdb( [
			'get_results' => [
				[ 'id' => '1', 'name' => 'test-skill', 'description' => 'A skill', 'enable_agentic' => '1', 'enable_prompt' => '0', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01' ],
			],
		] ) );

		$skills = Repository::instance()->all();
		$this->assertCount( 1, $skills );
		$this->assertSame( 'test-skill', $skills[0]['name'] );
		$this->assertTrue( $skills[0]['enable_agentic'] );
		$this->assertFalse( $skills[0]['enable_prompt'] );
	}

	public function test_all_returns_empty_array_when_no_skills(): void {
		$this->set_wpdb( $this->make_wpdb( [ 'get_results' => [] ] ) );

		$this->assertSame( [], Repository::instance()->all() );
	}

	// ── find() ────────────────────────────────────────────────────────────────

	public function test_find_returns_skill_when_found(): void {
		$row = [ 'id' => '1', 'name' => 'my-skill', 'description' => 'desc', 'body' => 'body', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01' ];
		$this->set_wpdb( $this->make_wpdb( [ 'get_row' => $row ] ) );

		$skill = Repository::instance()->find( 'my-skill' );
		$this->assertNotNull( $skill );
		$this->assertSame( 'my-skill', $skill['name'] );
		$this->assertTrue( $skill['enable_agentic'] );
	}

	public function test_find_returns_null_when_not_found(): void {
		$this->set_wpdb( $this->make_wpdb( [ 'get_row' => null ] ) );

		$this->assertNull( Repository::instance()->find( 'nonexistent' ) );
	}

	// ── create() ─────────────────────────────────────────────────────────────

	public function test_create_returns_id_name_and_action_on_success(): void {
		// find() returns null → no duplicate; insert succeeds.
		$this->set_wpdb( $this->make_wpdb( [
			'get_row' => null,
			'insert'  => 1, // insert_id will be set to 42 by the mock
		] ) );

		$result = Repository::instance()->create( [
			'name'           => 'new-skill',
			'description'    => 'A new skill',
			'body'           => '# Body',
			'enable_agentic' => true,
			'enable_prompt'  => true,
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 42,          $result['id'] );
		$this->assertSame( 'new-skill', $result['name'] );
		$this->assertSame( 'created',   $result['action'] );
	}

	public function test_create_returns_wp_error_on_duplicate(): void {
		$row = [ 'id' => '1', 'name' => 'existing', 'description' => 'x', 'body' => 'x', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ];
		$this->set_wpdb( $this->make_wpdb( [ 'get_row' => $row ] ) );

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

	// ── update() ─────────────────────────────────────────────────────────────

	public function test_update_returns_name_and_changed_fields_on_success(): void {
		$row = [ 'id' => '1', 'name' => 'skill', 'description' => 'old', 'body' => 'old', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ];
		$this->set_wpdb( $this->make_wpdb( [
			'get_row' => $row,
			'update'  => 1,
		] ) );

		$result = Repository::instance()->update( 'skill', [ 'description' => 'new' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'skill', $result['name'] );
		$this->assertContains( 'description', $result['changed_fields'] );
	}

	public function test_update_returns_wp_error_when_not_found(): void {
		$this->set_wpdb( $this->make_wpdb( [ 'get_row' => null ] ) );

		$result = Repository::instance()->update( 'missing', [ 'description' => 'x' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_not_found', $result->get_error_code() );
	}

	// ── delete() ─────────────────────────────────────────────────────────────

	public function test_delete_returns_true_on_success(): void {
		$row = [ 'id' => '1', 'name' => 'skill', 'description' => 'd', 'body' => 'b', 'enable_agentic' => '1', 'enable_prompt' => '1', 'created_at' => '', 'updated_at' => '' ];
		$this->set_wpdb( $this->make_wpdb( [
			'get_row' => $row,
			'delete'  => 1,
		] ) );

		$result = Repository::instance()->delete( 'skill' );
		$this->assertTrue( $result );
	}

	public function test_delete_is_idempotent_when_not_found(): void {
		// delete() is idempotent — not-found returns true (not WP_Error).
		$this->set_wpdb( $this->make_wpdb( [ 'get_row' => null ] ) );

		$result = Repository::instance()->delete( 'ghost' );
		$this->assertTrue( $result );
	}

	// ── Singleton ─────────────────────────────────────────────────────────────

	public function test_singleton_returns_same_instance(): void {
		$a = Repository::instance();
		$b = Repository::instance();
		$this->assertSame( $a, $b );
	}
}

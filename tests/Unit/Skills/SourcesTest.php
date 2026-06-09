<?php
/**
 * Unit tests for WPCodex\Skills\Sources.
 *
 * Does NOT use Brain\Monkey — tests rely on the real hooks system and the
 * real function stubs from tests/bootstrap.php.
 *
 * @package WPCodex\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Skills;

use PHPUnit\Framework\TestCase;
use WPCodex\Skills\Repository;
use WPCodex\Skills\Sources;

/**
 * @covers \WPCodex\Skills\Sources
 */
class SourcesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Reset the filter registry so previous tests' add_filter calls don't bleed in.
		$GLOBALS['_wp_filter'] = [];

		// Set up a minimal $wpdb mock so Repository::all() / Sources::load_user_db()
		// can call $wpdb->get_results() without a real database.
		global $wpdb;
		$wpdb = new class() {
			public string $prefix      = 'wp_';
			public string $last_error  = '';
			public int    $insert_id   = 0;

			public function get_results( string $query, string $output = OBJECT ): array {
				return [];
			}

			public function get_row( string $query, string $output = OBJECT ): array|null {
				return null;
			}

			// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
			public function get_var( ?string $query = null ): string|null {
				return null;
			}

			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}

			// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}

			public function insert( string $table, array $data, array|string $format = [] ): int|false {
				return 1;
			}

			public function update( string $table, array $data, array $where ): int|false {
				return 1;
			}

			public function delete( string $table, array $where ): int|false {
				return 1;
			}
		};

		// Reset Repository singleton so it picks up the fresh $wpdb.
		$this->reset_repository_singleton();
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_filter'] = [];
		$this->reset_repository_singleton();
		parent::tearDown();
	}

	// ── registry ──────────────────────────────────────────────────────────────

	public function test_registry_contains_user_db_source(): void {
		$registry = Sources::registry();
		$ids      = array_column( $registry, 'id' );
		$this->assertContains( 'user-db', $ids );
	}

	public function test_registry_is_sorted_by_priority(): void {
		$registry   = Sources::registry();
		$priorities = array_column( $registry, 'priority' );
		$sorted     = $priorities;
		sort( $sorted );
		$this->assertSame( $sorted, $priorities );
	}

	public function test_registry_entries_have_required_keys(): void {
		foreach ( Sources::registry() as $entry ) {
			$this->assertArrayHasKey( 'id',       $entry );
			$this->assertArrayHasKey( 'priority', $entry );
			$this->assertArrayHasKey( 'label',    $entry );
			$this->assertArrayHasKey( 'loader',   $entry );
			$this->assertIsCallable( $entry['loader'] );
		}
	}

	// ── discoverable ─────────────────────────────────────────────────────────

	public function test_discoverable_excludes_skills_with_empty_description(): void {
		add_filter(
			'wpcodex_skill_sources',
			static function ( array $sources ): array {
				$sources['test-empty'] = [
					'id'       => 'test-empty',
					'priority' => 1,
					'label'    => 'Test',
					'loader'   => static fn(): array => [
						[
							'slug'           => 'empty-desc',
							'name'           => 'empty-desc',
							'description'    => '',
							'body'           => '# Content',
							'enable_prompt'  => true,
							'enable_agentic' => true,
						],
					],
				];
				return $sources;
			}
		);

		$results = Sources::discoverable( 'agentic' );
		$slugs   = array_column( $results, 'slug' );
		$this->assertNotContains( 'empty-desc', $slugs );
	}

	public function test_discoverable_excludes_skills_with_empty_body(): void {
		add_filter(
			'wpcodex_skill_sources',
			static function ( array $sources ): array {
				$sources['test-empty-body'] = [
					'id'       => 'test-empty-body',
					'priority' => 1,
					'label'    => 'Test',
					'loader'   => static fn(): array => [
						[
							'slug'           => 'no-body',
							'name'           => 'no-body',
							'description'    => 'Has description',
							'body'           => '',
							'enable_prompt'  => true,
							'enable_agentic' => true,
						],
					],
				];
				return $sources;
			}
		);

		$results = Sources::discoverable( 'prompt' );
		$slugs   = array_column( $results, 'slug' );
		$this->assertNotContains( 'no-body', $slugs );
	}

	// ── exists_in_external_source ─────────────────────────────────────────────

	public function test_exists_in_external_source_returns_null_for_user_db(): void {
		// user-db source should be skipped.
		$result = Sources::exists_in_external_source( 'any-slug' );
		$this->assertNull( $result );
	}

	public function test_exists_in_external_source_returns_label_for_match(): void {
		add_filter(
			'wpcodex_skill_sources',
			static function ( array $sources ): array {
				$sources['ext'] = [
					'id'       => 'ext',
					'priority' => 1,
					'label'    => 'External',
					'loader'   => static fn(): array => [
						[ 'slug' => 'existing-skill', 'name' => 'existing-skill', 'description' => '', 'body' => '' ],
					],
				];
				return $sources;
			}
		);

		$label = Sources::exists_in_external_source( 'existing-skill' );
		$this->assertSame( 'External', $label );
	}

	// ── find ─────────────────────────────────────────────────────────────────

	public function test_find_returns_null_for_unknown_slug(): void {
		$this->assertNull( Sources::find( 'does-not-exist-xyz' ) );
	}

	public function test_find_normalises_slug_before_matching(): void {
		add_filter(
			'wpcodex_skill_sources',
			static function ( array $sources ): array {
				$sources['find-test'] = [
					'id'       => 'find-test',
					'priority' => 1,
					'label'    => 'FindTest',
					'loader'   => static fn(): array => [
						[ 'slug' => 'find-me', 'name' => 'find-me', 'description' => 'desc', 'body' => 'body' ],
					],
				];
				return $sources;
			}
		);

		// Mixed case / space → will be normalised to 'find-me'.
		$skill = Sources::find( 'Find Me' );
		$this->assertNotNull( $skill );
		$this->assertSame( 'FindTest', $skill['source_label'] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function reset_repository_singleton(): void {
		$prop = new \ReflectionProperty( Repository::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}
}

<?php
/**
 * Unit tests for WPCodex\Utils\GutenbergStorage — pure computation methods only.
 *
 * Methods that touch the WordPress database (create_batch, get_batches, etc.)
 * are integration-tested separately; this suite covers the stateless helpers
 * that operate entirely on in-memory data.
 *
 * @package WPCodex\Tests\Unit\Utils
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Utils;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Utils\GutenbergStorage;

/**
 * @covers \WPCodex\Utils\GutenbergStorage
 */
class GutenbergStorageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Constants sanity ──────────────────────────────────────────────────────

	public function test_post_type_constant_is_wpcodex_gb_change(): void {
		$this->assertSame( 'wpcodex_gb_change', GutenbergStorage::POST_TYPE );
	}

	public function test_non_terminal_and_terminal_statuses_are_disjoint(): void {
		$overlap = array_intersect(
			GutenbergStorage::NON_TERMINAL_STATUSES,
			GutenbergStorage::TERMINAL_STATUSES
		);
		$this->assertEmpty( $overlap );
	}

	public function test_all_status_constants_appear_in_one_list(): void {
		$all_statuses = [
			GutenbergStorage::STATUS_DRAFT,
			GutenbergStorage::STATUS_READY,
			GutenbergStorage::STATUS_RUNNING,
			GutenbergStorage::STATUS_PREPARED,
			GutenbergStorage::STATUS_FINALIZED,
			GutenbergStorage::STATUS_FAILED,
			GutenbergStorage::STATUS_CONFLICTED,
			GutenbergStorage::STATUS_CANCELED,
			GutenbergStorage::STATUS_STALE,
		];
		$combined = array_merge(
			GutenbergStorage::NON_TERMINAL_STATUSES,
			GutenbergStorage::TERMINAL_STATUSES
		);
		foreach ( $all_statuses as $s ) {
			$this->assertContains( $s, $combined, "Status '{$s}' missing from combined list." );
		}
	}

	// ── now_mysql ─────────────────────────────────────────────────────────────

	public function test_now_mysql_returns_mysql_datetime_format(): void {
		$ts = GutenbergStorage::now_mysql();
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ts );
	}

	// ── content_hash ─────────────────────────────────────────────────────────

	public function test_content_hash_returns_64_char_hex(): void {
		$hash = GutenbergStorage::content_hash( 'hello world' );
		$this->assertSame( 64, strlen( $hash ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $hash );
	}

	public function test_content_hash_is_deterministic(): void {
		$this->assertSame(
			GutenbergStorage::content_hash( 'same content' ),
			GutenbergStorage::content_hash( 'same content' )
		);
	}

	public function test_content_hash_differs_for_different_content(): void {
		$this->assertNotSame(
			GutenbergStorage::content_hash( 'content a' ),
			GutenbergStorage::content_hash( 'content b' )
		);
	}

	// ── input_target_id ───────────────────────────────────────────────────────

	public function test_input_target_id_reads_target_id_key(): void {
		$this->assertSame( 42, GutenbergStorage::input_target_id( [ 'target_id' => 42 ] ) );
	}

	public function test_input_target_id_falls_back_to_post_id(): void {
		$this->assertSame( 7, GutenbergStorage::input_target_id( [ 'post_id' => 7 ] ) );
	}

	public function test_input_target_id_prefers_target_id_over_post_id(): void {
		$this->assertSame( 10, GutenbergStorage::input_target_id( [ 'target_id' => 10, 'post_id' => 99 ] ) );
	}

	public function test_input_target_id_returns_zero_when_missing(): void {
		$this->assertSame( 0, GutenbergStorage::input_target_id( [] ) );
	}

	public function test_input_target_id_casts_string_to_int(): void {
		$this->assertSame( 5, GutenbergStorage::input_target_id( [ 'target_id' => '5' ] ) );
	}

	public function test_input_target_id_returns_zero_for_non_scalar(): void {
		$this->assertSame( 0, GutenbergStorage::input_target_id( [ 'target_id' => [] ] ) );
	}

	// ── normalize_blocks ─────────────────────────────────────────────────────

	public function test_normalize_blocks_returns_error_for_non_array(): void {
		$result = GutenbergStorage::normalize_blocks( 'not-an-array' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_normalize_blocks_returns_error_for_empty_array(): void {
		$result = GutenbergStorage::normalize_blocks( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gutenberg_empty_block_spec', $result->get_error_code() );
	}

	public function test_normalize_blocks_accepts_single_block_object(): void {
		$result = GutenbergStorage::normalize_blocks( [ 'name' => 'core/paragraph', 'attributes' => [] ] );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'core/paragraph', $result[0]['name'] );
	}

	public function test_normalize_blocks_accepts_array_of_blocks(): void {
		$blocks = [
			[ 'name' => 'core/heading',   'attributes' => [] ],
			[ 'name' => 'core/paragraph', 'attributes' => [] ],
		];
		$result = GutenbergStorage::normalize_blocks( $blocks );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	public function test_normalize_blocks_returns_error_for_missing_name(): void {
		$result = GutenbergStorage::normalize_blocks( [ [ 'attributes' => [] ] ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gutenberg_invalid_block_spec', $result->get_error_code() );
	}

	// ── normalize_block ───────────────────────────────────────────────────────

	public function test_normalize_block_returns_error_for_non_array(): void {
		$result = GutenbergStorage::normalize_block( 'string', 'root' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_normalize_block_returns_error_for_empty_name(): void {
		$result = GutenbergStorage::normalize_block( [ 'name' => '' ], 'root' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_normalize_block_normalizes_valid_block(): void {
		$block  = [ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'hi' ] ];
		$result = GutenbergStorage::normalize_block( $block, 'root' );
		$this->assertIsArray( $result );
		$this->assertSame( 'core/paragraph', $result['name'] );
		$this->assertSame( [ 'content' => 'hi' ], $result['attributes'] );
		$this->assertSame( [], $result['innerBlocks'] );
	}

	public function test_normalize_block_returns_error_for_non_array_attributes(): void {
		$block  = [ 'name' => 'core/paragraph', 'attributes' => 'not-array' ];
		$result = GutenbergStorage::normalize_block( $block, 'root' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_normalize_block_returns_error_for_non_array_inner_blocks(): void {
		$block  = [ 'name' => 'core/group', 'innerBlocks' => 'not-array' ];
		$result = GutenbergStorage::normalize_block( $block, 'root' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_normalize_block_recurses_into_inner_blocks(): void {
		$block = [
			'name'        => 'core/group',
			'innerBlocks' => [
				[ 'name' => 'core/paragraph' ],
			],
		];
		$result = GutenbergStorage::normalize_block( $block, 'root' );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $result['innerBlocks'][0]['name'] );
	}

	// ── top_level_block_names ─────────────────────────────────────────────────

	public function test_top_level_block_names_extracts_names(): void {
		$blocks = [
			[ 'name' => 'core/heading' ],
			[ 'name' => 'core/paragraph' ],
		];
		$this->assertSame(
			[ 'core/heading', 'core/paragraph' ],
			GutenbergStorage::top_level_block_names( $blocks )
		);
	}

	public function test_top_level_block_names_skips_empty_names(): void {
		$blocks = [
			[ 'name' => 'core/heading' ],
			[ 'name' => '' ],
		];
		$this->assertSame(
			[ 'core/heading' ],
			GutenbergStorage::top_level_block_names( $blocks )
		);
	}

	public function test_top_level_block_names_returns_empty_for_no_blocks(): void {
		$this->assertSame( [], GutenbergStorage::top_level_block_names( [] ) );
	}

	// ── block_inner_specs ─────────────────────────────────────────────────────

	public function test_block_inner_specs_returns_inner_blocks(): void {
		$inner = [ [ 'name' => 'core/paragraph' ] ];
		$block = [ 'name' => 'core/group', 'innerBlocks' => $inner ];
		$this->assertSame( $inner, GutenbergStorage::block_inner_specs( $block ) );
	}

	public function test_block_inner_specs_returns_empty_when_no_inner_blocks(): void {
		$block = [ 'name' => 'core/paragraph' ];
		$this->assertSame( [], GutenbergStorage::block_inner_specs( $block ) );
	}

	public function test_block_inner_specs_filters_non_array_entries(): void {
		$block = [ 'name' => 'core/group', 'innerBlocks' => [ 'not-array', [ 'name' => 'core/p' ] ] ];
		$result = GutenbergStorage::block_inner_specs( $block );
		$this->assertCount( 1, $result );
	}

	// ── leaf_block_names ──────────────────────────────────────────────────────

	public function test_leaf_block_names_for_flat_list(): void {
		$blocks = [
			[ 'name' => 'core/heading',   'innerBlocks' => [] ],
			[ 'name' => 'core/paragraph', 'innerBlocks' => [] ],
		];
		$this->assertSame(
			[ 'core/heading', 'core/paragraph' ],
			GutenbergStorage::leaf_block_names( $blocks )
		);
	}

	public function test_leaf_block_names_recurses_and_returns_leaves_only(): void {
		$blocks = [
			[
				'name'        => 'core/group',
				'innerBlocks' => [
					[ 'name' => 'core/paragraph', 'innerBlocks' => [] ],
				],
			],
		];
		$leaves = GutenbergStorage::leaf_block_names( $blocks );
		$this->assertSame( [ 'core/paragraph' ], $leaves );
		$this->assertNotContains( 'core/group', $leaves );
	}

	// ── blocks_are_raw_html_only ──────────────────────────────────────────────

	public function test_blocks_are_raw_html_only_true_for_html_blocks(): void {
		$blocks = [
			[ 'name' => 'core/html',     'innerBlocks' => [] ],
			[ 'name' => 'core/freeform', 'innerBlocks' => [] ],
		];
		$this->assertTrue( GutenbergStorage::blocks_are_raw_html_only( $blocks ) );
	}

	public function test_blocks_are_raw_html_only_false_for_mixed_blocks(): void {
		$blocks = [
			[ 'name' => 'core/html',      'innerBlocks' => [] ],
			[ 'name' => 'core/paragraph', 'innerBlocks' => [] ],
		];
		$this->assertFalse( GutenbergStorage::blocks_are_raw_html_only( $blocks ) );
	}

	public function test_blocks_are_raw_html_only_false_for_empty(): void {
		$this->assertFalse( GutenbergStorage::blocks_are_raw_html_only( [] ) );
	}

	// ── target_title ─────────────────────────────────────────────────────────

	public function test_target_title_returns_post_title(): void {
		$post = new \WP_Post( [ 'ID' => 5, 'post_title' => 'My Page' ] );
		$this->assertSame( 'My Page', GutenbergStorage::target_title( $post ) );
	}

	public function test_target_title_uses_fallback_for_empty_title(): void {
		$post  = new \WP_Post( [ 'ID' => 5, 'post_title' => '   ' ] );
		$title = GutenbergStorage::target_title( $post );
		$this->assertStringContainsString( '5', $title );
	}
}

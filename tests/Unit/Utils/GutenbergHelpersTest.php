<?php
/**
 * Unit tests for AllyWorker\Utils\GutenbergHelpers.
 *
 * @package AllyWorker\Tests\Unit\Utils
 */

declare( strict_types=1 );

namespace AllyWorker\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use AllyWorker\Utils\GutenbergHelpers;

/**
 * @covers \AllyWorker\Utils\GutenbergHelpers
 */
class GutenbergHelpersTest extends TestCase {

	// ── shape_blocks — empty input ────────────────────────────────────────────

	public function test_shape_blocks_empty_array_returns_empty(): void {
		$this->assertSame( [], GutenbergHelpers::shape_blocks( [] ) );
	}

	// ── shape_blocks — basic block ────────────────────────────────────────────

	public function test_shape_blocks_returns_block_name(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [ 'content' => 'Hello' ],
				'innerBlocks' => [],
				'innerHTML'   => '<p>Hello</p>',
			],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertCount( 1, $shaped );
		$this->assertSame( 'core/paragraph', $shaped[0]['name'] );
	}

	public function test_shape_blocks_includes_inner_block_count(): void {
		$blocks = [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '' ],
					[ 'blockName' => 'core/image',     'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '' ],
				],
				'innerHTML'   => '',
			],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertSame( 2, $shaped[0]['inner_block_count'] );
	}

	public function test_shape_blocks_includes_inner_html_length(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerBlocks' => [],
				'innerHTML'   => '<p>Hello</p>',
			],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertSame( strlen( '<p>Hello</p>' ), $shaped[0]['inner_html_length'] );
	}

	// ── shape_blocks — attributes ─────────────────────────────────────────────

	public function test_shape_blocks_includes_attributes_by_default(): void {
		$attrs  = [ 'level' => 2, 'content' => 'Title' ];
		$blocks = [
			[ 'blockName' => 'core/heading', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '' ],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertSame( $attrs, $shaped[0]['attributes'] );
	}

	public function test_shape_blocks_omits_attributes_when_disabled(): void {
		$blocks = [
			[ 'blockName' => 'core/heading', 'attrs' => [ 'level' => 2 ], 'innerBlocks' => [], 'innerHTML' => '' ],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks, 4, false );
		$this->assertArrayNotHasKey( 'attributes', $shaped[0] );
	}

	// ── shape_blocks — inner blocks ───────────────────────────────────────────

	public function test_shape_blocks_recurses_into_inner_blocks(): void {
		$blocks = [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'inner' ],
				],
				'innerHTML'   => '',
			],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertArrayHasKey( 'innerBlocks', $shaped[0] );
		$this->assertCount( 1, $shaped[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $shaped[0]['innerBlocks'][0]['name'] );
	}

	public function test_shape_blocks_respects_max_depth(): void {
		$inner = [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '' ];
		$blocks = [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [ $inner ],
				'innerHTML'   => '',
			],
		];
		// max_depth = 0 → no inner blocks in output.
		$shaped = GutenbergHelpers::shape_blocks( $blocks, 0 );
		$this->assertArrayNotHasKey( 'innerBlocks', $shaped[0] );
	}

	// ── shape_blocks — null blockName fallback ────────────────────────────────

	public function test_shape_blocks_uses_core_freeform_for_null_block_name(): void {
		$blocks = [
			[ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '' ],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertSame( 'core/freeform', $shaped[0]['name'] );
	}

	// ── shape_blocks — skips non-array entries ────────────────────────────────

	public function test_shape_blocks_skips_non_array_entries(): void {
		$blocks = [ 'not-an-array', null, 42 ];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertSame( [], $shaped );
	}

	// ── shape_blocks — multiple blocks ───────────────────────────────────────

	public function test_shape_blocks_shapes_multiple_blocks(): void {
		$blocks = [
			[ 'blockName' => 'core/heading',   'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'h' ],
			[ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'p' ],
		];
		$shaped = GutenbergHelpers::shape_blocks( $blocks );
		$this->assertCount( 2, $shaped );
		$this->assertSame( 'core/heading',   $shaped[0]['name'] );
		$this->assertSame( 'core/paragraph', $shaped[1]['name'] );
	}
}

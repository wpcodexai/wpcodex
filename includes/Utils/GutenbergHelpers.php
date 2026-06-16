<?php
/**
 * Gutenberg block-tree shaping helpers.
 *
 * Provides utility methods for converting WordPress's raw parse_blocks() output
 * into compact, agent-readable block trees.
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Utils;

/**
 * Class GutenbergHelpers
 *
 * Static utility class for Gutenberg block tree operations.
 *
 * @since 1.0.0
 */
class GutenbergHelpers {

	/**
	 * Shape a list of parsed blocks (from parse_blocks()) into a compact, agent-readable tree.
	 *
	 * Recursively walks innerBlocks up to $max_depth levels. Each entry in the
	 * returned array includes name, inner_block_count, inner_html_length, and
	 * optionally attributes and innerBlocks.
	 *
	 * @since 1.0.0
	 * @param array<int, array<string, mixed>> $parsed_blocks Raw blocks from parse_blocks().
	 * @param int                              $max_depth         Maximum innerBlocks depth to include. Default 4.
	 * @param bool                             $include_attributes Whether to include block attributes. Default true.
	 * @param int                              $depth             Current recursion depth (internal use). Default 0.
	 * @return array<int, array<string, mixed>> Compact shaped block tree.
	 */
	public static function shape_blocks(
		array $parsed_blocks,
		int $max_depth = 4,
		bool $include_attributes = true,
		int $depth = 0
	): array {
		$shaped = [];

		foreach ( $parsed_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name         = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : 'core/freeform';
			$inner_blocks = is_array( $block['innerBlocks'] ?? null ) ? array_values( $block['innerBlocks'] ) : [];

			$entry = [
				'name'              => $name,
				'inner_block_count' => count( $inner_blocks ),
				'inner_html_length' => is_string( $block['innerHTML'] ?? null ) ? strlen( $block['innerHTML'] ) : 0,
			];

			if ( $include_attributes ) {
				$entry['attributes'] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
			}

			if ( $inner_blocks !== [] && $depth < $max_depth ) {
				$entry['innerBlocks'] = self::shape_blocks( $inner_blocks, $max_depth, $include_attributes, $depth + 1 );
			}

			$shaped[] = $entry;
		}

		return $shaped;
	}
}

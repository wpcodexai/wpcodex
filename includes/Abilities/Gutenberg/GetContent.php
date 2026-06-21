<?php
/**
 * Ability: allyworker/gutenberg-get-content
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Gutenberg;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\Utils\GutenbergHelpers;
use AllyWorker\Utils\GutenbergStorage;

/**
 * Class GetContent
 *
 * Reads the live saved Gutenberg post_content and returns a compact parsed block tree.
 *
 * @since 1.0.0
 */
class GetContent extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-gutenberg';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/gutenberg-get-content';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Get Gutenberg Content', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Reads the live saved Gutenberg post_content for a target post and returns a compact parsed block tree. Use before queuing or writing block changes.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'             => [
					'type'        => 'integer',
					'description' => 'Target post/page/template ID.',
				],
				'max_depth'           => [
					'type'        => 'integer',
					'description' => 'Maximum innerBlocks depth to include in the compact tree. Default 4.',
					'default'     => 4,
				],
				'include_attributes'  => [
					'type'        => 'boolean',
					'description' => 'Whether to include block attributes. Default true.',
					'default'     => true,
				],
				'include_raw_content' => [
					'type'        => 'boolean',
					'description' => 'Whether to include raw post_content. Can be large; default false.',
					'default'     => false,
				],
			],
			'required' => [ 'post_id' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'     => [ 'type' => 'integer' ],
				'post_type'   => [ 'type' => 'string' ],
				'post_title'  => [ 'type' => 'string' ],
				'blocks'      => [ 'type' => 'array' ],
				'block_count' => [ 'type' => 'integer' ],
				'raw_content' => [ 'type' => 'string' ],
				'pending'     => [ 'type' => 'object' ],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return 'Read this before writing or queueing Gutenberg block changes. The returned compact block tree shows current block names, attributes, and nesting so you can plan an accurate replacement. The pending field warns when a queued change exists for this target.';
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'allyworker_invalid_input', __( 'post_id must be a positive integer.', 'allyworker' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error(
				'allyworker_not_found',
				/* translators: %d post ID */
				sprintf( __( 'Post %d was not found.', 'allyworker' ), $post_id )
			);
		}

		$max_depth          = max( 0, min( 12, (int) ( $input['max_depth'] ?? 4 ) ) );
		$include_attributes = ! array_key_exists( 'include_attributes', $input ) || true === $input['include_attributes'];

		$parsed_blocks = parse_blocks( $post->post_content );
		$blocks        = GutenbergHelpers::shape_blocks( $parsed_blocks, $max_depth, $include_attributes );

		$response = [
			'post_id'     => $post->ID,
			'post_type'   => $post->post_type,
			'post_title'  => $post->post_title,
			'blocks'      => $blocks,
			'block_count' => count( $blocks ),
			'pending'     => GutenbergStorage::pending_summary_for_target( $post->ID, $post->post_type ),
		];

		if ( array_key_exists( 'include_raw_content', $input ) && true === $input['include_raw_content'] ) {
			$response['raw_content'] = $post->post_content;
		}

		return $response;
	}
}

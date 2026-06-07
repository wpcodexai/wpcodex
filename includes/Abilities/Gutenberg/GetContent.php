<?php
/**
 * Ability: wpcodex/gutenberg-get-content
 *
 * @package WPCodex\Abilities\Gutenberg
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Gutenberg;

use WPCodex\Utils\GutenbergHelpers;
use WPCodex\Utils\GutenbergStorage;
use WPCodex\Utils\Helpers;

/**
 * Class GetContent
 *
 * Reads the live saved Gutenberg post_content and returns a compact parsed block tree.
 *
 * @since 1.0.0
 */
class GetContent {

	/**
	 * Register the wpcodex/register_abilities hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	/**
	 * Register the ability with the WordPress Abilities API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		wp_register_ability( 'wpcodex/gutenberg-get-content', [
			'label'       => __( 'Get Gutenberg Content', 'wpcodex' ),
			'description' => __( 'Reads the live saved Gutenberg post_content for a target post and returns a compact parsed block tree. Use before queuing or writing block changes.', 'wpcodex' ),
			'category'    => 'wpcodex-gutenberg',

			'input_schema' => [
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
			],

			'output_schema' => [
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
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
				if ( $post_id <= 0 ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'post_id must be a positive integer.', 'wpcodex' ) );
				}

				$post = get_post( $post_id );
				if ( ! $post instanceof \WP_Post ) {
					return new \WP_Error(
						'wpcodex_not_found',
						/* translators: %d post ID */
						sprintf( __( 'Post %d was not found.', 'wpcodex' ), $post_id )
					);
				}

				$max_depth          = max( 0, min( 12, (int) ( $args['max_depth'] ?? 4 ) ) );
				$include_attributes = ! array_key_exists( 'include_attributes', $args ) || true === $args['include_attributes'];

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

				if ( array_key_exists( 'include_raw_content', $args ) && true === $args['include_raw_content'] ) {
					$response['raw_content'] = $post->post_content;
				}

				return $response;
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Read this before writing or queueing Gutenberg block changes. The returned compact block tree shows current block names, attributes, and nesting so you can plan an accurate replacement. The pending field warns when a queued change exists for this target.',
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

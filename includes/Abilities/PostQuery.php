<?php
/**
 * Ability: wpcodex/post-query
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities;

use WPCodex\Utils\Helpers;

class PostQuery {

	public static function init(): void {
		wp_register_ability( 'wpcodex/post-query', [
			'label'       => __( 'Query Posts', 'wpcodex' ),
			'description' => __(
				'Query posts using WP_Query. Pass a query_args object following the WP_Query parameter reference. Returns found_posts count and an array of post summaries.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'query_args' => [
						'type'        => 'object',
						'description' => 'WP_Query arguments. Example: {"post_type":"page","post_status":"publish","posts_per_page":10}',
					],
				],
				'required'   => [ 'query_args' ],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'JSON with found_posts (int) and posts (array of post summaries).',
			],

			'execute_callback' => static function ( array $args ): string|\WP_Error {
				if ( ! isset( $args['query_args'] ) || ! is_array( $args['query_args'] ) ) {
					return new \WP_Error( 'wpcodex_invalid_input', __( 'query_args must be an object.', 'wpcodex' ) );
				}

				$query_args = $args['query_args'];
				$query_args['update_post_meta_cache'] = false;
				$query_args['update_post_term_cache'] = false;

				$query   = new \WP_Query( $query_args );
				$results = array_map( static function ( \WP_Post $post ): array {
					return [
						'ID'        => $post->ID,
						'title'     => $post->post_title,
						'slug'      => $post->post_name,
						'status'    => $post->post_status,
						'type'      => $post->post_type,
						'date'      => $post->post_date,
						'modified'  => $post->post_modified,
						'parent'    => $post->post_parent,
						'permalink' => get_permalink( $post->ID ),
					];
				}, $query->posts );

				wp_reset_postdata();

				return wp_json_encode( [
					'found_posts' => $query->found_posts,
					'posts'       => $results,
				], JSON_PRETTY_PRINT ) ?: '{}';
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

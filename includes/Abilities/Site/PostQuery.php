<?php
/**
 * Ability: wpcodex/post-query
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Abilities\AbstractAbility;

/**
 * Class PostQuery
 *
 * @since 1.0.0
 */
class PostQuery extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/post-query';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Query Posts', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Query posts using WP_Query. Pass a query_args object following the WP_Query parameter reference. Returns found_posts count and an array of post summaries.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'query_args' => [
					'type'        => 'object',
					'description' => 'WP_Query arguments. Example: {"post_type":"page","post_status":"publish","posts_per_page":10}',
				],
			],
			'required'   => [ 'query_args' ],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON with found_posts (int) and posts (array of post summaries).',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( ! isset( $input['query_args'] ) || ! is_array( $input['query_args'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'query_args must be an object.', 'wpcodex' ) );
		}

		$query_args                          = $input['query_args'];
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
	}
}

<?php
/**
 * Ability: wpworker/astra-get-page-settings
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Abilities\Themes\Astra;

use WPWorker\Abilities\AbstractAbility;

/**
 * Class GetPageSettings
 *
 * Read all Astra per-post/page meta settings for a given post ID (or post title).
 * These override the global astra-settings on a per-post basis.
 *
 * Meta keys match Astra's own astra/get-post-meta ability exactly.
 *
 * @since 1.0.0
 */
class GetPageSettings extends AbstractAbility {

	/**
	 * Astra post-meta keys that control per-page layout and appearance.
	 *
	 * Sourced from Astra's Astra_Get_Postmeta::execute() implementation.
	 *
	 * @var list<string>
	 */
	public const META_KEYS = [
		'site-post-title',
		'ast-site-content-layout',
		'site-content-style',
		'site-sidebar-layout',
		'site-sidebar-style',
		'ast-global-header-display',
		'footer-sml-layout',
		'ast-banner-title-visibility',
		'ast-breadcrumbs-content',
		'ast-hfb-above-header-display',
		'ast-main-header-display',
		'ast-hfb-below-header-display',
		'ast-hfb-mobile-header-display',
		'theme-transparent-header-meta',
	];

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpworker/astra-get-page-settings';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Astra: Get Page Settings', 'worker-ai' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Read Astra per-page/post meta settings for a specific post ID or post title. '
			. 'These settings override the global astra-settings for that page only. '
			. 'Returns an object with all Astra meta keys and their current values.',
			'worker-ai'
		);
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpworker-themes';
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return <<<'INSTR'
Astra per-page meta keys and their valid values:

  site-post-title               "" (default) | "disabled"
  ast-site-content-layout       "" (default) | "normal-width-container" | "narrow-width-container" | "full-width-container"
  site-content-style            "" (default) | "unboxed" | "boxed"
  site-sidebar-layout           "" (default) | "no-sidebar" | "left-sidebar" | "right-sidebar"
  site-sidebar-style            "" (default) | "unboxed" | "boxed"
  ast-global-header-display     "" (default) | "disabled"
  footer-sml-layout             "" (default) | "disabled"
  ast-banner-title-visibility   "" (default) | "disabled"
  ast-breadcrumbs-content       "" (default) | "disabled"
  ast-hfb-above-header-display  "" (default) | "disabled"
  ast-main-header-display       "" (default) | "disabled"
  ast-hfb-below-header-display  "" (default) | "disabled"
  ast-hfb-mobile-header-display "" (default) | "disabled"
  theme-transparent-header-meta "" (default) | "enabled" | "disabled"

Empty string "" means "inherit from global customizer settings".
INSTR;
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'post_id'    => [
					'type'        => 'integer',
					'description' => 'WordPress post/page ID. Either post_id or post_title is required.',
				],
				'post_title' => [
					'type'        => 'string',
					'description' => 'Post title to look up (e.g. "Sample Page", "About"). Either post_id or post_title is required.',
				],
			],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON object of Astra meta key→value pairs. Empty string means "inherit global setting".',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string|\WP_Error {
		if ( ! GetSettings::astra_is_active() ) {
			return new \WP_Error( 'wpworker_astra_inactive', __( 'The Astra theme is not currently active.', 'worker-ai' ) );
		}

		$post_id = $this->resolve_post_id( $input );
		if ( $post_id instanceof \WP_Error ) {
			return $post_id;
		}

		$result = [];
		foreach ( self::META_KEYS as $key ) {
			/** @var mixed $value */
			$value          = get_post_meta( $post_id, $key, true );
			$result[ $key ] = ( $value !== false ) ? $value : '';
		}

		return wp_json_encode( $result, JSON_PRETTY_PRINT ) ?: '{}';
	}

	/**
	 * Resolve post_id from input (by ID or by title lookup).
	 *
	 * @param  array<string, mixed> $input
	 * @return int|\WP_Error
	 */
	private function resolve_post_id( array $input ): int|\WP_Error {
		if ( isset( $input['post_id'] ) && is_scalar( $input['post_id'] ) ) {
			$id = (int) $input['post_id'];
			if ( $id <= 0 ) {
				return new \WP_Error( 'wpworker_invalid_input', __( 'post_id must be a positive integer.', 'worker-ai' ) );
			}
			if ( ! get_post( $id ) ) {
				return new \WP_Error(
					'wpworker_post_not_found',
					/* translators: %d: post ID */
					sprintf( __( 'Post %d not found.', 'worker-ai' ), $id )
				);
			}
			return $id;
		}

		if ( isset( $input['post_title'] ) && is_string( $input['post_title'] ) && $input['post_title'] !== '' ) {
			$title = sanitize_text_field( $input['post_title'] );
			$query = new \WP_Query( [
				'title'          => $title,
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			] );
			if ( empty( $query->posts ) ) {
				return new \WP_Error(
					'wpworker_post_not_found',
					/* translators: %s: post title */
					sprintf( __( 'No post found with title "%s".', 'worker-ai' ), $title )
				);
			}
			return (int) $query->posts[0];
		}

		return new \WP_Error( 'wpworker_invalid_input', __( 'Provide either post_id or post_title.', 'worker-ai' ) );
	}
}

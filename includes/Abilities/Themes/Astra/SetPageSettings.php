<?php
/**
 * Ability: wpcodex/astra-set-page-settings
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Themes\Astra;

use WPCodex\Abilities\AbstractAbility;

/**
 * Class SetPageSettings
 *
 * Write Astra per-post/page meta settings for a given post ID or post title.
 * Empty string value = revert to global default (key is deleted from post meta).
 *
 * Valid keys and values match Astra's own astra/update-post-meta ability exactly.
 *
 * @since 1.0.0
 */
class SetPageSettings extends AbstractAbility {

	/**
	 * Astra post-meta keys that are valid to write.
	 *
	 * @var list<string>
	 */
	public const VALID_KEYS = [
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
		return 'wpcodex/astra-set-page-settings';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Astra: Set Page Settings', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Write Astra per-page/post meta settings for a specific post ID or post title. '
			. 'These settings override the global astra-settings for that page only. '
			. 'Pass an empty string "" as a value to delete that meta key and revert to the global default.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-astra';
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return <<<'INSTR'
Valid Astra meta keys and their accepted values:

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

Pass "" (empty string) to revert any key back to the global customizer setting.

Example — hide the sidebar on the About page:
  { "post_title": "About", "settings": { "site-sidebar-layout": "no-sidebar" } }

Example — make Sample Page full-width:
  { "post_title": "Sample Page", "settings": { "ast-site-content-layout": "full-width-container" } }

Example — revert all layout overrides on post ID 42:
  { "post_id": 42, "settings": { "site-sidebar-layout": "", "ast-site-content-layout": "" } }
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
				'settings'   => [
					'type'                 => 'object',
					'description'          => 'Astra meta key→value pairs. Pass "" to delete a key (revert to global default).',
					'additionalProperties' => [ 'type' => 'string' ],
				],
			],
			'required'             => [ 'settings' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'set'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Keys written.' ],
				'deleted' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Keys deleted (reverted to global default).' ],
				'skipped' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Keys skipped — not a valid Astra meta key.' ],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( ! GetSettings::astra_is_active() ) {
			return new \WP_Error( 'wpcodex_astra_inactive', __( 'The Astra theme is not currently active.', 'wpcodex' ) );
		}

		$post_id = $this->resolve_post_id( $input );
		if ( $post_id instanceof \WP_Error ) {
			return $post_id;
		}

		if ( ! isset( $input['settings'] ) || ! is_array( $input['settings'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'settings must be an object.', 'wpcodex' ) );
		}

		$set     = [];
		$deleted = [];
		$skipped = [];

		foreach ( $input['settings'] as $key => $value ) {
			if ( ! is_string( $key ) || $key === '' ) {
				$skipped[] = (string) $key;
				continue;
			}

			if ( ! in_array( $key, self::VALID_KEYS, true ) ) {
				$skipped[] = $key;
				continue;
			}

			$safe_value = is_string( $value ) ? sanitize_text_field( $value ) : '';

			if ( $safe_value === '' ) {
				delete_post_meta( $post_id, $key );
				$deleted[] = $key;
			} else {
				update_post_meta( $post_id, $key, $safe_value );
				$set[] = $key;
			}
		}

		return [
			'set'     => $set,
			'deleted' => $deleted,
			'skipped' => $skipped,
		];
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
				return new \WP_Error( 'wpcodex_invalid_input', __( 'post_id must be a positive integer.', 'wpcodex' ) );
			}
			if ( ! get_post( $id ) ) {
				return new \WP_Error(
					'wpcodex_post_not_found',
					/* translators: %d: post ID */
					sprintf( __( 'Post %d not found.', 'wpcodex' ), $id )
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
					'wpcodex_post_not_found',
					/* translators: %s: post title */
					sprintf( __( 'No post found with title "%s".', 'wpcodex' ), $title )
				);
			}
			return (int) $query->posts[0];
		}

		return new \WP_Error( 'wpcodex_invalid_input', __( 'Provide either post_id or post_title.', 'wpcodex' ) );
	}
}

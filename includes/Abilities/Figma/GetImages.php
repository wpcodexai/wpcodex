<?php
/**
 * Ability: wpcodex/figma-get-images
 *
 * @package WPCodex\Abilities\Figma
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Figma;

use WPCodex\Runner\FigmaClient;
use WPCodex\Utils\Helpers;

/**
 * Class GetImages
 */
class GetImages {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! FigmaClient::is_connected() ) {
			return;
		}

		wp_register_ability( 'wpcodex/figma-get-images', [
			'label'       => __( 'Figma: Get Images', 'wpcodex' ),
			'description' => __( 'Get rendered image URLs for one or more Figma nodes. Returns temporary CDN URLs (expire after ~30 days) in PNG, JPG, SVG, or PDF format. Use to export assets or take a visual snapshot of a frame.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'file_key' => [
						'type'        => 'string',
						'description' => 'Figma file key from the file URL.',
					],
					'node_ids' => [
						'type'        => 'string',
						'description' => 'Comma-separated list of node IDs to render, e.g. "10:25,10:30".',
					],
					'format' => [
						'type'        => 'string',
						'enum'        => [ 'png', 'jpg', 'svg', 'pdf' ],
						'description' => 'Image format (default: png).',
						'default'     => 'png',
					],
					'scale' => [
						'type'        => 'number',
						'description' => 'Render scale factor between 0.01 and 4 (default: 1). Use 2 for @2x retina.',
						'default'     => 1.0,
						'minimum'     => 0.01,
						'maximum'     => 4.0,
					],
				],
				'required'             => [ 'file_key', 'node_ids' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'images'  => [
						'type'                 => 'object',
						'description'          => 'Map of node ID → image URL. URLs are temporary Figma CDN links.',
						'additionalProperties' => [ 'type' => 'string' ],
					],
					'err' => [
						'type'        => 'object',
						'description' => 'Map of node IDs that could not be rendered to their error reason.',
					],
				],
				'required' => [ 'success' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$file_key = sanitize_text_field( $args['file_key'] ?? '' );
				$node_ids = sanitize_text_field( $args['node_ids'] ?? '' );

				if ( '' === $file_key || '' === $node_ids ) {
					return new \WP_Error( 'wpcodex_figma_invalid_input', __( 'file_key and node_ids are required.', 'wpcodex' ) );
				}

				$format = in_array( $args['format'] ?? 'png', [ 'png', 'jpg', 'svg', 'pdf' ], true )
					? (string) $args['format']
					: 'png';

				$scale  = isset( $args['scale'] ) ? (float) $args['scale'] : 1.0;
				$scale  = max( 0.01, min( 4.0, $scale ) );

				$result = FigmaClient::instance()->get_images( $file_key, $node_ids, $format, $scale );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array_merge( [ 'success' => true ], $result );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Use this to get a visual preview of a frame or to export assets. Image URLs expire — do not store them long-term; fetch fresh URLs when needed. For SVG exports (icons, illustrations) use format=svg. For photography/photos use format=jpg. URLs point to Figma\'s CDN and can be downloaded directly.',
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

<?php
/**
 * Ability: wpcodex/figma-get-node
 *
 * @package WPCodex\Abilities\Figma
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Figma;

use WPCodex\Runner\FigmaClient;
use WPCodex\Utils\Helpers;

/**
 * Class GetNode
 */
class GetNode {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! FigmaClient::is_connected() ) {
			return;
		}

		wp_register_ability( 'wpcodex/figma-get-node', [
			'label'       => __( 'Figma: Get Node', 'wpcodex' ),
			'description' => __( 'Fetch a specific frame, component, or element from a Figma file by node ID. Returns the full subtree including layout, fills, typography, and constraints. Prefer this over figma-get-file when working on a single screen or component.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'file_key' => [
						'type'        => 'string',
						'description' => 'Figma file key from the file URL.',
					],
					'node_id' => [
						'type'        => 'string',
						'description' => 'Node ID to fetch, e.g. "10:25". Copy via right-click → Copy link to selection — the node-id= parameter in the URL is the value you need.',
					],
					'depth' => [
						'type'        => 'integer',
						'description' => 'Tree depth to return (default 5). Higher values include deeper nested elements.',
						'default'     => 5,
						'minimum'     => 1,
						'maximum'     => 20,
					],
				],
				'required'             => [ 'file_key', 'node_id' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'nodes'   => [
						'type'        => 'object',
						'description' => 'Map of node ID → node data. Each node contains type, name, children, absoluteBoundingBox, fills, strokes, effects, style, and more.',
					],
				],
				'required' => [ 'success' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$file_key = sanitize_text_field( $args['file_key'] ?? '' );
				$node_id  = sanitize_text_field( $args['node_id'] ?? '' );

				if ( '' === $file_key || '' === $node_id ) {
					return new \WP_Error( 'wpcodex_figma_invalid_input', __( 'file_key and node_id are required.', 'wpcodex' ) );
				}

				$depth  = isset( $args['depth'] ) ? (int) $args['depth'] : 5;
				$result = FigmaClient::instance()->get_node( $file_key, $node_id, $depth );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array_merge( [ 'success' => true ], $result );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Get the node ID from a Figma selection link: right-click a frame → Copy link to selection → extract the node-id= query parameter (e.g. "10:25"). The response nodes object is keyed by node ID. Check absoluteBoundingBox for dimensions, fills for colors, style.fontFamily / style.fontSize for typography.',
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

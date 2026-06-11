<?php
/**
 * Ability: wpcodex/figma-get-file
 *
 * @package WPCodex\Abilities\Figma
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Figma;

use WPCodex\Runner\FigmaClient;
use WPCodex\Utils\Helpers;

/**
 * Class GetFile
 */
class GetFile {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! FigmaClient::is_connected() ) {
			return;
		}

		wp_register_ability( 'wpcodex/figma-get-file', [
			'label'       => __( 'Figma: Get File', 'wpcodex' ),
			'description' => __( 'Fetch the full node tree of a Figma file by its file key. Returns document structure, components, styles, and metadata. Use the file key from the Figma URL (the segment between /design/ and the file name).', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'file_key' => [
						'type'        => 'string',
						'description' => 'Figma file key — the alphanumeric segment from the file URL, e.g. "ABC123XYZ" from https://www.figma.com/design/ABC123XYZ/My-Design.',
					],
					'depth' => [
						'type'        => 'integer',
						'description' => 'How deep to traverse the document tree (default 2). Increase for more detail; lower for a quick overview.',
						'default'     => 2,
						'minimum'     => 1,
						'maximum'     => 10,
					],
				],
				'required'             => [ 'file_key' ],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'  => [ 'type' => 'boolean' ],
					'document' => [ 'type' => 'object', 'description' => 'The root document node with its child tree.' ],
					'name'     => [ 'type' => 'string', 'description' => 'File name.' ],
					'styles'   => [ 'type' => 'object', 'description' => 'Named styles defined in the file.' ],
					'components' => [ 'type' => 'object', 'description' => 'Components defined in the file.' ],
				],
				'required' => [ 'success' ],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$file_key = sanitize_text_field( $args['file_key'] ?? '' );
				if ( '' === $file_key ) {
					return new \WP_Error( 'wpcodex_figma_invalid_input', __( 'file_key must be a non-empty string.', 'wpcodex' ) );
				}

				$depth  = isset( $args['depth'] ) ? (int) $args['depth'] : 2;
				$result = FigmaClient::instance()->get_file( $file_key, $depth );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array_merge( [ 'success' => true ], $result );
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => 'Extract the file key from a Figma URL: https://www.figma.com/design/FILE_KEY/name?... — take only the FILE_KEY segment. Use depth=2 for a quick structural overview, depth=5+ when you need layout details. For a specific frame use wpcodex/figma-get-node instead.',
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

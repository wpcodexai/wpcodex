<?php
/**
 * Ability: wpcodex/create-upload-link
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Files;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\REST\UploadEndpoint;

/**
 * Class CreateUploadLink
 *
 * @since 1.0.0
 */
class CreateUploadLink extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'wpcodex-general';
	}

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'wpcodex/create-upload-link';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Create Upload Link', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Creates a temporary upload endpoint and header-only bearer token that external tools (e.g. curl) can use to upload one file into the WordPress filesystem. Accepts raw PUT/POST bodies and multipart/form-data with a field named "file".', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'path'               => [
					'type'        => 'string',
					'description' => 'Destination file path. Relative paths are resolved from the WordPress root (ABSPATH).',
					'minLength'   => 1,
				],
				'expires_in'         => [
					'type'        => 'integer',
					'description' => 'Seconds before the upload token expires. Minimum 30, maximum 3600.',
					'default'     => 900,
					'minimum'     => 30,
					'maximum'     => 3600,
				],
				'max_bytes'          => [
					'type'        => 'integer',
					'description' => 'Maximum upload size in bytes accepted by this endpoint. Default is 536870912 (512 MiB).',
					'default'     => 536_870_912,
					'minimum'     => 1,
				],
				'overwrite'          => [
					'type'        => 'boolean',
					'description' => 'Whether the upload may replace an existing destination file.',
					'default'     => false,
				],
				'create_directories' => [
					'type'        => 'boolean',
					'description' => 'Whether to create parent directories if they do not exist.',
					'default'     => true,
				],
			],
			'required'             => [ 'path' ],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'upload_url'    => [ 'type' => 'string', 'description' => 'Temporary upload endpoint URL.' ],
				'upload_token'  => [ 'type' => 'string', 'description' => 'Temporary bearer token. Send as the token_header value.' ],
				'token_header'  => [ 'type' => 'string', 'description' => 'HTTP header that must carry upload_token.' ],
				'method'        => [ 'type' => 'string', 'description' => 'Recommended HTTP method.' ],
				'path'          => [ 'type' => 'string', 'description' => 'Absolute destination path.' ],
				'expires_at'    => [ 'type' => 'integer', 'description' => 'Unix timestamp when the upload token expires.' ],
				'max_bytes'     => [ 'type' => 'integer', 'description' => 'Maximum upload size accepted by the endpoint.' ],
				'overwrite'     => [ 'type' => 'boolean', 'description' => 'Whether existing files may be replaced.' ],
				'curl_examples' => [
					'type'        => 'array',
					'description' => 'Example curl commands. Replace /path/to/local-file with the local file to upload.',
					'items'       => [ 'type' => 'string' ],
				],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ];
	}

	/** {@inheritDoc} */
	public function get_instructions(): string {
		return implode( "\n", [
			'Use this when a file is too large or inconvenient to send through the MCP JSON transport.',
			'Recommended curl form: curl -X PUT -H "$token_header: $upload_token" --data-binary @/path/to/local-file "$upload_url"',
			'Multipart form is also accepted: curl -H "$token_header: $upload_token" -F file=@/path/to/local-file "$upload_url"',
			'PHP files (*.php) and PHP execution control files can ONLY be uploaded to wp-content/wpcodex-sandbox/.',
			'Other non-PHP uploads outside the sandbox are intentional; the sandbox is not security isolation for all filesystem writes.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		if ( empty( $input['path'] ) || ! is_string( $input['path'] ) ) {
			return new \WP_Error( 'wpcodex_invalid_input', __( 'path must be a non-empty string.', 'wpcodex' ) );
		}

		// Resolve and validate the destination path.
		$path = (string) $input['path'];
		if ( ! str_starts_with( $path, '/' ) && ! str_starts_with( $path, '\\' )
			&& ! ( strlen( $path ) >= 2 && ':' === $path[1] ) ) {
			$path = ABSPATH . $path;
		}

		// Quick sandbox check for PHP files before signing the token.
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'php' === $extension || in_array( strtolower( basename( $path ) ), [ '.htaccess', '.php.ini', '.user.ini', 'php.ini', 'web.config' ], true ) ) {
			$sandbox   = WPCODEX_SANDBOX_DIR;
			$norm_sand = rtrim( str_replace( '\\', '/', $sandbox ), '/' );
			$norm_path = str_replace( '\\', '/', dirname( $path ) );
			if ( ! str_starts_with( $norm_path, $norm_sand ) ) {
				return new \WP_Error(
					'php_sandbox_required',
					sprintf(
						/* translators: %s sandbox directory path */
						__( 'PHP files and PHP execution control files can only be uploaded to the sandbox directory: %s.', 'wpcodex' ),
						$sandbox
					)
				);
			}
		}

		$expires_in         = max( 30, min( 3600, (int) ( $input['expires_in'] ?? 900 ) ) );
		$max_bytes          = max( 1, (int) ( $input['max_bytes'] ?? 536_870_912 ) );
		$expires_at         = time() + $expires_in;
		$overwrite          = true === ( $input['overwrite'] ?? false );
		$create_directories = false !== ( $input['create_directories'] ?? true );

		$payload = [
			'path'               => $path,
			'expires_at'         => $expires_at,
			'max_bytes'          => $max_bytes,
			'overwrite'          => $overwrite,
			'create_directories' => $create_directories,
		];

		$token = UploadEndpoint::sign_payload( $payload );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$upload_url   = rest_url( 'wpcodex/v1/upload' );
		$token_header = 'X-WPCodex-Upload-Token';

		return [
			'upload_url'    => $upload_url,
			'upload_token'  => $token,
			'token_header'  => $token_header,
			'method'        => 'PUT',
			'path'          => $path,
			'expires_at'    => $expires_at,
			'max_bytes'     => $max_bytes,
			'overwrite'     => $overwrite,
			'curl_examples' => [
				sprintf(
					'curl -X PUT -H "%s: $upload_token" --data-binary @/path/to/local-file %s',
					$token_header,
					escapeshellarg( $upload_url )
				),
				sprintf(
					'curl -H "%s: $upload_token" -F file=@/path/to/local-file %s',
					$token_header,
					escapeshellarg( $upload_url )
				),
			],
		];
	}
}

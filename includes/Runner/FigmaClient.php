<?php
/**
 * Figma REST API client.
 *
 * Wraps wp_remote_get() calls to api.figma.com using the Personal Access
 * Token stored in the wpcodex_figma_token option.
 *
 * @package WPCodex\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Runner;

/**
 * Class FigmaClient
 */
class FigmaClient {

	/** Figma REST API base URL. */
	private const API_BASE = 'https://api.figma.com/v1';

	/** Option keys. */
	public const OPTION_TOKEN   = 'wpcodex_figma_token';
	public const OPTION_ENABLED = 'wpcodex_figma_enabled';
	public const OPTION_USER    = 'wpcodex_figma_user';

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Whether Figma integration is enabled and a token is stored.
	 */
	public static function is_connected(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false )
			&& '' !== (string) get_option( self::OPTION_TOKEN, '' );
	}

	/**
	 * Verify a token against the Figma /me endpoint.
	 * Returns user data array on success, WP_Error on failure.
	 *
	 * @param string $token Personal Access Token to test.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function verify_token( string $token ): array|\WP_Error {
		return $this->get( '/me', [], $token );
	}

	/**
	 * Get the full node tree for a Figma file.
	 *
	 * @param string $file_key Figma file key (from file URL).
	 * @param int    $depth    Tree depth (default 2).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_file( string $file_key, int $depth = 2 ): array|\WP_Error {
		return $this->get( '/files/' . rawurlencode( $file_key ), [ 'depth' => $depth ] );
	}

	/**
	 * Get a specific node by file key and node ID.
	 *
	 * @param string $file_key Figma file key.
	 * @param string $node_id  Node ID (e.g. "10:25").
	 * @param int    $depth    Tree depth (default 5).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_node( string $file_key, string $node_id, int $depth = 5 ): array|\WP_Error {
		return $this->get(
			'/files/' . rawurlencode( $file_key ) . '/nodes',
			[
				'ids'   => $node_id,
				'depth' => $depth,
			]
		);
	}

	/**
	 * Get rendered image URLs for one or more nodes.
	 *
	 * @param string $file_key Figma file key.
	 * @param string $node_ids Comma-separated node IDs.
	 * @param string $format   Image format: png|jpg|svg|pdf (default png).
	 * @param float  $scale    Scale factor 0.01–4 (default 1).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_images( string $file_key, string $node_ids, string $format = 'png', float $scale = 1.0 ): array|\WP_Error {
		return $this->get(
			'/images/' . rawurlencode( $file_key ),
			[
				'ids'    => $node_ids,
				'format' => $format,
				'scale'  => $scale,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Internal HTTP layer
	// -------------------------------------------------------------------------

	/**
	 * Make an authenticated GET request to the Figma API.
	 *
	 * @param string               $path    API path (leading slash).
	 * @param array<string, mixed> $params  Query parameters.
	 * @param string|null          $token   Token override (for verification before save).
	 * @return array<string, mixed>|\WP_Error
	 */
	private function get( string $path, array $params = [], ?string $token = null ): array|\WP_Error {
		$token = $token ?? (string) get_option( self::OPTION_TOKEN, '' );

		if ( '' === $token ) {
			return new \WP_Error( 'wpcodex_figma_no_token', __( 'No Figma token configured. Enable Figma integration and connect your account.', 'wpcodex' ) );
		}

		$url = self::API_BASE . $path;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'X-Figma-Token' => $token,
					'Accept'        => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'wpcodex_figma_invalid_response',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Figma API returned an unexpected response (HTTP %d).', 'wpcodex' ), $code )
			);
		}

		if ( (int) $code === 403 || (int) $code === 401 ) {
			return new \WP_Error(
				'wpcodex_figma_auth',
				__( 'Figma token is invalid or expired. Please reconnect.', 'wpcodex' )
			);
		}

		if ( (int) $code >= 400 ) {
			$message = isset( $data['message'] ) && is_string( $data['message'] )
				? $data['message']
				/* translators: %d: HTTP status code */
				: sprintf( __( 'Figma API error (HTTP %d).', 'wpcodex' ), $code );

			return new \WP_Error( 'wpcodex_figma_api_error', $message );
		}

		return $data;
	}
}

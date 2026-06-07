<?php
/**
 * Ability: wpcodex/create-admin-access-link
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\REST\AdminAccessEndpoint;
use WPCodex\Utils\Helpers;

/**
 * Class CreateAdminAccessLink
 */
class CreateAdminAccessLink {

	public function __construct() {
		add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
	}

	public function init(): void {
		wp_register_ability( 'wpcodex/create-admin-access-link', [
			'label'       => __( 'Create Admin Access Link', 'wpcodex' ),
			'description' => __( 'Creates a temporary one-time admin session exchange for browser automation tools (e.g. Claude in Chrome). The tool POSTs the token and nonce to the exchange URL, receives a short-lived login URL, opens it in the browser, and is redirected into wp-admin without needing credentials.', 'wpcodex' ),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'                 => 'object',
				'properties'           => [
					'user_id'             => [
						'type'        => 'integer',
						'description' => 'WordPress user ID of the admin to log in as. Defaults to the current user.',
					],
					'expires_in'          => [
						'type'        => 'integer',
						'description' => 'Seconds before the access token expires (30–3600). Default 300.',
						'default'     => 300,
						'minimum'     => 30,
						'maximum'     => 3600,
					],
					'session_expires_in'  => [
						'type'        => 'integer',
						'description' => 'WordPress session lifetime in seconds after login (60–3600). Default 1800.',
						'default'     => 1800,
						'minimum'     => 60,
						'maximum'     => 3600,
					],
					'admin_path'          => [
						'type'        => 'string',
						'description' => 'Admin path to redirect to after login. Relative to wp-admin/. Default: empty (wp-admin home).',
						'default'     => '',
					],
				],
				'additionalProperties' => false,
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'exchange_url'   => [ 'type' => 'string', 'description' => 'POST this URL with access_token in token_header and access_nonce in nonce_header to receive a login_url.' ],
					'access_token'   => [ 'type' => 'string', 'description' => 'One-time bearer token. Send as the token_header value.' ],
					'token_header'   => [ 'type' => 'string', 'description' => 'HTTP header that must carry access_token.' ],
					'access_nonce'   => [ 'type' => 'string', 'description' => 'Binding nonce. Send as the nonce_header value.' ],
					'nonce_header'   => [ 'type' => 'string', 'description' => 'HTTP header that must carry access_nonce.' ],
					'expires_at'     => [ 'type' => 'integer', 'description' => 'Unix timestamp when the token expires.' ],
					'curl_example'   => [ 'type' => 'string', 'description' => 'Example curl command for the exchange step.' ],
				],
			],

			'execute_callback' => static function ( array $args ): array|\WP_Error {
				$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id();
				if ( $user_id <= 0 ) {
					$user_id = get_current_user_id();
				}

				$expires_in         = max( 30, min( 3600, (int) ( $args['expires_in'] ?? 300 ) ) );
				$session_expires_in = max( 60, min( 3600, (int) ( $args['session_expires_in'] ?? 1800 ) ) );
				$admin_path         = is_string( $args['admin_path'] ?? null ) ? (string) $args['admin_path'] : '';

				$result = AdminAccessEndpoint::create_token(
					$user_id,
					$expires_in,
					$session_expires_in,
					$admin_path
				);

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$exchange_url = rest_url( 'wpcodex/v1/admin-access' );
				$token_header = 'X-WPCodex-Admin-Access-Token';
				$nonce_header = 'X-WPCodex-Admin-Access-Nonce';

				return [
					'exchange_url' => $exchange_url,
					'access_token' => $result['token'],
					'token_header' => $token_header,
					'access_nonce' => $result['nonce'],
					'nonce_header' => $nonce_header,
					'expires_at'   => $result['expires_at'],
					'curl_example' => sprintf(
						'curl -X POST -H "%s: $access_token" -H "%s: $access_nonce" %s',
						$token_header,
						$nonce_header,
						escapeshellarg( $exchange_url )
					),
				];
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [
					'instructions' => implode( "\n", [
						'Two-step flow:',
						'1. Call this ability to get exchange_url, access_token, access_nonce, token_header, nonce_header.',
						'2. POST to exchange_url with access_token in token_header and access_nonce in nonce_header.',
						'3. The exchange returns a login_url. Open login_url in the browser — it sets a WordPress auth cookie and redirects to wp-admin.',
						'The login_url is one-time and expires in 60 seconds. Use it immediately after the exchange.',
					] ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				],
				'mcp' => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

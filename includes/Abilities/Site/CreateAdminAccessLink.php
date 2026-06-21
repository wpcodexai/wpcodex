<?php
/**
 * Ability: allyworker/create-admin-access-link
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Site;

use AllyWorker\Abilities\AbstractAbility;
use AllyWorker\REST\AdminAccessEndpoint;

/**
 * Class CreateAdminAccessLink
 *
 * @since 1.0.0
 */
class CreateAdminAccessLink extends AbstractAbility {
	public function get_category(): string {
		return 'allyworker-site';
	}
	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/create-admin-access-link';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Create Admin Access Link', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __( 'Creates a temporary one-time admin session exchange for browser automation tools (e.g. Claude in Chrome). The tool POSTs the token and nonce to the exchange URL, receives a short-lived login URL, opens it in the browser, and is redirected into wp-admin without needing credentials.', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'user_id'            => [
					'type'        => 'integer',
					'description' => 'WordPress user ID of the admin to log in as. Defaults to the current user.',
				],
				'expires_in'         => [
					'type'        => 'integer',
					'description' => 'Seconds before the access token expires (30–600). Default 300.',
					'default'     => 300,
					'minimum'     => 30,
					'maximum'     => 600,
				],
				'session_expires_in' => [
					'type'        => 'integer',
					'description' => 'WordPress session lifetime in seconds after login (60–3600). Default 1800.',
					'default'     => 1800,
					'minimum'     => 60,
					'maximum'     => 3600,
				],
				'admin_path'         => [
					'type'        => 'string',
					'description' => 'Admin path to redirect to after login. Relative to wp-admin/. Default: empty (wp-admin home).',
					'default'     => '',
				],
			],
			'additionalProperties' => false,
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'exchange_url'       => [ 'type' => 'string', 'description' => 'POST this URL with access_token in token_header and access_nonce in nonce_header to receive a login_url.' ],
				'exchange_method'    => [ 'type' => 'string', 'description' => 'HTTP method for the exchange request.' ],
				'access_token'       => [ 'type' => 'string', 'description' => 'One-time bearer token. Send as the token_header value.' ],
				'token_header'       => [ 'type' => 'string', 'description' => 'HTTP header that must carry access_token.' ],
				'access_nonce'       => [ 'type' => 'string', 'description' => 'Binding nonce. Send as the nonce_header value.' ],
				'nonce_header'       => [ 'type' => 'string', 'description' => 'HTTP header that must carry access_nonce.' ],
				'expires_at'         => [ 'type' => 'integer', 'description' => 'Unix timestamp when the token expires.' ],
				'session_expires_in' => [ 'type' => 'integer', 'description' => 'Browser admin session duration in seconds.' ],
				'redirect_url'       => [ 'type' => 'string', 'description' => 'Admin URL opened after the token is consumed.' ],
				'one_time'           => [ 'type' => 'boolean', 'description' => 'Whether the URL can only be used once.' ],
				'curl_example'       => [ 'type' => 'string', 'description' => 'Example curl command for the exchange step.' ],
			],
			'required' => [
				'exchange_url',
				'exchange_method',
				'access_token',
				'token_header',
				'access_nonce',
				'nonce_header',
				'expires_at',
				'session_expires_in',
				'redirect_url',
				'one_time',
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
			'Two-step flow:',
			'1. Call this ability to get exchange_url, access_token, access_nonce, token_header, nonce_header.',
			'2. POST to exchange_url with access_token in token_header and access_nonce in nonce_header.',
			'3. The exchange returns a login_url. Open login_url in the browser — it sets a WordPress auth cookie and redirects to wp-admin.',
			'The login_url is one-time and expires in 60 seconds. Use it immediately after the exchange.',
		] );
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array|\WP_Error {
		$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : get_current_user_id();
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		$expires_in         = max( 30, min( 600, (int) ( $input['expires_in'] ?? 300 ) ) );
		$session_expires_in = max( 60, min( 3600, (int) ( $input['session_expires_in'] ?? 1800 ) ) );
		$admin_path         = is_string( $input['admin_path'] ?? null ) ? (string) $input['admin_path'] : '';

		$result = AdminAccessEndpoint::create_token(
			$user_id,
			$expires_in,
			$session_expires_in,
			$admin_path
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$exchange_url = rest_url( 'allyworker/v1/admin-access' );
		$token_header = 'X-AllyWorker-Admin-Access-Token';
		$nonce_header = 'X-AllyWorker-Admin-Access-Nonce';

		$admin_path_clean = ltrim( $admin_path, '/' );
		if ( str_starts_with( $admin_path_clean, 'wp-admin/' ) ) {
			$admin_path_clean = substr( $admin_path_clean, strlen( 'wp-admin/' ) );
		}
		$redirect_url = '' !== $admin_path_clean ? admin_url( $admin_path_clean ) : admin_url();

		return [
			'exchange_url'       => $exchange_url,
			'exchange_method'    => 'POST',
			'access_token'       => $result['token'],
			'token_header'       => $token_header,
			'access_nonce'       => $result['nonce'],
			'nonce_header'       => $nonce_header,
			'expires_at'         => $result['expires_at'],
			'session_expires_in' => $session_expires_in,
			'redirect_url'       => $redirect_url,
			'one_time'           => true,
			'curl_example'       => sprintf(
				'curl -s -X POST -H "%s: $access_token" -H "%s: $access_nonce" %s',
				$token_header,
				$nonce_header,
				escapeshellarg( $exchange_url )
			),
		];
	}
}

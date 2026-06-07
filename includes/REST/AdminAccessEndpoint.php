<?php
/**
 * Temporary admin access exchange REST endpoints.
 *
 * Provides a two-step flow: the AI agent creates a one-time token, then a
 * browser automation tool POSTs the token + nonce to the exchange endpoint
 * and gets back a short-lived login URL that sets a WordPress auth cookie.
 *
 * @package WPCodex\REST
 */

declare( strict_types=1 );

namespace WPCodex\REST;

/**
 * Class AdminAccessEndpoint
 */
class AdminAccessEndpoint {

	private const ROUTE_NAMESPACE = 'wpcodex/v1';

	/**
	 * Wire the rest_api_init hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the admin-access REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		// Exchange endpoint: POST token + nonce → receive short-lived login URL.
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/admin-access',
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ self::class, 'handle_exchange' ],
				'permission_callback' => '__return_true',
			]
		);

		// Login endpoint: GET one-time nonce → set auth cookie → redirect.
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/admin-access/(?P<nonce>[A-Za-z0-9_-]+)',
			[
				'methods'             => [ 'GET' ],
				'callback'            => [ self::class, 'handle_login' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Token creation (called by the CreateAdminAccessLink ability)
	// -------------------------------------------------------------------------

	/**
	 * Create a one-time admin access token and binding nonce.
	 *
	 * @return array{token: string, nonce: string, expires_at: int}|\WP_Error
	 */
	public static function create_token(
		int $user_id,
		int $expires_in,
		int $session_expires_in,
		string $admin_path
	): array|\WP_Error {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User || ! user_can( $user, 'manage_options' ) ) {
			return new \WP_Error( 'invalid_admin_access_user', 'Admin access links can only be created for administrators.' );
		}

		$redirect_url = self::resolve_redirect( $admin_path );
		if ( is_wp_error( $redirect_url ) ) {
			return $redirect_url;
		}

		$token      = wp_generate_password( 64, false, false );
		$nonce      = wp_generate_password( 32, false, false );
		$expires_at = time() + $expires_in;

		$payload = [
			'user_id'             => $user_id,
			'redirect_url'        => $redirect_url,
			'expires_at'          => $expires_at,
			'session_expires_in'  => $session_expires_in,
			'nonce_hash'          => self::nonce_hash( $nonce ),
		];

		if ( ! set_transient( self::token_transient_key( $token ), $payload, $expires_in ) ) {
			return new \WP_Error( 'admin_access_token_store_failed', 'Could not store admin access token.' );
		}

		return [
			'token'      => $token,
			'nonce'      => $nonce,
			'expires_at' => $expires_at,
		];
	}

	// -------------------------------------------------------------------------
	// REST handlers
	// -------------------------------------------------------------------------

	/**
	 * Exchange the one-time token + nonce for a short-lived browser login URL.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_exchange( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! self::abilities_enabled() ) {
			return new \WP_Error( 'wpcodex_disabled', 'WPCodex abilities are disabled.', [ 'status' => 403 ] );
		}

		$token = self::get_header_value( $request, 'x-wpcodex-admin-access-token' );
		if ( '' === $token ) {
			return new \WP_Error( 'missing_admin_access_token', 'Missing admin access token.', [ 'status' => 401 ] );
		}

		$nonce = self::get_header_value( $request, 'x-wpcodex-admin-access-nonce' );
		if ( '' === $nonce ) {
			return new \WP_Error( 'missing_admin_access_nonce', 'Missing admin access nonce.', [ 'status' => 401 ] );
		}

		/** @var mixed $payload */
		$payload = get_transient( self::token_transient_key( $token ) );
		delete_transient( self::token_transient_key( $token ) );

		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		if ( ! is_string( $payload['nonce_hash'] ?? null ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		if ( ! hash_equals( $payload['nonce_hash'], self::nonce_hash( $nonce ) ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		$access = self::validate_payload( $payload );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		// Create a short-lived, one-time browser login nonce (≤ 60 s).
		$login_nonce      = wp_generate_password( 48, false, false );
		$login_expires_at = min( $access['expires_at'], time() + 60 );
		$login_expires_in = max( 1, $login_expires_at - time() );

		$login_payload = [
			'user_id'            => $access['user_id'],
			'redirect_url'       => $access['redirect_url'],
			'expires_at'         => $login_expires_at,
			'session_expires_in' => $access['session_expires_in'],
		];

		if ( ! set_transient( self::login_transient_key( $login_nonce ), $login_payload, $login_expires_in ) ) {
			return new \WP_Error( 'admin_access_nonce_store_failed', 'Could not store admin access login nonce.' );
		}

		$response = new \WP_REST_Response( [
			'login_url'          => rest_url( 'wpcodex/v1/admin-access/' . rawurlencode( $login_nonce ) ),
			'expires_at'         => $login_expires_at,
			'session_expires_in' => $access['session_expires_in'],
			'redirect_url'       => $access['redirect_url'],
			'one_time'           => true,
		] );

		self::add_no_store_headers( $response );

		return $response;
	}

	/**
	 * Consume a one-time browser login nonce, set the auth cookie, and redirect.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_login( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! self::abilities_enabled() ) {
			return new \WP_Error( 'wpcodex_disabled', 'WPCodex abilities are disabled.', [ 'status' => 403 ] );
		}

		$params = $request->get_url_params();
		$nonce  = is_string( $params['nonce'] ?? null ) ? (string) $params['nonce'] : '';
		if ( '' === $nonce ) {
			return new \WP_Error( 'missing_admin_access_nonce', 'Missing admin access nonce.', [ 'status' => 401 ] );
		}

		/** @var mixed $payload */
		$payload = get_transient( self::login_transient_key( $nonce ) );
		delete_transient( self::login_transient_key( $nonce ) );

		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'invalid_admin_access_nonce', 'Invalid or expired admin access nonce.', [ 'status' => 401 ] );
		}

		$access = self::validate_payload( $payload );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		return self::create_redirect_response( $access );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve an admin-relative redirect path to a full admin URL.
	 *
	 * @return string|\WP_Error
	 */
	private static function resolve_redirect( string $admin_path ): string|\WP_Error {
		$admin_path = trim( $admin_path );
		if ( '' === $admin_path ) {
			return admin_url();
		}

		if (
			str_contains( $admin_path, "\r" )
			|| str_contains( $admin_path, "\n" )
			|| preg_match( '#^[a-z][a-z0-9+.-]*:#i', $admin_path ) === 1
			|| str_starts_with( $admin_path, '//' )
		) {
			return new \WP_Error(
				'invalid_admin_access_redirect',
				'Redirect path must be relative to wp-admin, not an absolute URL.'
			);
		}

		$admin_path = ltrim( $admin_path, '/' );
		if ( str_starts_with( $admin_path, 'wp-admin/' ) ) {
			$admin_path = substr( $admin_path, strlen( 'wp-admin/' ) );
		}

		return admin_url( $admin_path );
	}

	/**
	 * Validate a stored admin access payload.
	 *
	 * @param array<array-key, mixed> $payload
	 * @return array{user_id: int, redirect_url: string, expires_at: int, session_expires_in: int}|\WP_Error
	 */
	private static function validate_payload( array $payload ): array|\WP_Error {
		$expires_at = (int) ( $payload['expires_at'] ?? 0 );
		if ( $expires_at < time() ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		$user_id = (int) ( $payload['user_id'] ?? 0 );
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User || ! user_can( $user, 'manage_options' ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		if ( ! is_string( $payload['redirect_url'] ?? null ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		$redirect_url = (string) $payload['redirect_url'];
		if ( ! str_starts_with( $redirect_url, admin_url() ) ) {
			return new \WP_Error( 'invalid_admin_access_token', 'Invalid or expired admin access token.', [ 'status' => 401 ] );
		}

		return [
			'user_id'            => $user_id,
			'redirect_url'       => $redirect_url,
			'expires_at'         => $expires_at,
			'session_expires_in' => max( 60, min( 3600, (int) ( $payload['session_expires_in'] ?? 1800 ) ) ),
		];
	}

	/**
	 * Set the auth cookie and return a redirect response.
	 *
	 * @param array{user_id: int, redirect_url: string, expires_at: int, session_expires_in: int} $access
	 */
	private static function create_redirect_response( array $access ): \WP_REST_Response {
		$session_expires_in = $access['session_expires_in'];
		$shorten_expiry     = static fn( int $length ): int => $session_expires_in;

		add_filter( 'auth_cookie_expiration', $shorten_expiry );
		try {
			wp_set_current_user( $access['user_id'] );
			wp_set_auth_cookie( $access['user_id'], false, is_ssl() );
		} finally {
			remove_filter( 'auth_cookie_expiration', $shorten_expiry );
		}

		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $access['redirect_url'] );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		self::add_no_store_headers( $response );

		return $response;
	}

	/** Add cache-control no-store headers to a response. */
	private static function add_no_store_headers( \WP_REST_Response $response ): void {
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
	}

	/** Extract a value from a request header or Authorization Bearer. */
	private static function get_header_value( \WP_REST_Request $request, string $header_name ): string {
		$value = $request->get_header( $header_name );
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return trim( $value );
		}

		// Fallback: Bearer token in Authorization header.
		$auth = $request->get_header( 'authorization' );
		if ( ! is_string( $auth ) ) {
			return '';
		}

		if ( preg_match( '/^\s*Bearer\s+(.+?)\s*$/i', $auth, $matches ) === 1 ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/** Return the transient key for an admin access token. */
	private static function token_transient_key( string $token ): string {
		return 'wpcodex_admin_access_' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/** Return the transient key for a one-time browser login nonce. */
	private static function login_transient_key( string $nonce ): string {
		return 'wpcodex_admin_login_' . hash_hmac( 'sha256', $nonce, wp_salt( 'auth' ) );
	}

	/** Return the binding nonce HMAC hash stored alongside a token. */
	private static function nonce_hash( string $nonce ): string {
		return hash_hmac( 'sha256', $nonce, wp_salt( 'nonce' ) . '|wpcodex-admin-access' );
	}

	/** Check whether WPCodex abilities are currently enabled. */
	private static function abilities_enabled(): bool {
		return (bool) get_option( 'wpcodex_abilities_enabled', false );
	}
}

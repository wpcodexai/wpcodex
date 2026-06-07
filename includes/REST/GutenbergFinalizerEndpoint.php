<?php
/**
 * Gutenberg finalizer REST endpoint.
 *
 * Provides the REST routes used by the browser-side JS finalizer runtime
 * to heartbeat, claim batches, process items, and stream SSE status events.
 *
 * @package WPCodex\REST
 */

declare( strict_types=1 );

namespace WPCodex\REST;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WPCodex\Utils\GutenbergStorage;

/**
 * Class GutenbergFinalizerEndpoint
 *
 * REST endpoint for the browser-side JS Gutenberg finalizer runtime.
 *
 * @since 1.0.0
 */
class GutenbergFinalizerEndpoint {

	/**
	 * Wire the rest_api_init hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register all wpcodex/v1/gutenberg/* REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route( 'wpcodex/v1', '/gutenberg/batches', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'rest_list_batches' ],
			'permission_callback' => [ self::class, 'can_access_dashboard' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/finalizer-runtime/heartbeat', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_finalizer_runtime_heartbeat' ],
			'permission_callback' => [ self::class, 'can_access_dashboard' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/finalizer-runtime/status', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'rest_poll_finalizer_runtime_status' ],
			'permission_callback' => [ self::class, 'can_poll_finalizer_runtime' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/finalizer-runtime/events', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'rest_stream_finalizer_runtime_events' ],
			'permission_callback' => [ self::class, 'can_poll_finalizer_runtime' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/batches/(?P<batch_id>\d+)/claim', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_claim_batch' ],
			'permission_callback' => [ self::class, 'can_access_batch' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/batches/(?P<batch_id>\d+)/items/claim-next', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_claim_next_item' ],
			'permission_callback' => [ self::class, 'can_access_batch' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/items/(?P<item_id>\d+)/spec', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'rest_get_item_spec' ],
			'permission_callback' => [ self::class, 'can_access_item' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/items/(?P<item_id>\d+)/complete', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_complete_item' ],
			'permission_callback' => [ self::class, 'can_access_item' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/items/(?P<item_id>\d+)/fail', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_fail_item' ],
			'permission_callback' => [ self::class, 'can_access_item' ],
		] );

		register_rest_route( 'wpcodex/v1', '/gutenberg/batches/(?P<batch_id>\d+)/cancel', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_cancel_batch' ],
			'permission_callback' => [ self::class, 'can_access_batch' ],
		] );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission callback: requires edit_posts capability.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success; WP_Error with 403 on failure.
	 */
	public static function can_access_dashboard(): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', 'You are not allowed to access the Block Editor Queue dashboard.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Permission callback: validates the token-gated poll token.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True on success; WP_Error with 403 on failure.
	 */
	public static function can_poll_finalizer_runtime( WP_REST_Request $request ): bool|WP_Error {
		$token = self::query_string_param( $request, 'token' );
		if ( $token === '' || ! hash_equals( GutenbergStorage::finalizer_runtime_poll_token(), $token ) ) {
			return new WP_Error( 'rest_forbidden', 'The Block Editor Queue status token is invalid.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Permission callback: verifies batch exists and current user can finalize it.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True on success; WP_Error with 403/404 on failure.
	 */
	public static function can_access_batch( WP_REST_Request $request ): bool|WP_Error {
		$batch_id = self::int_param( $request, 'batch_id' );
		$batch    = GutenbergStorage::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}
		if ( ! GutenbergStorage::current_user_can_finalize_batch( $batch ) ) {
			return new WP_Error( 'rest_forbidden', 'You are not allowed to finalize this Gutenberg batch.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Permission callback: verifies item exists and current user can finalize its target.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True on success; WP_Error with 403/404 on failure.
	 */
	public static function can_access_item( WP_REST_Request $request ): bool|WP_Error {
		$item_id = self::int_param( $request, 'item_id' );
		$item    = GutenbergStorage::find_item( $item_id );
		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_not_found', sprintf( 'Gutenberg item %d was not found.', $item_id ), [ 'status' => 404 ] );
		}
		$target_id = GutenbergStorage::meta_int( $item->ID, GutenbergStorage::META_TARGET_ID );
		if (
			! current_user_can( 'manage_options' )
			&& ( $target_id <= 0 || ! current_user_can( 'edit_post', $target_id ) )
		) {
			return new WP_Error( 'rest_forbidden', 'You are not allowed to finalize this Gutenberg item.', [ 'status' => 403 ] );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// REST callbacks
	// -------------------------------------------------------------------------

	/**
	 * GET /gutenberg/batches — list all batches the current user can finalize.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_list_batches( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		GutenbergStorage::mark_stale_drafts();
		$query_params = $request->get_query_params();
		$statuses     = self::string_list_query_param( $query_params['status'] ?? null );
		$batches      = GutenbergStorage::get_batches( $statuses !== [] ? $statuses : null, 50 );
		$visible      = [];
		foreach ( $batches as $batch ) {
			$batch = GutenbergStorage::refresh_batch_runtime_state( $batch );
			if ( GutenbergStorage::current_user_can_finalize_batch( $batch ) ) {
				$visible[] = GutenbergStorage::shape_batch_summary( $batch );
			}
		}
		return new WP_REST_Response( [
			'batches'           => $visible,
			'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status(),
		] );
	}

	/**
	 * POST /gutenberg/finalizer-runtime/heartbeat — record a JS runtime heartbeat.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_finalizer_runtime_heartbeat(): WP_REST_Response|WP_Error {
		return new WP_REST_Response( GutenbergStorage::record_finalizer_runtime_heartbeat() );
	}

	/**
	 * GET /gutenberg/finalizer-runtime/status — poll the current runtime + batch status.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_poll_finalizer_runtime_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::rest_response( self::finalizer_runtime_status_payload(
			self::query_int_param( $request, 'batch_id' ),
			self::query_string_param( $request, 'batch_token' )
		) );
	}

	/**
	 * GET /gutenberg/finalizer-runtime/events — SSE stream of runtime + batch status events.
	 *
	 * Streams Server-Sent Events until the connection is aborted, the batch reaches a
	 * terminal status, or the configured duration limit is reached.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return void
	 */
	public static function rest_stream_finalizer_runtime_events( WP_REST_Request $request ): void {
		$batch_id           = self::query_int_param( $request, 'batch_id' );
		$requested_interval = self::query_int_param( $request, 'interval' );
		$requested_duration = self::query_int_param( $request, 'duration' );
		$interval           = max( 1, min( 10, $requested_interval > 0 ? $requested_interval : 2 ) );
		$duration           = max( 5, min( 25, $requested_duration > 0 ? $requested_duration : 25 ) );
		$started_at         = time();

		self::send_sse_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo 'retry: ' . (string) ( $interval * 1000 ) . "\n\n";
		self::flush_sse();

		while ( ! connection_aborted() ) {
			$payload = self::finalizer_runtime_status_payload(
				$batch_id,
				self::query_string_param( $request, 'batch_token' )
			);
			if ( is_wp_error( $payload ) ) {
				self::sse_event( 'error', [
					'code'    => $payload->get_error_code(),
					'message' => $payload->get_error_message(),
				] );
				exit();
			}

			self::sse_event( 'status', $payload );
			if ( self::finalizer_runtime_payload_is_terminal( $payload ) ) {
				self::sse_event( 'done', [ 'reason' => 'terminal' ] );
				exit();
			}

			if ( ( time() - $started_at + $interval ) >= $duration ) {
				self::sse_event( 'reconnect', [ 'reason' => 'connection_duration_limit', 'after_seconds' => 1 ] );
				exit();
			}

			sleep( $interval );
		}

		exit();
	}

	/**
	 * POST /gutenberg/batches/{batch_id}/claim — claim a batch for finalization.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_claim_batch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::rest_response( GutenbergStorage::claim_batch( self::int_param( $request, 'batch_id' ) ) );
	}

	/**
	 * POST /gutenberg/batches/{batch_id}/items/claim-next — claim the next ready item.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_claim_next_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params      = self::json_params( $request );
		$lease_owner = is_scalar( $params['lease_owner'] ?? null ) ? (string) $params['lease_owner'] : '';
		return self::rest_response( GutenbergStorage::claim_next_item( self::int_param( $request, 'batch_id' ), $lease_owner ) );
	}

	/**
	 * GET /gutenberg/items/{item_id}/spec — return the block spec and editor URL for a running item.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_get_item_spec( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$item_id = self::int_param( $request, 'item_id' );
		$item    = GutenbergStorage::find_item( $item_id );
		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_not_found', sprintf( 'Gutenberg item %d was not found.', $item_id ), [ 'status' => 404 ] );
		}

		$query_params = $request->get_query_params();
		$lease_owner  = array_key_exists( 'lease_owner', $query_params ) && is_scalar( $query_params['lease_owner'] )
			? (string) $query_params['lease_owner']
			: '';

		if (
			GutenbergStorage::gb_status( $item->ID ) !== GutenbergStorage::STATUS_RUNNING
			|| ! GutenbergStorage::lease_is_valid( $item->ID, $lease_owner )
		) {
			return new WP_Error( 'gutenberg_item_lease_invalid', 'The item finalization lease is no longer active.', [ 'status' => 409 ] );
		}

		$blocks = GutenbergStorage::item_blocks( $item );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$editor_url = self::item_editor_url( $item );
		if ( is_wp_error( $editor_url ) ) {
			return $editor_url;
		}

		return new WP_REST_Response( [
			'item'       => GutenbergStorage::shape_item( $item ),
			'blocks'     => $blocks,
			'editor_url' => $editor_url,
		] );
	}

	/**
	 * POST /gutenberg/items/{item_id}/complete — submit serialized content from the JS runtime.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_complete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params      = self::json_params( $request );
		$lease_owner = is_scalar( $params['lease_owner'] ?? null ) ? (string) $params['lease_owner'] : '';
		$content     = is_scalar( $params['content'] ?? null ) ? (string) $params['content'] : '';
		return self::rest_response( GutenbergStorage::complete_item(
			self::int_param( $request, 'item_id' ),
			$lease_owner,
			$content,
			$params['validations'] ?? null
		) );
	}

	/**
	 * POST /gutenberg/items/{item_id}/fail — report a failure from the JS runtime.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_fail_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params      = self::json_params( $request );
		$lease_owner = is_scalar( $params['lease_owner'] ?? null ) ? (string) $params['lease_owner'] : '';
		$message     = is_scalar( $params['message'] ?? null ) ? (string) $params['message'] : '';
		return self::rest_response( GutenbergStorage::fail_item(
			self::int_param( $request, 'item_id' ),
			$lease_owner,
			$params['errors'] ?? null,
			$message
		) );
	}

	/**
	 * POST /gutenberg/batches/{batch_id}/cancel — cancel a batch from the queue dashboard.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_cancel_batch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::rest_response( GutenbergStorage::cancel_batch( self::int_param( $request, 'batch_id' ) ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Wrap an array or WP_Error in a WP_REST_Response.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|WP_Error $value Data to wrap or error to pass through.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function rest_response( array|WP_Error $value ): WP_REST_Response|WP_Error {
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		return new WP_REST_Response( $value );
	}

	/**
	 * Build the Gutenberg editor URL for a queue item with finalizer query params appended.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item Queue item post object.
	 * @return string|WP_Error Editor URL string or WP_Error if the target post is missing.
	 */
	private static function item_editor_url( WP_Post $item ): string|WP_Error {
		$target_id = GutenbergStorage::meta_int( $item->ID, GutenbergStorage::META_TARGET_ID );
		$target    = GutenbergStorage::get_target( $target_id );
		if ( ! $target instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_target_not_found', sprintf( 'Target post %d was not found.', $target_id ), [ 'status' => 404 ] );
		}
		$editor_url = get_edit_post_link( $target_id, 'raw' );
		if ( ! is_string( $editor_url ) || $editor_url === '' ) {
			$editor_url = admin_url( sprintf( 'post.php?post=%d&action=edit', $target_id ) );
		}
		return add_query_arg( [
			'wpcodex_gb_finalizer' => '1',
			'wpcodex_gb_item'      => $item->ID,
		], $editor_url );
	}

	/**
	 * Build the payload for a finalizer runtime status response.
	 *
	 * When batch_id is 0 only global runtime state is returned. Otherwise the
	 * batch is loaded and token-verified before the full batch shape is included.
	 *
	 * @since 1.0.0
	 * @param int    $batch_id    Gutenberg batch post ID, or 0 for global-only response.
	 * @param string $batch_token HMAC poll token for the batch (may be empty when $batch_id is 0).
	 * @return array<string, mixed>|WP_Error Payload array or WP_Error on failure.
	 */
	private static function finalizer_runtime_status_payload( int $batch_id, string $batch_token ): array|WP_Error {
		GutenbergStorage::mark_stale_drafts();

		if ( $batch_id <= 0 ) {
			return [
				'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status(),
				'user_instruction'  => GutenbergStorage::finalizer_runtime_startup_instruction(),
			];
		}

		$batch = GutenbergStorage::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}

		if (
			! GutenbergStorage::current_user_can_finalize_batch( $batch )
			&& ! GutenbergStorage::finalizer_runtime_batch_token_is_valid( $batch->ID, $batch_token )
		) {
			return new WP_Error( 'rest_forbidden', 'The Block Editor Queue batch status token is invalid.', [ 'status' => 403 ] );
		}

		$batch = GutenbergStorage::refresh_batch_runtime_state( $batch );

		return [
			'batch'             => GutenbergStorage::shape_batch( $batch ),
			'finalizer_runtime' => GutenbergStorage::finalizer_runtime_status( $batch ),
			'user_instruction'  => GutenbergStorage::user_instruction( $batch ),
		];
	}

	/**
	 * Check whether a finalizer runtime payload represents a terminal batch state.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $payload Payload produced by finalizer_runtime_status_payload().
	 * @return bool True when the batch status is one of the terminal statuses.
	 */
	private static function finalizer_runtime_payload_is_terminal( array $payload ): bool {
		$batch = is_array( $payload['batch'] ?? null ) ? $payload['batch'] : null;
		if ( $batch === null || ! is_scalar( $batch['status'] ?? null ) ) {
			return false;
		}
		return in_array( (string) $batch['status'], GutenbergStorage::TERMINAL_STATUSES, true );
	}

	/**
	 * Emit HTTP headers required for a Server-Sent Events stream.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function send_sse_headers(): void {
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/event-stream; charset=UTF-8' );
			header( 'Cache-Control: no-cache, no-transform' );
			header( 'X-Accel-Buffering: no' );
			header( 'Connection: keep-alive' );
		}
	}

	/**
	 * Write a single SSE event frame to the output buffer.
	 *
	 * @since 1.0.0
	 * @param string               $event SSE event name.
	 * @param array<string, mixed> $data  Data payload, JSON-encoded as the event data field.
	 * @return void
	 */
	private static function sse_event( string $event, array $data ): void {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo 'event: ' . $event . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo 'data: ' . ( $json !== false ? $json : '{}' ) . "\n\n";
		self::flush_sse();
	}

	/**
	 * Flush the output buffer so the SSE client receives data immediately.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function flush_sse(): void {
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Extract an integer value from the merged request params (route + body).
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @param string          $name    Parameter name.
	 * @return int Parsed integer, or 0 if absent or non-scalar.
	 */
	private static function int_param( WP_REST_Request $request, string $name ): int {
		$params = $request->get_params();
		if ( ! array_key_exists( $name, $params ) ) {
			return 0;
		}
		return is_scalar( $params[ $name ] ) ? (int) $params[ $name ] : 0;
	}

	/**
	 * Extract an integer value from the URL query string params only.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @param string          $name    Query parameter name.
	 * @return int Parsed integer, or 0 if absent or non-scalar.
	 */
	private static function query_int_param( WP_REST_Request $request, string $name ): int {
		$params = $request->get_query_params();
		if ( ! array_key_exists( $name, $params ) ) {
			return 0;
		}
		return is_scalar( $params[ $name ] ) ? (int) $params[ $name ] : 0;
	}

	/**
	 * Extract a string value from the URL query string params only.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @param string          $name    Query parameter name.
	 * @return string Trimmed string, or empty string if absent or non-scalar.
	 */
	private static function query_string_param( WP_REST_Request $request, string $name ): string {
		$params = $request->get_query_params();
		if ( ! array_key_exists( $name, $params ) ) {
			return '';
		}
		return is_scalar( $params[ $name ] ) ? (string) $params[ $name ] : '';
	}

	/**
	 * Return the JSON body params filtered to string-keyed entries only.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return array<string, mixed> Filtered JSON params.
	 */
	private static function json_params( WP_REST_Request $request ): array {
		$raw = $request->get_json_params();
		/** @var array<string, mixed> $filtered */
		$filtered = array_filter(
			$raw,
			static fn( mixed $v, mixed $k ): bool => is_string( $k ),
			ARRAY_FILTER_USE_BOTH
		);
		return $filtered;
	}

	/**
	 * Parse a comma-delimited string or array query param into a list of trimmed, non-empty strings.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw query param value (string, array, or other).
	 * @return list<string> Ordered list of non-empty trimmed string values.
	 */
	private static function string_list_query_param( mixed $value ): array {
		if ( is_string( $value ) ) {
			return array_values( array_filter(
				array_map( static fn( string $s ): string => trim( $s ), explode( ',', $value ) ),
				static fn( string $s ): bool => $s !== ''
			) );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_values( array_filter(
			array_map( static fn( mixed $s ): string => is_scalar( $s ) ? trim( (string) $s ) : '', $value ),
			static fn( string $s ): bool => $s !== ''
		) );
	}
}

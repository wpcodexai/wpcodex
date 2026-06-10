<?php
/**
 * Signed file upload REST endpoint.
 *
 * Provides a temporary, header-authenticated upload URL that external tools
 * (e.g. curl) can use to upload files directly into the WordPress filesystem
 * without sending the file through the MCP JSON transport.
 *
 * @package WPCodex\REST
 */

declare( strict_types=1 );

namespace WPCodex\REST;

/**
 * Class UploadEndpoint
 */
class UploadEndpoint {

	private const ROUTE_NAMESPACE = 'wpcodex/v1';
	private const ROUTE           = '/upload';

	/**
	 * Wire the rest_api_init hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the upload REST route.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => [ 'POST', 'PUT' ],
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Token helpers
	// -------------------------------------------------------------------------

	/**
	 * Sign an upload-link payload into a bearer token.
	 *
	 * @param array<string, mixed> $payload
	 * @return string|\WP_Error
	 */
	public static function sign_payload( array $payload ): string|\WP_Error {
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return new \WP_Error( 'upload_token_encode_failed', 'Could not encode upload token payload.' );
		}

		$body      = self::base64url_encode( $json );
		$signature = hash_hmac( 'sha256', $body, self::token_secret(), true );

		return $body . '.' . self::base64url_encode( $signature );
	}

	/**
	 * Verify a bearer token and return its payload.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function verify_token( string $token ): array|\WP_Error {
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return new \WP_Error( 'invalid_upload_token', 'Invalid upload token.', [ 'status' => 401 ] );
		}

		[ $body, $sig ] = $parts;

		$expected = self::base64url_encode(
			hash_hmac( 'sha256', $body, self::token_secret(), true )
		);
		if ( ! hash_equals( $expected, $sig ) ) {
			return new \WP_Error( 'invalid_upload_token', 'Invalid upload token signature.', [ 'status' => 401 ] );
		}

		$json = self::base64url_decode( $body );
		if ( false === $json ) {
			return new \WP_Error( 'invalid_upload_token', 'Invalid upload token payload.', [ 'status' => 401 ] );
		}

		/** @var array<string, mixed>|null $decoded */
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'invalid_upload_token', 'Invalid upload token payload.', [ 'status' => 401 ] );
		}

		$payload = [
			'path'               => $decoded['path'] ?? null,
			'expires_at'         => $decoded['expires_at'] ?? null,
			'max_bytes'          => $decoded['max_bytes'] ?? null,
			'overwrite'          => $decoded['overwrite'] ?? null,
			'create_directories' => $decoded['create_directories'] ?? null,
		];

		$expires_at = (int) $payload['expires_at'];
		if ( $expires_at < time() ) {
			return new \WP_Error( 'upload_token_expired', 'Upload token has expired.', [ 'status' => 401 ] );
		}

		return $payload;
	}

	// -------------------------------------------------------------------------
	// REST handler
	// -------------------------------------------------------------------------

	/**
	 * Handle a signed upload request.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ): array|\WP_Error {
		if ( ! self::abilities_enabled() ) {
			return new \WP_Error( 'wpcodex_disabled', 'WPCodex abilities are disabled.', [ 'status' => 403 ] );
		}

		$token = self::get_token_from_request( $request );
		if ( '' === $token ) {
			return new \WP_Error( 'missing_upload_token', 'Missing upload token.', [ 'status' => 401 ] );
		}

		$payload = self::verify_token( $token );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$destination = self::prepare_destination( $payload );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}

		$source = self::open_source( $request );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$stream = $source['stream'];
		$result = $destination['overwrite']
			? self::overwrite_stream( $stream, $destination['path'], $destination['max_bytes'] )
			: self::create_stream( $stream, $destination['path'], $destination['max_bytes'] );

		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		clearstatcache( true, $destination['path'] );

		return [
			'path'                => $destination['path'],
			'bytes_written'       => $result['bytes_written'],
			'created'             => $result['created'],
			'directories_created' => $destination['directories_created'],
			'size'                => filesize( $destination['path'] ),
			'source'              => $source['source'],
			'filename'            => $source['filename'],
		];
	}

	// -------------------------------------------------------------------------
	// Destination preparation
	// -------------------------------------------------------------------------

	/**
	 * Resolve and validate the upload destination from a verified token payload.
	 *
	 * @param array<string, mixed> $payload
	 * @return array{path: string, max_bytes: int, overwrite: bool, directories_created: array<int, string>}|\WP_Error
	 */
	private static function prepare_destination( array $payload ): array|\WP_Error {
		if ( ! is_string( $payload['path'] ) || '' === $payload['path'] ) {
			return new \WP_Error( 'invalid_upload_token', 'Upload token does not contain a valid path.', [ 'status' => 401 ] );
		}

		try {
			$resolved = self::resolve_path( $payload['path'] );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'path_outside_base', $e->getMessage(), [ 'status' => 400 ] );
		}

		// Reject writes through a final symlink.
		if ( is_link( $resolved ) ) {
			return new \WP_Error( 'symlink_write_rejected', sprintf( 'Refusing to write through symlink path: %s', $resolved ) );
		}

		// PHP files and execution-control files must stay in the sandbox.
		$sandbox_error = self::check_php_execution_sandbox( $resolved );
		if ( is_wp_error( $sandbox_error ) ) {
			return $sandbox_error;
		}

		$parent_dir          = dirname( $resolved );
		$directories_created = [];

		if ( ! is_dir( $parent_dir ) ) {
			if ( true !== $payload['create_directories'] ) {
				return new \WP_Error( 'directory_not_found', sprintf( 'Parent directory does not exist: %s', $parent_dir ) );
			}

			$directories_created = self::ensure_parent_dir( $parent_dir );
			if ( is_wp_error( $directories_created ) ) {
				return $directories_created;
			}
		}

		if ( ! wp_is_writable( $parent_dir ) ) {
			return new \WP_Error( 'directory_not_writable', sprintf( 'Parent directory is not writable: %s', $parent_dir ) );
		}

		return [
			'path'               => $resolved,
			'max_bytes'          => max( 1, (int) $payload['max_bytes'] ),
			'overwrite'          => true === $payload['overwrite'],
			'directories_created' => $directories_created,
		];
	}

	// -------------------------------------------------------------------------
	// Stream handling
	// -------------------------------------------------------------------------

	/**
	 * Open the uploaded file as a stream (multipart or raw body).
	 *
	 * @param \WP_REST_Request $request
	 * @return array{stream: resource, source: string, filename: string}|\WP_Error
	 */
	private static function open_source( \WP_REST_Request $request ): array|\WP_Error {
		$files = $request->get_file_params();
		foreach ( $files as $field => $candidate ) {
			if ( 'file' === $field || 1 === count( $files ) ) {
				return self::open_multipart_source( $candidate );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$stream = fopen( 'php://input', 'rb' );
		if ( false === $stream ) {
			return new \WP_Error( 'upload_read_failed', 'Could not read upload request body.' );
		}

		return [ 'stream' => $stream, 'source' => 'raw', 'filename' => '' ];
	}

	/**
	 * Open a multipart upload as a stream.
	 *
	 * @param array<array-key, mixed> $file
	 * @return array{stream: resource, source: string, filename: string}|\WP_Error
	 */
	private static function open_multipart_source( array $file ): array|\WP_Error {
		$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_OK !== $error ) {
			return new \WP_Error( 'upload_failed', self::upload_error_message( $error ) );
		}

		$tmp_name = is_string( $file['tmp_name'] ?? null ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'invalid_upload', 'The multipart upload did not contain a valid uploaded file.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$stream = fopen( $tmp_name, 'rb' );
		if ( false === $stream ) {
			return new \WP_Error( 'upload_read_failed', 'Could not read uploaded file.' );
		}

		$name = is_string( $file['name'] ?? null ) ? sanitize_file_name( (string) $file['name'] ) : '';

		return [ 'stream' => $stream, 'source' => 'multipart', 'filename' => $name ];
	}

	/**
	 * Write an upload stream to a new (non-existent) destination file.
	 *
	 * @param resource $source
	 * @return array{bytes_written: int, created: bool}|\WP_Error
	 */
	private static function create_stream( $source, string $resolved, int $max_bytes ): array|\WP_Error {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$target = fopen( $resolved, 'xb' );
		if ( false === $target ) {
			if ( file_exists( $resolved ) ) {
				return new \WP_Error( 'file_exists', sprintf( 'Destination already exists: %s', $resolved ) );
			}
			return new \WP_Error( 'upload_write_failed', sprintf( 'Could not open destination for writing: %s', $resolved ) );
		}

		$bytes_written = self::copy_limited_stream( $source, $target, $max_bytes );
		fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( is_wp_error( $bytes_written ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $resolved );
			return $bytes_written;
		}

		chmod( $resolved, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		return [ 'bytes_written' => $bytes_written, 'created' => true ];
	}

	/**
	 * Write an upload stream, replacing the destination if it already exists.
	 *
	 * @param resource $source
	 * @return array{bytes_written: int, created: bool}|\WP_Error
	 */
	private static function overwrite_stream( $source, string $resolved, int $max_bytes ): array|\WP_Error {
		$created      = ! file_exists( $resolved );
		$tmp          = tempnam( dirname( $resolved ), '.wpcodex-upload-' );
		if ( false === $tmp ) {
			return new \WP_Error( 'upload_temp_failed', sprintf( 'Could not create temporary upload file in: %s', dirname( $resolved ) ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$target = fopen( $tmp, 'wb' );
		if ( false === $target ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $tmp );
			return new \WP_Error( 'upload_write_failed', sprintf( 'Could not open destination for writing: %s', $resolved ) );
		}

		$bytes_written = self::copy_limited_stream( $source, $target, $max_bytes );
		fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( is_wp_error( $bytes_written ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $tmp );
			return $bytes_written;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $tmp, $resolved ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $tmp );
			return new \WP_Error( 'upload_move_failed', sprintf( 'Could not move uploaded file into place: %s', $resolved ) );
		}

		chmod( $resolved, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		return [ 'bytes_written' => $bytes_written, 'created' => $created ];
	}

	/**
	 * Copy a stream while enforcing a byte limit.
	 *
	 * @param resource $source
	 * @param resource $target
	 * @return int|\WP_Error
	 */
	private static function copy_limited_stream( $source, $target, int $max_bytes ): int|\WP_Error {
		$bytes_written = 0;
		while ( ! feof( $source ) ) {
			$chunk = fread( $source, 1_048_576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( false === $chunk ) {
				return new \WP_Error( 'upload_read_failed', 'Could not read upload stream.' );
			}
			if ( '' === $chunk ) {
				continue;
			}

			$bytes_written += strlen( $chunk );
			if ( $bytes_written > $max_bytes ) {
				return new \WP_Error(
					'upload_too_large',
					sprintf( 'Upload exceeds the signed URL limit of %d bytes.', $max_bytes )
				);
			}

			if ( false === fwrite( $target, $chunk ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				return new \WP_Error( 'upload_write_failed', 'Could not write upload stream.' );
			}
		}

		return $bytes_written;
	}

	// -------------------------------------------------------------------------
	// Path resolution & sandbox enforcement
	// -------------------------------------------------------------------------

	/**
	 * Resolve a path and ensure it stays within ABSPATH.
	 *
	 * @throws \InvalidArgumentException When path is outside allowed roots.
	 */
	private static function resolve_path( string $path ): string {
		// Prepend ABSPATH to relative paths.
		if ( ! str_starts_with( $path, '/' ) && ! str_starts_with( $path, '\\' )
			&& ! ( strlen( $path ) >= 2 && ':' === $path[1] ) ) {
			$path = ABSPATH . $path;
		}

		$normalized_path = rtrim( str_replace( '\\', '/', $path ), '/' );
		$allowed_bases   = [
			rtrim( str_replace( '\\', '/', ABSPATH ), '/' ),
			rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ),
		];

		foreach ( $allowed_bases as $base ) {
			if ( str_starts_with( $normalized_path, $base . '/' ) || $normalized_path === $base ) {
				return $path;
			}
		}

		throw new \InvalidArgumentException(
			__( 'Path traversal outside WordPress root is not allowed.', 'wpcodex' )
		);
	}

	/**
	 * Determine whether a file extension or name requires the sandbox.
	 */
	private static function path_requires_sandbox( string $path ): bool {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'php' === $extension ) {
			return true;
		}

		return in_array(
			strtolower( basename( $path ) ),
			[ '.htaccess', '.php.ini', '.user.ini', 'php.ini', 'web.config' ],
			true
		);
	}

	/**
	 * Enforce the sandbox boundary for PHP execution paths.
	 *
	 * Non-PHP files outside the sandbox are intentionally allowed.
	 *
	 * @return bool|\WP_Error
	 */
	private static function check_php_execution_sandbox( string $path ): bool|\WP_Error {
		if ( ! self::path_requires_sandbox( $path ) ) {
			return true;
		}

		$sandbox = WPCODEX_SANDBOX_DIR;
		$normalized_sandbox = rtrim( str_replace( '\\', '/', $sandbox ), '/' );
		$normalized_path    = str_replace( '\\', '/', dirname( $path ) );

		if ( ! str_starts_with( $normalized_path, $normalized_sandbox ) ) {
			return new \WP_Error(
				'php_sandbox_required',
				sprintf(
					'PHP files and PHP execution control files can only be uploaded to the sandbox directory: %s. Use a path like "wp-content/wpcodex-sandbox/my-feature.php".',
					$sandbox
				)
			);
		}

		return true;
	}

	/**
	 * Create a parent directory tree and return the list of directories created.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	private static function ensure_parent_dir( string $parent_dir ): array|\WP_Error {
		if ( is_dir( $parent_dir ) ) {
			return [];
		}

		// Collect which directories will be created.
		$cursor       = $parent_dir;
		$dirs_to_make = [];
		while ( ! is_dir( $cursor ) ) {
			$dirs_to_make[] = $cursor;
			$cursor         = dirname( $cursor );
		}
		$dirs_created = array_reverse( $dirs_to_make );

		if ( ! wp_mkdir_p( $parent_dir ) ) {
			return new \WP_Error( 'mkdir_failed', sprintf( 'Failed to create directory: %s', $parent_dir ) );
		}

		return $dirs_created;
	}

	// -------------------------------------------------------------------------
	// Misc helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract the upload token from the request (custom header or Bearer).
	 */
	public static function get_token_from_request( \WP_REST_Request $request ): string {
		$header = $request->get_header( 'x-wpcodex-upload-token' );
		if ( is_string( $header ) && '' !== trim( $header ) ) {
			return trim( $header );
		}

		$auth = $request->get_header( 'authorization' );
		if ( ! is_string( $auth ) ) {
			return '';
		}

		if ( preg_match( '/^\s*Bearer\s+(.+?)\s*$/i', $auth, $matches ) === 1 ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/** Return the HMAC signing secret. */
	private static function token_secret(): string {
		return wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|wpcodex-upload-link';
	}

	/** Base64url-encode a binary string. */
	private static function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/** Base64url-decode a string. Returns false on invalid input. */
	private static function base64url_decode( string $value ): string|false {
		$padding = strlen( $value ) % 4;
		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}

	/** Human-readable PHP upload error message. */
	private static function upload_error_message( int $error ): string {
		return match ( $error ) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the configured PHP upload size limit.',
			UPLOAD_ERR_PARTIAL                        => 'The file was only partially uploaded.',
			UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR                     => 'The server is missing a temporary upload directory.',
			UPLOAD_ERR_CANT_WRITE                     => 'The server could not write the uploaded file to disk.',
			UPLOAD_ERR_EXTENSION                      => 'A PHP extension stopped the file upload.',
			default                                   => 'The upload failed.',
		};
	}

	/** Check whether WPCodex abilities are currently enabled. */
	private static function abilities_enabled(): bool {
		return (bool) get_option( 'wpcodex_abilities_enabled', false );
	}
}

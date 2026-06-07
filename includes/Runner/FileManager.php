<?php
/**
 * File Manager — filesystem operations with path traversal protection.
 *
 * @package WPCodex\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Runner;

/**
 * Class FileManager
 *
 * Provides structured filesystem operations for the WPCodex file abilities.
 * All methods return typed arrays on success and throw \RuntimeException or
 * \InvalidArgumentException on failure so callers can convert to \WP_Error.
 */
class FileManager {

	private const BACKUP_TRANSIENT_PREFIX = 'wpcodex_bak_';

	/** Extensions / basenames that must be written inside WPCODEX_SANDBOX_DIR. */
	private const SANDBOX_REQUIRED_EXT   = [ 'php' ];
	private const SANDBOX_REQUIRED_NAMES = [ '.htaccess', '.php.ini', '.user.ini', 'php.ini', 'web.config' ];

	/** Directories that must never be deleted. */
	private const PROTECTED_DIRS = [
		'wp-admin',
		'wp-includes',
		'wp-content/mu-plugins',
	];

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
	// Public structured API
	// -------------------------------------------------------------------------

	/**
	 * Read a file and return structured metadata + content.
	 *
	 * @return array{path: string, content: string, encoding: string, size: int, bytes_read: int, truncated: bool, mime_type: string}
	 * @throws \InvalidArgumentException On path traversal.
	 * @throws \RuntimeException On I/O errors.
	 */
	public function read_file( string $path, int $offset = 0, int $limit = 1_048_576 ): array {
		$path   = $this->resolve_path( $path );
		$offset = max( 0, $offset );
		$limit  = max( 1, min( 10_485_760, $limit ) );

		if ( ! file_exists( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'File not found: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'File not readable: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		$size = (int) filesize( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fh = fopen( $path, 'rb' );
		if ( false === $fh ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to open: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		if ( $offset > 0 ) {
			fseek( $fh, $offset );
		}

		// Read one extra byte to detect truncation.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$raw = fread( $fh, $limit + 1 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $fh );

		if ( false === $raw ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to read: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		$truncated = strlen( $raw ) > $limit;
		if ( $truncated ) {
			$raw = substr( $raw, 0, $limit );
		}

		$bytes_read = strlen( $raw );

		// Detect MIME type.
		$mime_type = 'application/octet-stream';
		if ( function_exists( 'mime_content_type' ) ) {
			$detected = mime_content_type( $path );
			if ( is_string( $detected ) ) {
				$mime_type = $detected;
			}
		}

		// Use base64 for binary data (non-UTF-8).
		$is_binary = ! mb_check_encoding( $raw, 'UTF-8' );
		if ( $is_binary ) {
			$encoding = 'base64';
			$content  = base64_encode( $raw );
		} else {
			$encoding = 'utf-8';
			$content  = $raw;
		}

		return [
			'path'       => $path,
			'content'    => $content,
			'encoding'   => $encoding,
			'size'       => $size,
			'bytes_read' => $bytes_read,
			'truncated'  => $truncated,
			'mime_type'  => $mime_type,
		];
	}

	/**
	 * Write a file, creating it or overwriting/appending as requested.
	 *
	 * @param string $encoding 'utf-8' (default) or 'base64' — when base64, content is decoded first.
	 * @param string $mode     'overwrite' (default, atomic with .bak) or 'append'.
	 * @return array{path: string, bytes_written: int, created: bool, directories_created: list<string>, size: int}
	 * @throws \InvalidArgumentException On path traversal or symlink/sandbox violations.
	 * @throws \RuntimeException On I/O errors.
	 */
	public function write_file(
		string $path,
		string $content,
		string $encoding = 'utf-8',
		string $mode = 'overwrite',
		bool $create_directories = true
	): array {
		$path = $this->resolve_path( $path );

		// Reject writes through a symlink.
		if ( is_link( $path ) ) {
			throw new \InvalidArgumentException(
				sprintf( __( 'Refusing to write through symlink: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		// PHP / execution-control files must stay in the sandbox.
		$this->assert_sandbox_if_required( $path );

		// Decode base64 content.
		if ( 'base64' === $encoding ) {
			$decoded = base64_decode( $content, true );
			if ( false === $decoded ) {
				throw new \RuntimeException( __( 'Content is not valid base64.', 'wpcodex' ) );
			}
			$content = $decoded;
		}

		$created             = ! file_exists( $path );
		$directories_created = [];
		$parent_dir          = dirname( $path );

		if ( ! is_dir( $parent_dir ) ) {
			if ( ! $create_directories ) {
				throw new \RuntimeException(
					sprintf( __( 'Parent directory does not exist: %s', 'wpcodex' ), esc_html( $parent_dir ) )
				);
			}
			$directories_created = $this->ensure_and_track_dirs( $parent_dir );
		}

		if ( ! wp_is_writable( $parent_dir ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Directory not writable: %s', 'wpcodex' ), esc_html( $parent_dir ) )
			);
		}

		if ( 'append' === $mode ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $path, $content, FILE_APPEND | LOCK_EX );
			if ( false === $result ) {
				throw new \RuntimeException(
					sprintf( __( 'Failed to append to: %s', 'wpcodex' ), esc_html( $path ) )
				);
			}
		} else {
			if ( ! $created ) {
				$this->backup( $path );
			}
			$this->atomic_write( $path, $content );
		}

		clearstatcache( true, $path );

		$bytes_written = strlen( $content );
		$final_size    = filesize( $path );

		return [
			'path'                => $path,
			'bytes_written'       => $bytes_written,
			'created'             => $created,
			'directories_created' => $directories_created,
			'size'                => false !== $final_size ? (int) $final_size : $bytes_written,
		];
	}

	/**
	 * Edit a file by exact string replacement.
	 *
	 * @return array{path: string, replacements: int, size: int}
	 * @throws \InvalidArgumentException On path traversal.
	 * @throws \RuntimeException On not-found, not-unique (when replace_all=false), or I/O errors.
	 */
	public function edit_file( string $path, string $old_string, string $new_string, bool $replace_all = false ): array {
		$path = $this->resolve_path( $path );

		if ( ! file_exists( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'File not found: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$current = file_get_contents( $path );
		if ( false === $current ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to read: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		$count = substr_count( $current, $old_string );

		if ( 0 === $count ) {
			throw new \RuntimeException( __( 'old_string not found in file.', 'wpcodex' ) );
		}

		if ( ! $replace_all && $count > 1 ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d occurrence count */
					__( 'old_string appears %d times — must appear exactly once for a safe edit, or set replace_all to true.', 'wpcodex' ),
					$count
				)
			);
		}

		$new_content  = str_replace( $old_string, $new_string, $current );
		$replacements = $replace_all ? $count : 1;

		$this->atomic_write( $path, $new_content );
		clearstatcache( true, $path );

		$size = filesize( $path );

		return [
			'path'         => $path,
			'replacements' => $replacements,
			'size'         => false !== $size ? (int) $size : strlen( $new_content ),
		];
	}

	/**
	 * List a directory with optional depth, glob pattern, hidden-file control, and entry limit.
	 *
	 * @return array{path: string, entries: list<array<string, mixed>>, total: int, truncated: bool}
	 * @throws \InvalidArgumentException On path traversal.
	 * @throws \RuntimeException When path is not a directory.
	 */
	public function list_directory(
		string $path,
		string $pattern = '*',
		int $max_depth = 3,
		bool $include_hidden = false,
		int $limit = 500
	): array {
		$path      = $this->resolve_path( $path );
		$max_depth = max( 1, min( 10, $max_depth ) );
		$limit     = max( 1, min( 5000, $limit ) );

		if ( ! is_dir( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Not a directory: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		$all = $this->scan_entries( $path, $pattern, $max_depth, $include_hidden, 1 );

		// Sort: directories first, then files; each group case-insensitive alpha.
		usort( $all, static function ( array $a, array $b ): int {
			if ( $a['type'] !== $b['type'] ) {
				return $a['type'] === 'dir' ? -1 : 1;
			}
			return strnatcasecmp( (string) $a['name'], (string) $b['name'] );
		} );

		$total     = count( $all );
		$truncated = $total > $limit;
		$entries   = array_slice( $all, 0, $limit );

		return [
			'path'      => $path,
			'entries'   => $entries,
			'total'     => $total,
			'truncated' => $truncated,
		];
	}

	/**
	 * Delete a file or directory.
	 *
	 * Idempotent: if the path does not exist, returns success with deleted=false.
	 *
	 * @return array{path: string, type: string, deleted: bool, items_deleted: int}
	 * @throws \InvalidArgumentException On path traversal or protected-path violations.
	 * @throws \RuntimeException On I/O errors.
	 */
	public function delete_path( string $path, bool $recursive = false ): array {
		$path = $this->resolve_path( $path );

		// Protect core directories.
		$this->assert_not_protected( $path );

		// Idempotent: not-found = success.
		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			return [ 'path' => $path, 'type' => 'unknown', 'deleted' => false, 'items_deleted' => 0 ];
		}

		$is_dir = is_dir( $path ) && ! is_link( $path );

		if ( $is_dir ) {
			if ( ! $recursive ) {
				throw new \RuntimeException(
					sprintf(
						__( 'Path is a directory — set recursive to true to delete it: %s', 'wpcodex' ),
						esc_html( $path )
					)
				);
			}
			$items_deleted = $this->delete_recursive( $path );
			return [ 'path' => $path, 'type' => 'dir', 'deleted' => true, 'items_deleted' => $items_deleted ];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		if ( ! unlink( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to delete: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}

		return [ 'path' => $path, 'type' => 'file', 'deleted' => true, 'items_deleted' => 1 ];
	}

	/**
	 * Resolve and validate a path — blocks traversal outside ABSPATH / sys_get_temp_dir().
	 *
	 * Made public so ability classes can call it for pre-validation before using
	 * native PHP functions on the resolved path.
	 *
	 * @throws \InvalidArgumentException On traversal attempt.
	 */
	public function resolve_path( string $path ): string {
		if ( ! $this->is_absolute_path( $path ) ) {
			$path = ABSPATH . ltrim( $path, '/' );
		}

		$normalized_bases = [
			rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/',
			rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/',
		];
		$normalized_path = str_replace( '\\', '/', $path );

		foreach ( $normalized_bases as $base ) {
			if ( str_starts_with( $normalized_path, $base ) ) {
				return $path;
			}
		}

		if ( preg_match( '#^[A-Za-z]:/#', $normalized_path ) ) {
			throw new \InvalidArgumentException(
				__( 'Path traversal outside WordPress root is not allowed.', 'wpcodex' )
			);
		}

		if ( str_starts_with( $normalized_path, '/' ) || str_starts_with( $normalized_path, '\\' ) ) {
			throw new \InvalidArgumentException(
				__( 'Path traversal outside WordPress root is not allowed.', 'wpcodex' )
			);
		}

		return $path;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function is_absolute_path( string $path ): bool {
		return str_starts_with( $path, '/' )
			|| str_starts_with( $path, '\\' )
			|| ( 2 <= strlen( $path ) && ':' === $path[1] && ctype_alpha( $path[0] ) );
	}

	/**
	 * Assert that a PHP / execution-control file lives inside WPCODEX_SANDBOX_DIR.
	 *
	 * @throws \InvalidArgumentException When the path is outside the sandbox.
	 */
	private function assert_sandbox_if_required( string $path ): void {
		$ext      = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$basename = strtolower( basename( $path ) );

		$requires_sandbox = in_array( $ext, self::SANDBOX_REQUIRED_EXT, true )
			|| in_array( $basename, self::SANDBOX_REQUIRED_NAMES, true );

		if ( ! $requires_sandbox ) {
			return;
		}

		if ( ! defined( 'WPCODEX_SANDBOX_DIR' ) ) {
			throw new \InvalidArgumentException(
				__( 'PHP and execution-control files require the sandbox but WPCODEX_SANDBOX_DIR is not defined.', 'wpcodex' )
			);
		}

		$sandbox          = WPCODEX_SANDBOX_DIR;
		$normalized_box   = rtrim( str_replace( '\\', '/', $sandbox ), '/' );
		$normalized_dir   = rtrim( str_replace( '\\', '/', dirname( $path ) ), '/' );

		if ( $normalized_dir !== $normalized_box
			&& ! str_starts_with( $normalized_dir . '/', $normalized_box . '/' )
		) {
			throw new \InvalidArgumentException(
				sprintf(
					__( 'PHP files can only be written to the sandbox directory: %s', 'wpcodex' ),
					esc_html( $sandbox )
				)
			);
		}
	}

	/**
	 * Assert that a path does not point to a protected core directory.
	 *
	 * @throws \InvalidArgumentException When the path matches a protected directory.
	 */
	private function assert_not_protected( string $path ): void {
		$protected = [ rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) ];
		foreach ( self::PROTECTED_DIRS as $rel ) {
			$protected[] = rtrim( str_replace( '\\', '/', ABSPATH . $rel ), '/' );
		}

		$normalized = rtrim( str_replace( '\\', '/', $path ), '/' );

		foreach ( $protected as $prot ) {
			if ( $normalized === $prot || str_starts_with( $normalized . '/', $prot . '/' ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						__( 'Deleting this path is not allowed — it is a protected WordPress directory: %s', 'wpcodex' ),
						esc_html( $path )
					)
				);
			}
		}
	}

	/**
	 * Create the parent directory tree and return the list of created directories.
	 *
	 * @return list<string>
	 */
	private function ensure_and_track_dirs( string $parent_dir ): array {
		if ( is_dir( $parent_dir ) ) {
			return [];
		}

		$cursor       = $parent_dir;
		$dirs_to_make = [];
		while ( ! is_dir( $cursor ) ) {
			$dirs_to_make[] = $cursor;
			$parent         = dirname( $cursor );
			if ( $parent === $cursor ) {
				break;
			}
			$cursor = $parent;
		}
		$dirs_created = array_reverse( $dirs_to_make );

		wp_mkdir_p( $parent_dir );

		return $dirs_created;
	}

	/**
	 * Recursively scan a directory and return entries matching the pattern.
	 *
	 * Directories are always recursed; the pattern filters which entries appear
	 * in the output (applied to both files and directories).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function scan_entries(
		string $dir,
		string $pattern,
		int $max_depth,
		bool $include_hidden,
		int $current_depth
	): array {
		$entries = [];
		$items   = scandir( $dir );
		if ( false === $items ) {
			return $entries;
		}

		foreach ( $items as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( ! $include_hidden && str_starts_with( $name, '.' ) ) {
				continue;
			}

			$full  = $dir . DIRECTORY_SEPARATOR . $name;
			$is_d  = is_dir( $full ) && ! is_link( $full );
			$type  = $is_d ? 'dir' : 'file';

			// Include entry if it matches the pattern.
			if ( fnmatch( $pattern, $name ) ) {
				$perms   = fileperms( $full );
				$entries[] = [
					'name'        => $name,
					'path'        => $full,
					'type'        => $type,
					'size'        => $is_d ? null : (int) filesize( $full ),
					'permissions' => false !== $perms ? substr( sprintf( '%o', $perms ), -4 ) : '0000',
					'modified'    => (int) ( filemtime( $full ) ?: 0 ),
				];
			}

			// Always recurse into directories (regardless of pattern match).
			if ( $is_d && $current_depth < $max_depth ) {
				$sub     = $this->scan_entries( $full, $pattern, $max_depth, $include_hidden, $current_depth + 1 );
				$entries = array_merge( $entries, $sub );
			}
		}

		return $entries;
	}

	/** Delete a directory tree recursively and return the item count. */
	private function delete_recursive( string $dir ): int {
		$count = 0;
		$items = scandir( $dir );
		if ( false === $items ) {
			return $count;
		}

		foreach ( $items as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$full = $dir . DIRECTORY_SEPARATOR . $name;
			if ( is_dir( $full ) && ! is_link( $full ) ) {
				$count += $this->delete_recursive( $full );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $full );
				++$count;
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
				unlink( $full );
				++$count;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir );
		return $count;
	}

	private function backup( string $path ): void {
		$backup = $path . '.bak';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( copy( $path, $backup ) ) {
			$key = self::BACKUP_TRANSIENT_PREFIX . md5( $path );
			set_transient(
				$key,
				wp_json_encode( [ 'original' => $path, 'backup' => $backup, 'ts' => time() ] ),
				DAY_IN_SECONDS
			);
		}
	}

	private function atomic_write( string $path, string $content ): void {
		$tmp = $path . '.tmp_' . bin2hex( random_bytes( 4 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $tmp, $content ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to write temp file for: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $tmp, $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $tmp );
			throw new \RuntimeException(
				sprintf( __( 'Failed to rename temp file to: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
	}
}

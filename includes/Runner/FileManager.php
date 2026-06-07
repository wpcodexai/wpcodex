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
 */
class FileManager {

	private const BACKUP_TRANSIENT_PREFIX = 'wpcodex_bak_';

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

	/** Read a file. */
	public function read( string $path ): string {
		$path = $this->resolve( $path );
		$this->assert_exists( $path );
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'File not readable: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );
		if ( false === $content ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to read: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		return $content;
	}

	/** Write (overwrite) a file atomically with .bak backup. */
	public function write( string $path, string $content ): string {
		$path = $this->resolve( $path );
		$this->ensure_parent_dir( $path );
		$msg  = '';

		if ( file_exists( $path ) ) {
			$msg .= $this->backup( $path );
		}

		$this->atomic_write( $path, $content );
		$msg .= sprintf( 'Written: %s (%d bytes)', basename( $path ), strlen( $content ) );
		return $msg;
	}

	/** Edit a file by precise string replacement. */
	public function edit( string $path, string $search, string $replacement ): string {
		$path    = $this->resolve( $path );
		$content = $this->read( $path );

		$count = substr_count( $content, $search );
		if ( 0 === $count ) {
			throw new \RuntimeException(
				__( 'Search string not found in file.', 'wpcodex' )
			);
		}
		if ( $count > 1 ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d occurrence count */
					__( 'Search string appears %d times — must appear exactly once for a safe edit.', 'wpcodex' ),
					$count
				)
			);
		}

		$this->backup( $path );
		$new_content = str_replace( $search, $replacement, $content );
		$this->atomic_write( $path, $new_content );
		return sprintf( 'Edited: %s', basename( $path ) );
	}

	/** Delete a file permanently. */
	public function delete( string $path ): string {
		$path = $this->resolve( $path );
		$this->assert_exists( $path );
		if ( ! is_file( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Path is not a file: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		if ( ! unlink( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Failed to delete: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		return sprintf( 'Deleted: %s', basename( $path ) );
	}

	/** List a directory. */
	public function list( string $path, bool $recursive = false ): string {
		$path = $this->resolve( $path );
		if ( ! is_dir( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Not a directory: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
		$items = $recursive ? $this->list_recursive( $path ) : $this->list_flat( $path );
		return wp_json_encode( $items, JSON_PRETTY_PRINT ) ?: '[]';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve and validate a path — blocks traversal outside ABSPATH.
	 *
	 * @throws \InvalidArgumentException On traversal attempt.
	 */
	private function resolve( string $path ): string {
		if ( ! $this->is_absolute_path( $path ) ) {
			$path = ABSPATH . ltrim( $path, '/' );
		}

		$normalized_bases = [
			rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/',
			rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/',
		];
		$normalized_path = str_replace( '\\', '/', $path );

		foreach ( $normalized_bases as $normalized_base ) {
			if ( str_starts_with( $normalized_path, $normalized_base ) ) {
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

	private function is_absolute_path( string $path ): bool {
		return str_starts_with( $path, '/' )
			|| str_starts_with( $path, '\\' )
			|| ( 2 <= strlen( $path ) && ':' === $path[1] && ctype_alpha( $path[0] ) );
	}

	private function assert_exists( string $path ): void {
		if ( ! file_exists( $path ) ) {
			throw new \RuntimeException(
				sprintf( __( 'File not found: %s', 'wpcodex' ), esc_html( $path ) )
			);
		}
	}

	private function ensure_parent_dir( string $path ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! wp_is_writable( $dir ) ) {
			throw new \RuntimeException(
				sprintf( __( 'Directory not writable: %s', 'wpcodex' ), esc_html( $dir ) )
			);
		}
	}

	private function backup( string $path ): string {
		$backup = $path . '.bak';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( copy( $path, $backup ) ) {
			$key = self::BACKUP_TRANSIENT_PREFIX . md5( $path );
			set_transient( $key, wp_json_encode( [ 'original' => $path, 'backup' => $backup, 'ts' => time() ] ), DAY_IN_SECONDS );
			return sprintf( '[Backup: %s] ', basename( $backup ) );
		}
		return '';
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

	/** @return array<int, array<string, mixed>> */
	private function list_flat( string $dir ): array {
		$items = [];
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full    = $dir . DIRECTORY_SEPARATOR . $entry;
			$items[] = $this->file_info( $full, $entry );
		}
		return $items;
	}

	/** @return array<int, array<string, mixed>> */
	private function list_recursive( string $dir ): array {
		$items    = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			$items[] = $this->file_info( $file->getPathname(), $file->getFilename() );
		}
		return $items;
	}

	/** @return array<string, mixed> */
	private function file_info( string $full, string $name ): array {
		return [
			'name'     => $name,
			'path'     => $full,
			'type'     => is_dir( $full ) ? 'dir' : 'file',
			'size'     => is_file( $full ) ? filesize( $full ) : null,
			'modified' => filemtime( $full ) ?: null,
		];
	}
}
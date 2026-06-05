<?php
/**
 * PHP Runner — temp-file sandbox execution engine.
 *
 * @package WPCodex\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Runner;

/**
 * Class PhpRunner
 *
 * Executes PHP code by writing it to a uniquely named temp file inside
 * WPCODEX_SANDBOX_DIR, including it in the current WordPress process with
 * output buffering, then always deleting the file — even on exception.
 *
 * No process-level isolation. Designed for dev/staging environments.
 */
class PhpRunner {

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Execute PHP code and return captured output.
	 *
	 * @param string $code PHP code without opening tag.
	 * @return string Captured stdout + return value, or formatted error string.
	 *
	 * @throws \RuntimeException When the sandbox directory is not writable.
	 */
	public function run( string $code ): string {
		$sandbox = WPCODEX_SANDBOX_DIR;

		if ( ! is_dir( $sandbox ) ) {
			wp_mkdir_p( $sandbox );
		}

		if ( ! wp_is_writable( $sandbox ) ) {
			throw new \RuntimeException(
				__( 'WPCodex sandbox directory is not writable. Check plugin activation.', 'wpcodex' )
			);
		}

		// Write to a uniquely named temp file.
		$filename = $sandbox . 'exec_' . bin2hex( random_bytes( 8 ) ) . '.php';
		$wrapped  = "<?php\ndeclare(strict_types=1);\n" . $code . "\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $filename, $wrapped ) ) {
			throw new \RuntimeException( __( 'WPCodex: Failed to write PHP sandbox file.', 'wpcodex' ) );
		}

		ob_start();
		$return_value = null;

		try {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			$return_value = include $filename;
		} catch ( \Throwable $e ) {
			ob_end_clean();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $filename );

			return sprintf(
				"[WPCodex PHP Error]\n%s\nIn %s on line %d\n\nStack trace:\n%s",
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			);
		}

		$output = (string) ob_get_clean();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		@unlink( $filename );

		$parts = [];
		if ( '' !== $output ) {
			$parts[] = $output;
		}
		// Append return value if meaningful (include() returns 1 by default).
		if ( null !== $return_value && 1 !== $return_value ) {
			$parts[] = '[Return value]: ' . wp_json_encode( $return_value, JSON_PRETTY_PRINT );
		}

		return implode( "\n", $parts ) ?: '[No output]';
	}
}
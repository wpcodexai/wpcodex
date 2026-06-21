<?php
/**
 * PHP Runner — temp-file sandbox execution engine.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Runner;

/**
 * Class PhpRunner
 *
 * Executes PHP code by writing it to a uniquely named temp file inside
 * ALLY_WORKER_SANDBOX_DIR, including it in the current WordPress process with
 * output buffering, then always deleting the file — even on exception.
 *
 * Returns a structured result array so callers can expose typed output
 * to MCP agents rather than a plain string.
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
	 * Execute PHP code and return a structured result.
	 *
	 * @param string $code PHP code without opening tag.
	 * @return array{success: bool, return_value: mixed, output: string, errors: list<array{type: string, message: string, file: string, line: int}>, error_message: string, error_class: string, execution_time_ms: float}
	 *
	 * @throws \RuntimeException When the sandbox directory is not writable.
	 */
	public function run( string $code ): array {
		$sandbox = ALLY_WORKER_SANDBOX_DIR;

		if ( ! is_dir( $sandbox ) ) {
			wp_mkdir_p( $sandbox );
		}

		if ( ! wp_is_writable( $sandbox ) ) {
			throw new \RuntimeException( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output
				__( 'AllyWorker sandbox directory is not writable. Check plugin activation.', 'allyworker' )
			);
		}

		// Write to a uniquely named temp file.
		$filename = $sandbox . 'exec_' . bin2hex( random_bytes( 8 ) ) . '.php';
		$wrapped  = "<?php\ndeclare(strict_types=1);\n" . $code . "\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $filename, $wrapped ) ) {
			throw new \RuntimeException( __( 'AllyWorker: Failed to write PHP sandbox file.', 'allyworker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output
		}

		/** @var list<array{type: string, message: string, file: string, line: int}> $errors */
		$errors       = [];
		$return_value = null;

		// Install a custom error handler to capture warnings and notices.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- intentional: captures PHP errors from user-supplied code; not for logging
		set_error_handler( static function ( int $errno, string $errstr, string $errfile, int $errline ) use ( &$errors ): bool {
			$type_map = [
				E_WARNING        => 'warning',
				E_NOTICE         => 'notice',
				E_USER_ERROR     => 'user_error',
				E_USER_WARNING   => 'user_warning',
				E_USER_NOTICE    => 'user_notice',
				E_DEPRECATED     => 'deprecated',
				E_USER_DEPRECATED => 'user_deprecated',
			];
			$errors[] = [
				'type'    => $type_map[ $errno ] ?? 'error',
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			];
			return true; // suppress PHP's own error output
		} );

		ob_start();
		$start_ns = hrtime( true );

		try {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			$return_value = include $filename;

			$execution_time_ms = ( hrtime( true ) - $start_ns ) / 1_000_000;
			$output            = (string) ob_get_clean();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $filename );

			restore_error_handler();

			return [
				'success'          => true,
				'return_value'     => ( null !== $return_value && 1 !== $return_value ) ? $return_value : null,
				'output'           => $output,
				'errors'           => $errors,
				'error_message'    => '',
				'error_class'      => '',
				'execution_time_ms' => round( $execution_time_ms, 3 ),
			];

		} catch ( \Throwable $e ) {
			$execution_time_ms = ( hrtime( true ) - $start_ns ) / 1_000_000;
			ob_end_clean();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $filename );

			restore_error_handler();

			return [
				'success'          => false,
				'return_value'     => null,
				'output'           => '',
				'errors'           => $errors,
				'error_message'    => $e->getMessage(),
				'error_class'      => get_class( $e ),
				'execution_time_ms' => round( $execution_time_ms, 3 ),
			];
		}
	}
}

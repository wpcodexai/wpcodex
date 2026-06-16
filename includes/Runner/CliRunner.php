<?php
/**
 * CLI Runner — WP-CLI subprocess wrapper.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Runner;

/**
 * Class CliRunner
 */
class CliRunner {

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
	 * Run a WP-CLI command and return combined output.
	 *
	 * @param string $command  Arguments without the leading "wp".
	 * @param int    $timeout  Seconds before the process is killed.
	 * @return string Combined stdout + stderr.
	 *
	 * @throws \RuntimeException When WP-CLI is not found or process fails to open.
	 */
	public function run( string $command, int $timeout = 30 ): string {
		$wp     = $this->locate_binary();
		$cmd    = escapeshellcmd( $wp )
			. ' ' . $command
			. ' --path=' . escapeshellarg( ABSPATH )
			. ' --no-color 2>&1';

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.proc_open_proc_open, Generic.PHP.ForbiddenFunctions.Found -- proc_open is required for WP-CLI subprocess management
		$process = proc_open( $cmd, $descriptors, $pipes, ABSPATH, $this->safe_env() );

		if ( ! is_resource( $process ) ) {
			throw new \RuntimeException( __( 'WPWorker: Failed to open WP-CLI subprocess.', 'worker-ai' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- pipe I/O from proc_open; WP_Filesystem has no equivalent
		fclose( $pipes[0] );

		$output   = '';
		$deadline = time() + $timeout;

		while ( ! feof( $pipes[1] ) ) {
			if ( time() > $deadline ) {
				proc_terminate( $process );
				$output .= "\n[WPWorker] Command timed out after {$timeout}s.";
				break;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- pipe I/O from proc_open
			$chunk = fread( $pipes[1], 4096 );
			if ( false !== $chunk ) {
				$output .= $chunk;
			}
		}

		$stderr = stream_get_contents( $pipes[2] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- pipe I/O from proc_open
		fclose( $pipes[1] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- pipe I/O from proc_open
		fclose( $pipes[2] );
		proc_close( $process );

		if ( $stderr ) {
			$output .= "\n[stderr]\n" . $stderr;
		}

		return $output ?: '[No output]';
	}

	/**
	 * Locate the WP-CLI binary.
	 *
	 * @throws \RuntimeException When not found.
	 */
	private function locate_binary(): string {
		$candidates = [
			ABSPATH . 'wp-cli.phar',
			'/usr/local/bin/wp',
			'/usr/bin/wp',
		];

		// Use which as last resort — escapeshellcmd before exec.
		$which = trim( (string) shell_exec( escapeshellcmd( 'which wp' ) . ' 2>/dev/null' ) );
		if ( $which ) {
			$candidates[] = $which;
		}

		foreach ( $candidates as $path ) {
			if ( $path && file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		throw new \RuntimeException( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output
			__( 'WPWorker: WP-CLI not found. Install WP-CLI to use this ability.', 'worker-ai' )
		);
	}

	/**
	 * Build subprocess environment with credentials stripped.
	 *
	 * @return array<string, string>
	 */
	private function safe_env(): array {
		$env = getenv();
		if ( ! is_array( $env ) ) {
			return [];
		}
		unset( $env['HTTP_AUTHORIZATION'], $env['WPWORKER_SECRET'] );
		return $env;
	}
}
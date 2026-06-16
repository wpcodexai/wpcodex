<?php
/**
 * Sandbox Loader — loads AI-written PHP files from the sandbox directory.
 *
 * Usage from Plugin::init():
 *   $loader = new SandboxLoader();
 *   $loader->register(); // registers the admin_notices hook
 *   $loader->load();     // loads sandbox files
 *
 * WordPress hooks are registered directly in __construct.
 * load() must be called explicitly from Plugin::init().
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Runner;

use WPWorker\Admin\AdminMenu;

/**
 * Class SandboxLoader
 */
class SandboxLoader {

	/** Filename of the crash marker inside the sandbox directory. */
	private const CRASHED_MARKER = '.crashed';

	/** Absolute path to the sandbox directory. Set in register(). */
	private string $sandbox_dir;

	/** Absolute path to the .crashed marker file. Set in register(). */
	private string $crashed_file;

	public function __construct() {
		$this->sandbox_dir  = WPWORKER_SANDBOX_DIR;
		$this->crashed_file = $this->sandbox_dir . self::CRASHED_MARKER;

		add_action( 'admin_notices', [ $this, 'maybe_show_crash_notice' ] );
	}

	/**
	 * Load all enabled sandbox PHP files.
	 *
	 * Called explicitly from Plugin::init().
	 * Reads the wpworker_abilities_enabled option to decide whether to run
	 * the full crash-recovery path or the lighter no-overhead path.
	 */
	public function load(): void {
		if ( ! is_dir( $this->sandbox_dir ) ) {
			return;
		}

		$abilities_enabled = (bool) get_option( AdminMenu::ABILITIES_ENABLED_OPTION, false );

		if ( ! $abilities_enabled ) {
			$this->load_files_simple();
			return;
		}

		if ( $this->is_safe_mode() ) {
			return;
		}

		$this->load_files_with_crash_recovery();
	}

	/**
	 * Admin notices callback — shows a safe-mode warning when .crashed exists.
	 *
	 * Registered via register() on the admin_notices hook.
	 */
	public function maybe_show_crash_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! file_exists( $this->crashed_file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $this->crashed_file );
		$info = is_string( $raw ) ? json_decode( $raw, true ) : null;

		$file_name = is_array( $info ) ? ( (string) ( $info['sandbox_file'] ?? '' ) ) : '';
		$error_msg = is_array( $info ) ? ( (string) ( $info['message']      ?? '' ) ) : '';

		$notice = sprintf(
			'<strong>%s</strong> %s',
			esc_html__( 'WPWorker Sandbox: Safe mode is active.', 'worker-ai' ),
			esc_html__( 'A sandbox file caused a fatal error. All sandbox files are disabled until you fix or delete the broken file, then delete the .crashed marker from the sandbox directory.', 'worker-ai' )
		);

		if ( '' !== $file_name ) {
			$notice .= ' <code>' . esc_html( $file_name ) . '</code>';
		}
		if ( '' !== $error_msg ) {
			$notice .= ' &mdash; ' . esc_html( $error_msg );
		}

		wp_admin_notice(
			$notice,
			[
				'type'           => 'error',
				'dismissible'    => false,
				'paragraph_wrap' => true,
			]
		);
	}


	/**
	 * Load sandbox files without crash-recovery overhead.
	 * Used when AI Abilities are disabled.
	 */
	private function load_files_simple(): void {
		foreach ( $this->collect_files() as $file ) {
			require_once $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		}
	}

	/**
	 * Load sandbox files with a shutdown-function crash detector.
	 *
	 * $current_sandbox_file is passed by reference to the shutdown handler
	 * so it knows which file was loading when a fatal occurred. It is set
	 * to null after the loop so the handler is a no-op on clean exits.
	 */
	private function load_files_with_crash_recovery(): void {
		$files = $this->collect_files();

		if ( empty( $files ) ) {
			return;
		}

		$current_sandbox_file = null;

		register_shutdown_function(
			function () use ( &$current_sandbox_file ): void {
				$this->handle_shutdown( $current_sandbox_file );
			}
		);

		foreach ( $files as $file ) {
			$current_sandbox_file = $file;
			require_once $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		}

		$current_sandbox_file = null;
	}

	/**
	 * Shutdown handler — writes a .crashed marker when a fatal error occurs
	 * while a sandbox file is being loaded.
	 *
	 * @param string|null $current_sandbox_file The file being loaded at crash time, or null if loading completed cleanly.
	 */
	private function handle_shutdown( ?string $current_sandbox_file ): void {
		if ( null === $current_sandbox_file ) {
			return;
		}

		$error = error_get_last();
		if ( null === $error ) {
			return;
		}

		// Only react to fatal error types that kill execution.
		if ( ! ( $error['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR ) ) ) {
			return;
		}

		$error['sandbox_file'] = $current_sandbox_file;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->crashed_file, (string) wp_json_encode( $error ), LOCK_EX );
	}

	/**
	 * Whether safe mode is currently active.
	 *
	 * True when the .crashed marker exists or ?wpworker_safe_mode=1 is set.
	 */
	private function is_safe_mode(): bool {
		if ( file_exists( $this->crashed_file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['wpworker_safe_mode'] ) && '1' === $_GET['wpworker_safe_mode'];
	}

	/**
	 * Return an ordered list of loadable sandbox PHP files.
	 * Excludes index.php (the directory guard stub).
	 *
	 * @return list<string>
	 */
	private function collect_files(): array {
		$pattern = rtrim( $this->sandbox_dir, '/\\' ) . DIRECTORY_SEPARATOR . '*.php';
		$files   = glob( $pattern );

		if ( ! is_array( $files ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$files,
				static fn( string $f ): bool => basename( $f ) !== 'index.php'
			)
		);
	}
}
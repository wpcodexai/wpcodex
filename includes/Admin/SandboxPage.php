<?php
/**
 * Sandbox admin page — lists PHP files in the wp-allyworker-sandbox directory
 * with enable/disable toggles and delete actions.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Admin;

/**
 * Class SandboxPage
 */
final class SandboxPage {

	/** Option prefix for disabled sandbox files. */
	private const DISABLED_PREFIX = 'allyworker_sandbox_disabled_';

	/** Filename of the crash marker inside the sandbox directory. */
	private const CRASHED_MARKER = '.crashed';

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'allyworker' ) );
		}

		$notices    = self::handle_actions();
		$files      = self::get_sandbox_files();
		$is_crashed = file_exists( ALLY_WORKER_SANDBOX_DIR . self::CRASHED_MARKER );
		?>
		<div class="wrap allyworker-wrap" id="allyworker-sandbox">
			<div class="allyworker-page-header allyworker-flex">
				<h1 class="allyworker-page-title"><?php esc_html_e( 'Sandbox', 'allyworker' ); ?></h1>
			</div>
			<p class="allyworker-page-description">
				<?php
				printf(
					/* translators: %s: sandbox directory path */
					esc_html__( 'PHP files saved to the sandbox by AI agents. Sandbox directory: %s', 'allyworker' ),
					'<code>' . esc_html( ALLY_WORKER_SANDBOX_DIR ) . '</code>'
				);
				?>
			</p>

			<?php self::render_notices( $notices ); ?>

			<?php if ( $is_crashed ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Safe mode is active.', 'allyworker' ); ?></strong>
						<?php esc_html_e( 'A sandbox file caused a fatal error on a previous request. All sandbox files are suspended until you fix or delete the broken file and exit safe mode.', 'allyworker' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( wp_nonce_url(
							admin_url( 'admin.php?page=allyworker-sandbox&sandbox_action=exit_safe_mode&file_name=' . rawurlencode( self::CRASHED_MARKER ) ),
							'allyworker_sandbox_action',
							'allyworker_sandbox_nonce'
						) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Exit Safe Mode', 'allyworker' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $files ) ) : ?>
				<div class="allyworker-empty-state">
					<p><?php esc_html_e( 'The sandbox is empty. PHP files created by the AI agent will appear here.', 'allyworker' ); ?></p>
				</div>
			<?php else : ?>
				<div class="allyworker-cards">
					<?php foreach ( $files as $file ) : ?>
						<?php self::render_file_card( $file, $is_crashed ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_actions(): array {
		// GET-based actions (exit_safe_mode).
		if ( isset( $_GET['sandbox_action'], $_GET['allyworker_sandbox_nonce'] ) ) {
			check_admin_referer( 'allyworker_sandbox_action', 'allyworker_sandbox_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				return [];
			}

			$get_action = sanitize_key( wp_unslash( $_GET['sandbox_action'] ) );

			if ( 'exit_safe_mode' === $get_action ) {
				$crashed_file = ALLY_WORKER_SANDBOX_DIR . self::CRASHED_MARKER;
				if ( file_exists( $crashed_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $crashed_file );
				}
				return [ [ 'type' => 'success', 'message' => __( 'Safe mode deactivated. Sandbox files will load on the next request.', 'allyworker' ) ] ];
			}

			return [];
		}

		// POST-based actions (enable / disable / delete).
		if ( ! isset( $_POST['allyworker_sandbox_nonce'] ) ) {
			return [];
		}

		check_admin_referer( 'allyworker_sandbox_action', 'allyworker_sandbox_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		$action    = sanitize_key( wp_unslash( $_POST['sandbox_action'] ?? '' ) );
		$file_name = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) );

		if ( ! $file_name || str_contains( $file_name, '/' ) || str_contains( $file_name, '\\' ) ) {
			return [ [ 'type' => 'error', 'message' => __( 'Invalid file name.', 'allyworker' ) ] ];
		}

		$full_path = ALLY_WORKER_SANDBOX_DIR . $file_name;

		switch ( $action ) {
			case 'enable':
				delete_option( self::DISABLED_PREFIX . md5( $file_name ) );
				return [ [ 'type' => 'success', 'message' => __( 'File enabled.', 'allyworker' ) ] ];

			case 'disable':
				update_option( self::DISABLED_PREFIX . md5( $file_name ), true, false );
				return [ [ 'type' => 'success', 'message' => __( 'File disabled.', 'allyworker' ) ] ];

			case 'delete':
				if ( file_exists( $full_path ) && is_file( $full_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $full_path );
					delete_option( self::DISABLED_PREFIX . md5( $file_name ) );
					return [ [ 'type' => 'success', 'message' => __( 'File deleted.', 'allyworker' ) ] ];
				}
				return [ [ 'type' => 'error', 'message' => __( 'File not found.', 'allyworker' ) ] ];
		}

		return [];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_sandbox_files(): array {
		$dir = ALLY_WORKER_SANDBOX_DIR;

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files = [];

		foreach ( scandir( $dir ) ?: [] as $entry ) {
			// Skip non-PHP files and protected files.
			if ( ! str_ends_with( $entry, '.php' ) ) {
				continue;
			}
			if ( in_array( $entry, [ 'index.php' ], true ) ) {
				continue;
			}

			$full_path = $dir . $entry;
			$disabled  = (bool) get_option( self::DISABLED_PREFIX . md5( $entry ) );
			$size      = file_exists( $full_path ) ? filesize( $full_path ) : 0;
			$modified  = file_exists( $full_path ) ? (int) filemtime( $full_path ) : 0;

			$files[] = [
				'name'     => $entry,
				'size'     => $size,
				'modified' => $modified,
				'enabled'  => ! $disabled,
				'path'     => $full_path,
			];
		}

		// Sort newest first.
		usort( $files, static fn( $a, $b ) => $b['modified'] <=> $a['modified'] );

		return $files;
	}

	/**
	 * @param array<string, mixed> $file
	 */
	private static function render_file_card( array $file, bool $is_crashed = false ): void {
		$name      = (string) $file['name'];
		$enabled   = (bool) $file['enabled'];
		$size      = (int) $file['size'];
		$modified  = (int) $file['modified'];
		$suspended = $is_crashed;
		?>
		<div class="allyworker-card <?php echo $suspended ? 'is-suspended' : ( $enabled ? 'is-enabled' : 'is-disabled' ); ?>">
			<div class="allyworker-card__header">
				<span class="allyworker-card__name"><?php echo esc_html( $name ); ?></span>
				<div class="allyworker-card__badges">
					<span class="allyworker-badge allyworker-badge--php">PHP</span>
					<?php if ( $suspended ) : ?>
						<span class="allyworker-badge allyworker-badge--warn"><?php esc_html_e( 'Suspended', 'allyworker' ); ?></span>
					<?php elseif ( ! $enabled ) : ?>
						<span class="allyworker-badge allyworker-badge--disabled"><?php esc_html_e( 'Disabled', 'allyworker' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<p class="allyworker-card__meta">
				<?php
				printf(
					/* translators: 1: file size, 2: modified date */
					esc_html__( '%1$s · Modified %2$s', 'allyworker' ),
					esc_html( self::format_bytes( $size ) ),
					esc_html( $modified ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $modified ) : '—' )
				);
				?>
			</p>
			<?php if ( ! $suspended ) : ?>
			<div class="allyworker-card__actions">
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'allyworker_sandbox_action', 'allyworker_sandbox_nonce' ); ?>
					<input type="hidden" name="file_name"      value="<?php echo esc_attr( $name ); ?>">
					<input type="hidden" name="sandbox_action" value="<?php echo $enabled ? 'disable' : 'enable'; ?>">
					<button type="submit" class="button button-small">
						<?php echo $enabled ? esc_html__( 'Disable', 'allyworker' ) : esc_html__( 'Enable', 'allyworker' ); ?>
					</button>
				</form>
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'allyworker_sandbox_action', 'allyworker_sandbox_nonce' ); ?>
					<input type="hidden" name="file_name"      value="<?php echo esc_attr( $name ); ?>">
					<input type="hidden" name="sandbox_action" value="delete">
					<button type="submit" class="button button-small button-link-delete"
					        onclick="return confirm('<?php echo esc_js( __( 'Delete this file permanently?', 'allyworker' ) ); ?>')">
						<?php esc_html_e( 'Delete', 'allyworker' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array{type: string, message: string}[] $notices
	 */
	private static function render_notices( array $notices ): void {
		foreach ( $notices as $notice ) {
			$type = in_array( $notice['type'], [ 'success', 'error', 'warning', 'info' ], true ) ? $notice['type'] : 'info';
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( $notice['message'] )
			);
		}
	}

	private static function format_bytes( int $bytes ): string {
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		return round( $bytes / 1048576, 1 ) . ' MB';
	}
}

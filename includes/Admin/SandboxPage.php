<?php
/**
 * Sandbox admin page — lists PHP files in the wpcodex-sandbox directory
 * with enable/disable toggles and delete actions.
 *
 * Matches Novamira's "Sandbox" page: compact card layout, file-type badges,
 * Enable/Disable toggle, and crash-recovery status.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class SandboxPage
 */
final class SandboxPage {

	/** Option prefix for disabled sandbox files. */
	private const DISABLED_PREFIX = 'wpcodex_sandbox_disabled_';

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$notices = self::handle_actions();
		$files   = self::get_sandbox_files();
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-sandbox">
			<div class="wpcodex-page-header">
				<h1 class="wpcodex-page-title"><?php esc_html_e( 'Sandbox', 'wpcodex' ); ?></h1>
			</div>
			<p class="wpcodex-page-description">
				<?php
				printf(
					/* translators: %s: sandbox directory path */
					esc_html__( 'PHP files saved to the sandbox by AI agents. Sandbox directory: %s', 'wpcodex' ),
					'<code>' . esc_html( WPCODEX_SANDBOX_DIR ) . '</code>'
				);
				?>
			</p>

			<?php self::render_notices( $notices ); ?>

			<?php if ( empty( $files ) ) : ?>
				<div class="wpcodex-empty-state">
					<p><?php esc_html_e( 'The sandbox is empty. PHP files created by the AI agent will appear here.', 'wpcodex' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpcodex-cards">
					<?php foreach ( $files as $file ) : ?>
						<?php self::render_file_card( $file ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Action handler
	// -------------------------------------------------------------------------

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_actions(): array {
		if ( ! isset( $_POST['wpcodex_sandbox_nonce'] ) ) {
			return [];
		}

		check_admin_referer( 'wpcodex_sandbox_action', 'wpcodex_sandbox_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		$action    = sanitize_key( wp_unslash( $_POST['sandbox_action'] ?? '' ) );
		$file_name = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) );

		if ( ! $file_name || str_contains( $file_name, '/' ) || str_contains( $file_name, '\\' ) ) {
			return [ [ 'type' => 'error', 'message' => __( 'Invalid file name.', 'wpcodex' ) ] ];
		}

		$full_path = WPCODEX_SANDBOX_DIR . $file_name;

		switch ( $action ) {
			case 'enable':
				delete_option( self::DISABLED_PREFIX . md5( $file_name ) );
				return [ [ 'type' => 'success', 'message' => __( 'File enabled.', 'wpcodex' ) ] ];

			case 'disable':
				update_option( self::DISABLED_PREFIX . md5( $file_name ), true, false );
				return [ [ 'type' => 'success', 'message' => __( 'File disabled.', 'wpcodex' ) ] ];

			case 'delete':
				if ( file_exists( $full_path ) && is_file( $full_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
					unlink( $full_path );
					delete_option( self::DISABLED_PREFIX . md5( $file_name ) );
					return [ [ 'type' => 'success', 'message' => __( 'File deleted.', 'wpcodex' ) ] ];
				}
				return [ [ 'type' => 'error', 'message' => __( 'File not found.', 'wpcodex' ) ] ];
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// File listing
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_sandbox_files(): array {
		$dir = WPCODEX_SANDBOX_DIR;

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

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $file
	 */
	private static function render_file_card( array $file ): void {
		$name     = (string) $file['name'];
		$enabled  = (bool) $file['enabled'];
		$size     = (int) $file['size'];
		$modified = (int) $file['modified'];
		?>
		<div class="wpcodex-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
			<div class="wpcodex-card__header">
				<span class="wpcodex-card__name"><?php echo esc_html( $name ); ?></span>
				<div class="wpcodex-card__badges">
					<span class="wpcodex-badge wpcodex-badge--php">PHP</span>
					<?php if ( ! $enabled ) : ?>
						<span class="wpcodex-badge wpcodex-badge--disabled"><?php esc_html_e( 'Disabled', 'wpcodex' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<p class="wpcodex-card__meta">
				<?php
				printf(
					/* translators: 1: file size, 2: modified date */
					esc_html__( '%1$s · Modified %2$s', 'wpcodex' ),
					esc_html( self::format_bytes( $size ) ),
					esc_html( $modified ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $modified ) : '—' )
				);
				?>
			</p>
			<div class="wpcodex-card__actions">
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'wpcodex_sandbox_action', 'wpcodex_sandbox_nonce' ); ?>
					<input type="hidden" name="file_name"      value="<?php echo esc_attr( $name ); ?>">
					<input type="hidden" name="sandbox_action" value="<?php echo $enabled ? 'disable' : 'enable'; ?>">
					<button type="submit" class="button button-small">
						<?php echo $enabled ? esc_html__( 'Disable', 'wpcodex' ) : esc_html__( 'Enable', 'wpcodex' ); ?>
					</button>
				</form>
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'wpcodex_sandbox_action', 'wpcodex_sandbox_nonce' ); ?>
					<input type="hidden" name="file_name"      value="<?php echo esc_attr( $name ); ?>">
					<input type="hidden" name="sandbox_action" value="delete">
					<button type="submit" class="button button-small button-link-delete"
					        onclick="return confirm('<?php echo esc_js( __( 'Delete this file permanently?', 'wpcodex' ) ); ?>')">
						<?php esc_html_e( 'Delete', 'wpcodex' ); ?>
					</button>
				</form>
			</div>
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

<?php
/**
 * Block Editor Queue admin page.
 *
 * Lists pending Gutenberg change batches created by AI agents.
 * Batches must be finalized through the browser to apply block changes cleanly
 * via the block editor's JavaScript runtime.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class BlockEditorPage
 */
final class BlockEditorPage {

	/** Option name for the pending batches store. */
	private const BATCHES_OPTION = 'wpcodex_gutenberg_batches';

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$notices = self::handle_actions();
		$batches = self::get_batches();
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-block-editor">
			<div class="wpcodex-page-header">
				<h1 class="wpcodex-page-title"><?php esc_html_e( 'Block Editor', 'wpcodex' ); ?></h1>
			</div>
			<p class="wpcodex-page-description">
				<?php esc_html_e( 'AI agents queue Gutenberg block changes here instead of writing directly to post_content. Open the finalization link in your browser to apply each batch through the block editor\'s JavaScript runtime — this produces valid, editor-trusted block markup.', 'wpcodex' ); ?>
			</p>

			<?php self::render_notices( $notices ); ?>

			<?php if ( empty( $batches ) ) : ?>
				<div class="wpcodex-empty-state">
					<p><?php esc_html_e( 'No pending batches. When an AI agent queues Gutenberg changes, they will appear here.', 'wpcodex' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpcodex-queue-table-wrap">
					<table class="wp-list-table widefat fixed striped wpcodex-queue-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch ID', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Post', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Changes', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Created', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wpcodex' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $batches as $batch ) : ?>
								<?php self::render_batch_row( $batch ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="wpcodex-info-box">
				<h3><?php esc_html_e( 'How it works', 'wpcodex' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'The AI agent calls wpcodex/gutenberg-write-content — changes are queued here, not written directly to the post.', 'wpcodex' ); ?></li>
					<li><?php esc_html_e( 'The agent calls wpcodex/gutenberg-get-finalization-url to get a one-time browser link.', 'wpcodex' ); ?></li>
					<li><?php esc_html_e( 'You open the link in your browser. WordPress loads the block editor, the JS finalizer applies the changes, and saves normally.', 'wpcodex' ); ?></li>
					<li><?php esc_html_e( 'The batch is removed from this queue.', 'wpcodex' ); ?></li>
				</ol>
			</div>
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
		if ( ! isset( $_POST['wpcodex_queue_nonce'] ) ) {
			return [];
		}

		check_admin_referer( 'wpcodex_queue_action', 'wpcodex_queue_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		$action   = sanitize_key( wp_unslash( $_POST['queue_action'] ?? '' ) );
		$batch_id = sanitize_key( wp_unslash( $_POST['batch_id'] ?? '' ) );

		switch ( $action ) {
			case 'delete':
				self::delete_batch( $batch_id );
				return [ [ 'type' => 'success', 'message' => __( 'Batch deleted.', 'wpcodex' ) ] ];

			case 'delete_all':
				update_option( self::BATCHES_OPTION, [], false );
				return [ [ 'type' => 'success', 'message' => __( 'All batches cleared.', 'wpcodex' ) ] ];
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_batches(): array {
		$batches = get_option( self::BATCHES_OPTION, [] );
		return is_array( $batches ) ? array_values( $batches ) : [];
	}

	private static function delete_batch( string $batch_id ): void {
		$batches = self::get_batches();
		$batches = array_filter( $batches, static fn( $b ) => ( $b['id'] ?? '' ) !== $batch_id );
		update_option( self::BATCHES_OPTION, array_values( $batches ), false );
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $batch
	 */
	private static function render_batch_row( array $batch ): void {
		$batch_id    = (string) ( $batch['id'] ?? '' );
		$post_id     = (int) ( $batch['post_id'] ?? 0 );
		$changes     = (int) ( $batch['change_count'] ?? 0 );
		$status      = (string) ( $batch['status'] ?? 'pending' );
		$created_at  = (int) ( $batch['created_at'] ?? 0 );
		$finalize_url = (string) ( $batch['finalize_url'] ?? '' );

		$post_title = $post_id ? get_the_title( $post_id ) : '—';
		$edit_url   = $post_id ? get_edit_post_link( $post_id ) : '';

		$status_labels = [
			'pending'  => __( 'Pending', 'wpcodex' ),
			'ready'    => __( 'Ready to finalize', 'wpcodex' ),
			'complete' => __( 'Finalized', 'wpcodex' ),
		];
		?>
		<tr>
			<td><code><?php echo esc_html( $batch_id ); ?></code></td>
			<td>
				<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $post_title ?: "#$post_id" ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $post_title ?: "Post #$post_id" ); ?>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $changes ); ?></td>
			<td>
				<span class="wpcodex-status wpcodex-status--<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
				</span>
			</td>
			<td>
				<?php echo $created_at
					? esc_html( (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at ) )
					: '—'; ?>
			</td>
			<td>
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<?php if ( $finalize_url ) : ?>
						<a href="<?php echo esc_url( $finalize_url ); ?>"
						   target="_blank"
						   class="button button-small button-primary">
							<?php esc_html_e( 'Finalize in editor', 'wpcodex' ); ?>
						</a>
					<?php endif; ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'wpcodex_queue_action', 'wpcodex_queue_nonce' ); ?>
						<input type="hidden" name="queue_action" value="delete">
						<input type="hidden" name="batch_id"     value="<?php echo esc_attr( $batch_id ); ?>">
						<button type="submit" class="button button-small button-link-delete"
						        onclick="return confirm('<?php echo esc_js( __( 'Delete this batch?', 'wpcodex' ) ); ?>')">
							<?php esc_html_e( 'Delete', 'wpcodex' ); ?>
						</button>
					</form>
				</div>
			</td>
		</tr>
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
}

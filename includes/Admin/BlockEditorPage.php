<?php
/**
 * Block Editor Queue admin page.
 *
 * Lists pending Gutenberg change batches created by AI agents.
 * Batches are finalized automatically once the user opens the Block Editor
 * Queue page — the browser-side JS runtime handles serialization via iframe.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Admin;

use WP_Post;
use WPWorker\Utils\GutenbergStorage;

/**
 * Class BlockEditorPage
 */
final class BlockEditorPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'worker-ai' ) );
		}

		$notices = self::handle_actions();
		$batches = self::get_batches();
		?>
		<div class="wrap wpworker-wrap" id="wpworker-block-editor">
			<div class="wpworker-page-header wpworker-flex">
				<h1 class="wpworker-page-title"><?php esc_html_e( 'Block Editor', 'worker-ai' ); ?></h1>
			</div>
			<p class="wpworker-page-description">
				<?php esc_html_e( 'AI agents queue Gutenberg block changes here instead of writing directly to post_content. The browser-side JS finalizer processes each batch automatically — keep this page open in a background tab while an active session is running. You can also open the finalization link for any batch to trigger it manually.', 'worker-ai' ); ?>
			</p>

			<?php self::render_notices( $notices ); ?>

			<?php if ( empty( $batches ) ) : ?>
				<div class="wpworker-empty-state">
					<p><?php esc_html_e( 'No pending batches. When an AI agent queues Gutenberg changes, they will appear here.', 'worker-ai' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpworker-queue-table-wrap">
					<table class="wp-list-table widefat fixed striped wpworker-queue-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'worker-ai' ); ?></th>
								<th><?php esc_html_e( 'Items', 'worker-ai' ); ?></th>
								<th><?php esc_html_e( 'Status', 'worker-ai' ); ?></th>
								<th><?php esc_html_e( 'Created', 'worker-ai' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'worker-ai' ); ?></th>
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

			<div class="wpworker-info-box">
				<h3><?php esc_html_e( 'How it works', 'worker-ai' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'The AI agent calls wpworker/gutenberg-write-content — changes are queued here, not written directly to the post.', 'worker-ai' ); ?></li>
					<li><?php esc_html_e( 'The JS finalizer on this page auto-processes ready batches via a hidden iframe. Keep this tab open in the background during active sessions.', 'worker-ai' ); ?></li>
					<li><?php esc_html_e( 'Alternatively, the agent calls wpworker/gutenberg-get-finalization-url and gives you a direct link to trigger finalization manually.', 'worker-ai' ); ?></li>
					<li><?php esc_html_e( 'Once finalized the batch is removed from the active queue.', 'worker-ai' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_actions(): array {
		if ( ! isset( $_POST['wpworker_queue_nonce'] ) ) {
			return [];
		}

		check_admin_referer( 'wpworker_queue_action', 'wpworker_queue_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		$action   = sanitize_key( wp_unslash( $_POST['queue_action'] ?? '' ) );
		$batch_id = (int) ( $_POST['batch_id'] ?? 0 );

		switch ( $action ) {
			case 'cancel':
				$result = GutenbergStorage::cancel_batch( $batch_id );
				if ( is_wp_error( $result ) ) {
					return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
				}
				return [ [ 'type' => 'success', 'message' => __( 'Batch cancelled.', 'worker-ai' ) ] ];

			case 'cancel_all':
				$cancelled = 0;
				foreach ( GutenbergStorage::get_batches( GutenbergStorage::NON_TERMINAL_STATUSES ) as $batch ) {
					if ( ! GutenbergStorage::current_user_can_finalize_batch( $batch ) ) {
						continue;
					}
					$r = GutenbergStorage::cancel_batch( $batch->ID );
					if ( ! is_wp_error( $r ) ) {
						$cancelled++;
					}
				}
				return [ [ 'type' => 'success', 'message' => sprintf(
					/* translators: %d: number of batches cancelled */
					__( '%d batch(es) cancelled.', 'worker-ai' ),
					$cancelled
				) ] ];
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	/**
	 * Return all non-terminal batches the current user can finalize, as shaped arrays.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_batches(): array {
		GutenbergStorage::mark_stale_drafts();
		$posts  = GutenbergStorage::get_batches( null, 50 );
		$result = [];
		foreach ( $posts as $post ) {
			if ( ! GutenbergStorage::current_user_can_finalize_batch( $post ) ) {
				continue;
			}
			$post    = GutenbergStorage::refresh_batch_runtime_state( $post );
			$result[] = GutenbergStorage::shape_batch_summary( $post );
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $batch
	 */
	private static function render_batch_row( array $batch ): void {
		$batch_id       = (int) ( $batch['batch_id'] ?? 0 );
		$label          = (string) ( $batch['label'] ?? '' );
		$agent_label    = (string) ( $batch['agent_label'] ?? '' );
		$item_count     = (int) ( $batch['item_count'] ?? 0 );
		$status         = (string) ( $batch['status'] ?? 'draft' );
		$created_at     = (string) ( $batch['created_at'] ?? '' );
		$finalize_url   = (string) ( $batch['finalization_url'] ?? '' );
		$last_error     = (string) ( $batch['last_error'] ?? '' );

		$created_ts = $created_at !== '' ? strtotime( $created_at ) : 0;

		$status_labels = [
			GutenbergStorage::STATUS_DRAFT      => __( 'Draft', 'worker-ai' ),
			GutenbergStorage::STATUS_READY      => __( 'Ready', 'worker-ai' ),
			GutenbergStorage::STATUS_RUNNING    => __( 'Finalizing…', 'worker-ai' ),
			GutenbergStorage::STATUS_PREPARED   => __( 'Prepared', 'worker-ai' ),
			GutenbergStorage::STATUS_FINALIZED  => __( 'Finalized', 'worker-ai' ),
			GutenbergStorage::STATUS_FAILED     => __( 'Failed', 'worker-ai' ),
			GutenbergStorage::STATUS_CONFLICTED => __( 'Conflicted', 'worker-ai' ),
			GutenbergStorage::STATUS_CANCELED   => __( 'Cancelled', 'worker-ai' ),
			GutenbergStorage::STATUS_STALE      => __( 'Stale', 'worker-ai' ),
		];
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( $label !== '' ? $label : sprintf( '#%d', $batch_id ) ); ?></strong>
				<?php if ( $agent_label !== '' && $agent_label !== 'the originating agent' ) : ?>
					<br><span style="color:#777;font-size:.8125rem;"><?php echo esc_html( $agent_label ); ?></span>
				<?php endif; ?>
				<?php if ( $last_error !== '' ) : ?>
					<br><span style="color:#d63638;font-size:.8125rem;" title="<?php echo esc_attr( $last_error ); ?>">
						<?php esc_html_e( 'Error (hover for details)', 'worker-ai' ); ?>
					</span>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( (string) $item_count ); ?></td>
			<td>
				<span class="wpworker-status wpworker-status--<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
				</span>
			</td>
			<td>
				<?php echo $created_ts
					? esc_html( (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_ts ) )
					: esc_html( $created_at ); ?>
			</td>
			<td>
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<?php if ( $finalize_url !== '' && ! in_array( $status, GutenbergStorage::TERMINAL_STATUSES, true ) ) : ?>
						<a href="<?php echo esc_url( $finalize_url ); ?>"
						   target="_blank"
						   class="button button-small button-primary">
							<?php esc_html_e( 'Finalize in editor', 'worker-ai' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( ! in_array( $status, GutenbergStorage::TERMINAL_STATUSES, true ) ) : ?>
						<form method="post" action="">
							<?php wp_nonce_field( 'wpworker_queue_action', 'wpworker_queue_nonce' ); ?>
							<input type="hidden" name="queue_action" value="cancel">
							<input type="hidden" name="batch_id"     value="<?php echo esc_attr( (string) $batch_id ); ?>">
							<button type="submit" class="button button-small button-link-delete"
							        onclick="return confirm('<?php echo esc_js( __( 'Cancel this batch?', 'worker-ai' ) ); ?>')">
								<?php esc_html_e( 'Cancel', 'worker-ai' ); ?>
							</button>
						</form>
					<?php endif; ?>
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

<?php
/**
 * Gutenberg CPT queue — storage and finalizer-runtime engine.
 *
 * Central static utility class for the Block Editor Queue system.
 * Manages the wpcodex_gb_change CPT, batch/item lifecycle, lease-based
 * concurrency, and the browser-side JS finalizer runtime heartbeat.
 *
 * @package WPCodex
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPCodex\Utils;

use WP_Block_Type_Registry;
use WP_Error;
use WP_Post;

/**
 * Class GutenbergStorage
 *
 * Static utility class — all state lives in the WordPress database.
 * Instantiate once to wire the CPT registration and cleanup hooks;
 * call all other methods statically as GutenbergStorage::method_name().
 *
 * @since 1.0.0
 */
class GutenbergStorage {

	/**
	 * Register the CPT registration, cron schedule, and cleanup hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_storage' ] );
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( 'wpcodex_gutenberg_cleanup', [ $this, 'cleanup_queue' ] );
	}

	/** @var string Custom post type slug for both batches and items. */
	public const POST_TYPE = 'wpcodex_gb_change';

	/** @var string Meta value for batch posts. */
	public const KIND_BATCH = 'batch';

	/** @var string Meta value for item posts. */
	public const KIND_ITEM = 'item';

	public const META_KIND              = '_wpcodex_gb_kind';
	public const META_STATUS            = '_wpcodex_gb_status';
	public const META_STATUS_UPDATED_AT = '_wpcodex_gb_status_updated_at';
	public const META_AGENT_LABEL       = '_wpcodex_gb_agent_label';
	public const META_AGENT_SESSION_ID  = '_wpcodex_gb_agent_session_id';
	public const META_READY_AT          = '_wpcodex_gb_ready_at';
	public const META_FINALIZED_AT      = '_wpcodex_gb_finalized_at';
	public const META_LEASE_OWNER       = '_wpcodex_gb_lease_owner';
	public const META_LEASE_EXPIRES_AT  = '_wpcodex_gb_lease_expires_at';
	public const META_LAST_ERROR        = '_wpcodex_gb_last_error';
	public const META_TARGET_ID         = '_wpcodex_gb_target_id';
	public const META_TARGET_TYPE       = '_wpcodex_gb_target_type';
	public const META_OPERATION         = '_wpcodex_gb_operation';
	public const META_BASE_CONTENT_HASH = '_wpcodex_gb_base_content_hash';
	public const META_BASE_CONTENT      = '_wpcodex_gb_base_content';
	public const META_BASE_REVISION_ID  = '_wpcodex_gb_base_revision_id';
	public const META_SPEC_HASH         = '_wpcodex_gb_spec_hash';
	public const META_BLOCK_SPEC        = '_wpcodex_gb_block_spec';
	public const META_VALIDATION_ERRORS = '_wpcodex_gb_validation_errors';
	public const META_FINALIZATION_MODE = '_wpcodex_gb_finalization_mode';
	public const META_FINALIZED_CONTENT = '_wpcodex_gb_finalized_content';

	// -------------------------------------------------------------------------
	// Status constants
	// -------------------------------------------------------------------------

	public const STATUS_DRAFT      = 'draft';
	public const STATUS_READY      = 'ready';
	public const STATUS_RUNNING    = 'running';
	public const STATUS_PREPARED   = 'prepared';
	public const STATUS_FINALIZED  = 'finalized';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_CONFLICTED = 'conflicted';
	public const STATUS_CANCELED   = 'canceled';
	public const STATUS_STALE      = 'stale';

	/**
	 * Statuses that are not yet terminal (batch/item still active).
	 *
	 * @var list<string>
	 */
	public const NON_TERMINAL_STATUSES = [
		self::STATUS_DRAFT,
		self::STATUS_READY,
		self::STATUS_RUNNING,
		self::STATUS_PREPARED,
		self::STATUS_FAILED,
		self::STATUS_CONFLICTED,
	];

	/**
	 * Statuses that are terminal (batch/item lifecycle ended).
	 *
	 * @var list<string>
	 */
	public const TERMINAL_STATUSES = [
		self::STATUS_FINALIZED,
		self::STATUS_CANCELED,
		self::STATUS_STALE,
	];

	// -------------------------------------------------------------------------
	// Timing constants
	// -------------------------------------------------------------------------

	/** @var int Seconds before an unfinished draft batch is marked stale. */
	private const DRAFT_STALE_SECONDS = 86_400;

	/** @var int Seconds a finalizer lease remains valid. */
	private const LEASE_SECONDS = 300;

	/** @var int Seconds a status-transition lock is held. */
	private const STATUS_LOCK_SECONDS = 60;

	/** @var int Number of claim attempts before giving up. */
	private const ITEM_CLAIM_ATTEMPTS = 3;

	/** @var int Seconds to retain terminal batches before hard-deletion. */
	private const RETENTION_SECONDS = 1_209_600;

	/** @var int Seconds after activation before the first cron cleanup fires. */
	private const CLEANUP_START_DELAY = 3600;

	// -------------------------------------------------------------------------
	// Finalizer runtime constants
	// -------------------------------------------------------------------------

	/** @var string Transient key for finalizer runtime heartbeat records. */
	public const FINALIZER_RUNTIME_TRANSIENT = 'wpcodex_gb_finalizer_runtimes';

	/** @var string Option key for the token-gated poll/SSE token. */
	public const FINALIZER_RUNTIME_POLL_TOKEN_OPTION = 'wpcodex_gb_finalizer_poll_token';

	/** @var int Random bytes used when generating the poll token. */
	private const FINALIZER_RUNTIME_POLL_TOKEN_BYTES = 16;

	/** @var int Seconds after last heartbeat before a runtime is considered offline. */
	private const FINALIZER_RUNTIME_STALE_SECONDS = 45;

	/** @var int Transient TTL for the finalizer runtime records. */
	private const FINALIZER_RUNTIME_TTL_SECONDS = 120;

	// -------------------------------------------------------------------------
	// Bootstrap / hook registration
	// -------------------------------------------------------------------------

	/**
	 * Register the CPT, post meta keys, and cron event.
	 *
	 * Attach via:
	 *   add_action( 'init', [ GutenbergStorage::class, 'register_storage' ] );
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_storage(): void {
		register_post_type( self::POST_TYPE, [
			'label'           => __( 'Gutenberg pending changes', 'wpcodex' ),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'supports'        => [ 'title', 'excerpt' ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'has_archive'     => false,
			'rewrite'         => false,
			'query_var'       => false,
		] );

		$string_meta_keys = [
			self::META_KIND,
			self::META_STATUS,
			self::META_STATUS_UPDATED_AT,
			self::META_AGENT_LABEL,
			self::META_AGENT_SESSION_ID,
			self::META_READY_AT,
			self::META_FINALIZED_AT,
			self::META_LEASE_OWNER,
			self::META_LEASE_EXPIRES_AT,
			self::META_LAST_ERROR,
			self::META_TARGET_TYPE,
			self::META_OPERATION,
			self::META_BASE_CONTENT_HASH,
			self::META_BASE_CONTENT,
			self::META_SPEC_HASH,
			self::META_BLOCK_SPEC,
			self::META_FINALIZATION_MODE,
			self::META_FINALIZED_CONTENT,
		];

		foreach ( $string_meta_keys as $key ) {
			register_post_meta( self::POST_TYPE, $key, [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn( mixed $v ): string => is_scalar( $v ) ? (string) $v : '',
			] );
		}

		foreach ( [ self::META_TARGET_ID, self::META_BASE_REVISION_ID ] as $key ) {
			register_post_meta( self::POST_TYPE, $key, [
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn( mixed $v ): int => is_scalar( $v ) ? (int) $v : 0,
			] );
		}
	}

	/**
	 * Schedule the daily queue cleanup cron event if not already scheduled.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_cleanup(): void {
		if ( wp_next_scheduled( 'wpcodex_gutenberg_cleanup' ) !== false ) {
			return;
		}
		wp_schedule_event(
			time() + self::CLEANUP_START_DELAY,
			'daily',
			'wpcodex_gutenberg_cleanup'
		);
	}

	/**
	 * Unschedule the daily cleanup cron event (called on plugin deactivation).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unschedule_cleanup(): void {
		wp_clear_scheduled_hook( 'wpcodex_gutenberg_cleanup' );
	}

	/**
	 * Run the queue cleanup: mark stale drafts, age out old batches, hard-delete terminal ones.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function cleanup_queue(): void {
		self::mark_stale_drafts();
		self::mark_old_failed_batches_stale();

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_SECONDS );
		foreach ( self::get_batches( self::TERMINAL_STATUSES, -1 ) as $batch ) {
			$updated_at = self::meta_string( $batch->ID, self::META_STATUS_UPDATED_AT );
			if ( $updated_at === '' || strcmp( $updated_at, $cutoff ) > 0 ) {
				continue;
			}
			foreach ( self::get_items( $batch->ID ) as $item ) {
				wp_delete_post( $item->ID, true );
			}
			wp_delete_post( $batch->ID, true );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the current UTC time as a MySQL datetime string.
	 *
	 * @since 1.0.0
	 * @return string MySQL datetime, e.g. "2024-06-01 12:00:00".
	 */
	public static function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Read a post-meta value as a string.
	 *
	 * @since 1.0.0
	 * @param int    $post_id WordPress post ID.
	 * @param string $key     Meta key.
	 * @return string Meta value, or empty string when not set.
	 */
	public static function meta_string( int $post_id, string $key ): string {
		/** @var mixed $value */
		$value = get_post_meta( $post_id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Read a post-meta value as an integer.
	 *
	 * @since 1.0.0
	 * @param int    $post_id WordPress post ID.
	 * @param string $key     Meta key.
	 * @return int Meta value, or 0 when not set.
	 */
	public static function meta_int( int $post_id, string $key ): int {
		/** @var mixed $value */
		$value = get_post_meta( $post_id, $key, true );
		return is_scalar( $value ) ? (int) $value : 0;
	}

	/**
	 * Return the current gb_status for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Batch or item post ID.
	 * @return string Status string; defaults to STATUS_DRAFT when meta is missing.
	 */
	public static function gb_status( int $post_id ): string {
		$s = self::meta_string( $post_id, self::META_STATUS );
		return $s !== '' ? $s : self::STATUS_DRAFT;
	}

	/**
	 * Unconditionally set a new status on a post, recording the timestamp.
	 *
	 * @since 1.0.0
	 * @param int    $post_id WordPress post ID.
	 * @param string $status  New status value.
	 * @return void
	 */
	public static function set_status( int $post_id, string $status ): void {
		update_post_meta( $post_id, self::META_STATUS, $status );
		update_post_meta( $post_id, self::META_STATUS_UPDATED_AT, self::now_mysql() );
		if ( $status === self::STATUS_FINALIZED ) {
			update_post_meta( $post_id, self::META_FINALIZED_AT, self::now_mysql() );
		}
	}

	/**
	 * Atomically transition a post from one of the allowed statuses to a new status.
	 *
	 * Uses an option-based mutex to prevent concurrent transitions.
	 *
	 * @since 1.0.0
	 * @param int          $post_id       Post ID to transition.
	 * @param list<string> $from_statuses Allowed current statuses.
	 * @param string       $to_status     Target status.
	 * @return bool True if the transition succeeded; false if the post was in a disallowed status or the lock could not be acquired.
	 */
	public static function atomic_status_transition( int $post_id, array $from_statuses, string $to_status ): bool {
		if ( $from_statuses === [] ) {
			return false;
		}
		$lock_owner = self::status_transition_lock( $post_id );
		if ( $lock_owner === '' ) {
			return false;
		}
		try {
			if ( ! in_array( self::gb_status( $post_id ), $from_statuses, true ) ) {
				return false;
			}
			self::set_status( $post_id, $to_status );
			return true;
		} finally {
			self::release_status_transition_lock( $post_id, $lock_owner );
		}
	}

	/**
	 * Acquire the status-transition mutex for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return string Lock owner UUID on success; empty string if the lock is held.
	 */
	private static function status_transition_lock( int $post_id ): string {
		$option  = sprintf( 'wpcodex_gb_status_lock_%d', $post_id );
		$owner   = (string) wp_generate_uuid4();
		$payload = self::status_lock_payload( $owner );

		if ( add_option( $option, $payload, '', false ) ) {
			return $owner;
		}
		if ( ! self::status_lock_is_stale( get_option( $option, '' ) ) ) {
			return '';
		}
		delete_option( $option );
		return add_option( $option, $payload, '', false ) ? $owner : '';
	}

	/**
	 * Build the payload string for a status-transition lock option.
	 *
	 * @since 1.0.0
	 * @param string $owner Lock owner UUID.
	 * @return string "{owner}|{expires_timestamp}".
	 */
	private static function status_lock_payload( string $owner ): string {
		return $owner . '|' . (string) ( time() + self::STATUS_LOCK_SECONDS );
	}

	/**
	 * Determine whether an existing status-transition lock option is stale.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw option value.
	 * @return bool True if the lock has expired or is malformed.
	 */
	private static function status_lock_is_stale( mixed $value ): bool {
		if ( ! is_string( $value ) ) {
			return true;
		}
		$parts = explode( '|', $value, 2 );
		if ( count( $parts ) !== 2 ) {
			return true;
		}
		$expires = is_numeric( $parts[1] ) ? (int) $parts[1] : 0;
		return $expires <= time();
	}

	/**
	 * Release the status-transition lock if we still own it.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $owner   Lock owner UUID returned by status_transition_lock().
	 * @return void
	 */
	private static function release_status_transition_lock( int $post_id, string $owner ): void {
		$option = sprintf( 'wpcodex_gb_status_lock_%d', $post_id );
		/** @var mixed $value */
		$value = get_option( $option, '' );
		if ( is_string( $value ) && str_starts_with( $value, $owner . '|' ) ) {
			delete_option( $option );
		}
	}

	/**
	 * Clear the finalizer lease meta from a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Batch or item post ID.
	 * @return void
	 */
	public static function clear_lease( int $post_id ): void {
		delete_post_meta( $post_id, self::META_LEASE_OWNER );
		delete_post_meta( $post_id, self::META_LEASE_EXPIRES_AT );
	}

	/**
	 * Write a new finalizer lease onto a post.
	 *
	 * @since 1.0.0
	 * @param int    $post_id     Batch or item post ID.
	 * @param string $lease_owner Opaque UUID identifying the lease holder.
	 * @return void
	 */
	public static function set_lease( int $post_id, string $lease_owner ): void {
		update_post_meta( $post_id, self::META_LEASE_OWNER, $lease_owner );
		update_post_meta(
			$post_id,
			self::META_LEASE_EXPIRES_AT,
			gmdate( 'Y-m-d H:i:s', time() + self::LEASE_SECONDS )
		);
	}

	/**
	 * Check whether a lease is currently valid for a post.
	 *
	 * @since 1.0.0
	 * @param int    $post_id     Batch or item post ID.
	 * @param string $lease_owner Lease owner to validate.
	 * @return bool True if the stored lease matches and has not expired.
	 */
	public static function lease_is_valid( int $post_id, string $lease_owner ): bool {
		if ( $lease_owner === '' || self::meta_string( $post_id, self::META_LEASE_OWNER ) !== $lease_owner ) {
			return false;
		}
		$expires_at = self::meta_string( $post_id, self::META_LEASE_EXPIRES_AT );
		if ( $expires_at === '' ) {
			return false;
		}
		$expires = strtotime( $expires_at . ' UTC' );
		return $expires !== false && $expires > time();
	}

	/**
	 * Fetch a batch post by ID, or null if not found / wrong type.
	 *
	 * @since 1.0.0
	 * @param int $batch_id Post ID.
	 * @return WP_Post|null Batch post, or null on mismatch.
	 */
	public static function find_batch( int $batch_id ): ?WP_Post {
		/** @var WP_Post|null $post */
		$post = get_post( $batch_id );
		if (
			! $post instanceof WP_Post
			|| $post->post_type !== self::POST_TYPE
			|| self::meta_string( $post->ID, self::META_KIND ) !== self::KIND_BATCH
		) {
			return null;
		}
		return $post;
	}

	/**
	 * Fetch an item post by ID, or null if not found / wrong type.
	 *
	 * @since 1.0.0
	 * @param int $item_id Post ID.
	 * @return WP_Post|null Item post, or null on mismatch.
	 */
	public static function find_item( int $item_id ): ?WP_Post {
		/** @var WP_Post|null $post */
		$post = get_post( $item_id );
		if (
			! $post instanceof WP_Post
			|| $post->post_type !== self::POST_TYPE
			|| self::meta_string( $post->ID, self::META_KIND ) !== self::KIND_ITEM
		) {
			return null;
		}
		return $post;
	}

	/**
	 * Query batches, optionally filtered by status.
	 *
	 * @since 1.0.0
	 * @param list<string>|null $statuses       Status filter; null returns all statuses.
	 * @param int               $posts_per_page Maximum results (-1 for all).
	 * @return list<WP_Post>
	 */
	public static function get_batches( ?array $statuses = null, int $posts_per_page = 50 ): array {
		$meta_query = [
			[ 'key' => self::META_KIND, 'value' => self::KIND_BATCH ],
		];
		if ( $statuses !== null ) {
			$meta_query[] = [
				'key'     => self::META_STATUS,
				'value'   => $statuses,
				'compare' => 'IN',
			];
		}
		/** @var list<WP_Post> */
		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => $posts_per_page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query is the correct WP_Query mechanism here; no viable alternative
		] );
	}

	/**
	 * Query items belonging to a batch, optionally filtered by status.
	 *
	 * @since 1.0.0
	 * @param int               $batch_id Batch post ID.
	 * @param list<string>|null $statuses Status filter; null returns all statuses.
	 * @return list<WP_Post>
	 */
	public static function get_items( int $batch_id, ?array $statuses = null ): array {
		$meta_query = [
			[ 'key' => self::META_KIND, 'value' => self::KIND_ITEM ],
		];
		if ( $statuses !== null ) {
			$meta_query[] = [
				'key'     => self::META_STATUS,
				'value'   => $statuses,
				'compare' => 'IN',
			];
		}
		/** @var list<WP_Post> */
		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'post_parent'    => $batch_id,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query is the correct WP_Query mechanism here; no viable alternative
		] );
	}

	/**
	 * Create a new draft batch post.
	 *
	 * @since 1.0.0
	 * @param string $label            Human-readable batch title.
	 * @param string $agent_label      Identifying label for the originating agent.
	 * @param string $agent_session_id Session/conversation ID of the agent.
	 * @param string $agent_note       Longer description of what the batch accomplishes.
	 * @return int|WP_Error New batch post ID on success; WP_Error on failure.
	 */
	public static function create_batch(
		string $label,
		string $agent_label,
		string $agent_session_id,
		string $agent_note
	): int|WP_Error {
		$label            = trim( $label ) !== '' ? sanitize_text_field( $label ) : __( 'Untitled Gutenberg batch', 'wpcodex' );
		$agent_label      = sanitize_text_field( $agent_label );
		$agent_session_id = sanitize_text_field( $agent_session_id );

		$result = wp_insert_post( [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $label,
			'post_excerpt' => wp_strip_all_tags( $agent_note ),
			'post_content' => '',
		], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$batch_id = (int) $result;
		update_post_meta( $batch_id, self::META_KIND, self::KIND_BATCH );
		update_post_meta( $batch_id, self::META_STATUS, self::STATUS_DRAFT );
		update_post_meta( $batch_id, self::META_STATUS_UPDATED_AT, self::now_mysql() );
		update_post_meta( $batch_id, self::META_AGENT_LABEL, $agent_label );
		update_post_meta( $batch_id, self::META_AGENT_SESSION_ID, $agent_session_id );

		return $batch_id;
	}

	/**
	 * Create a new item post inside an existing batch.
	 *
	 * @since 1.0.0
	 * @param int                        $batch_id    Parent batch post ID.
	 * @param int                        $target_id   Target post/page/template ID.
	 * @param string                     $target_type Target post_type.
	 * @param string                     $operation   Change operation (e.g. "replace-content").
	 * @param list<array<string, mixed>> $blocks      Normalized top-level block specs.
	 * @return int|WP_Error New item post ID on success; WP_Error on failure.
	 */
	public static function create_item(
		int $batch_id,
		int $target_id,
		string $target_type,
		string $operation,
		array $blocks
	): int|WP_Error {
		$target = self::get_target( $target_id );
		if ( ! $target instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_target_not_found', sprintf( 'Target post %d was not found.', $target_id ) );
		}

		$encoded = wp_json_encode( $blocks );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', 'The block_spec could not be encoded as JSON.' );
		}

		$result = wp_insert_post( [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_parent'  => $batch_id,
			'post_title'   => self::target_title( $target ),
			'post_content' => '',
			'post_excerpt' => self::item_change_summary( $target, $operation, $blocks ),
		], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$item_id = (int) $result;
		update_post_meta( $item_id, self::META_KIND, self::KIND_ITEM );
		update_post_meta( $item_id, self::META_STATUS, self::STATUS_DRAFT );
		update_post_meta( $item_id, self::META_STATUS_UPDATED_AT, self::now_mysql() );
		update_post_meta( $item_id, self::META_TARGET_ID, $target_id );
		update_post_meta( $item_id, self::META_TARGET_TYPE, $target_type );
		update_post_meta( $item_id, self::META_OPERATION, $operation );
		update_post_meta( $item_id, self::META_BASE_CONTENT_HASH, self::content_hash( $target->post_content ) );
		update_post_meta( $item_id, self::META_BASE_CONTENT, wp_slash( $target->post_content ) );
		update_post_meta( $item_id, self::META_BASE_REVISION_ID, self::latest_revision_id( $target_id ) );
		update_post_meta( $item_id, self::META_SPEC_HASH, hash( 'sha256', $encoded ) );
		update_post_meta( $item_id, self::META_BLOCK_SPEC, wp_slash( $encoded ) );
		update_post_meta( $item_id, self::META_FINALIZATION_MODE, 'js' );

		return $item_id;
	}

	/**
	 * Fetch a target post by ID, or null if not found.
	 *
	 * @since 1.0.0
	 * @param int $target_id WordPress post ID.
	 * @return WP_Post|null The post object, or null.
	 */
	public static function get_target( int $target_id ): ?WP_Post {
		/** @var WP_Post|null $post */
		$post = get_post( $target_id );
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Extract the target_id (or post_id alias) from an input array.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $input Ability input args.
	 * @return int Target ID, or 0 if not present.
	 */
	public static function input_target_id( array $input ): int {
		if ( array_key_exists( 'target_id', $input ) ) {
			return is_scalar( $input['target_id'] ) ? (int) $input['target_id'] : 0;
		}
		if ( array_key_exists( 'post_id', $input ) ) {
			return is_scalar( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		}
		return 0;
	}

	/**
	 * Extract the target_type (or post_type alias) from an input array.
	 *
	 * Falls back to the target post's registered post_type when not supplied.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $input  Ability input args.
	 * @param WP_Post              $target The resolved target post.
	 * @return string Post type string.
	 */
	public static function input_target_type( array $input, WP_Post $target ): string {
		if ( array_key_exists( 'target_type', $input ) ) {
			return is_scalar( $input['target_type'] ) && (string) $input['target_type'] !== ''
				? (string) $input['target_type']
				: $target->post_type;
		}
		if ( array_key_exists( 'post_type', $input ) ) {
			return is_scalar( $input['post_type'] ) && (string) $input['post_type'] !== ''
				? (string) $input['post_type']
				: $target->post_type;
		}
		return $target->post_type;
	}

	/**
	 * Return a human-readable title for a target post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $target Target post.
	 * @return string Title string, or "(no title) #ID" fallback.
	 */
	public static function target_title( WP_Post $target ): string {
		$title = trim( $target->post_title );
		return $title !== ''
			? $title
			/* translators: %d: post ID */
			: sprintf( __( '(no title) #%d', 'wpcodex' ), $target->ID );
	}

	/**
	 * Return a SHA-256 hash of post_content for conflict detection.
	 *
	 * @since 1.0.0
	 * @param string $content Raw post_content.
	 * @return string Hex hash string.
	 */
	public static function content_hash( string $content ): string {
		return hash( 'sha256', $content );
	}

	/**
	 * Return the latest revision post ID for a target, or 0 when none exist.
	 *
	 * @since 1.0.0
	 * @param int $target_id Target post ID.
	 * @return int Revision post ID, or 0.
	 */
	public static function latest_revision_id( int $target_id ): int {
		/** @var list<WP_Post> $revisions */
		$revisions = wp_get_post_revisions( $target_id, [
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );
		if ( $revisions === [] ) {
			return 0;
		}
		$revision = reset( $revisions );
		return $revision->ID;
	}

	/**
	 * Normalize a raw block_spec value into a validated list of block arrays.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw input (should be an array of block objects).
	 * @return list<array<string, mixed>>|WP_Error Normalized blocks, or WP_Error on invalid input.
	 */
	public static function normalize_blocks( mixed $value ): array|WP_Error {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', 'block_spec must be an array of Gutenberg block objects.' );
		}
		if ( ( $value['name'] ?? null ) !== null && is_string( $value['name'] ) ) {
			$value = [ $value ];
		}
		$blocks = [];
		$values = array_values( $value );
		for ( $i = 0; $i < count( $values ); ++$i ) {
			$normalized = self::normalize_block( $values[ $i ], sprintf( 'block_spec[%s]', (string) $i ) );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$blocks[] = $normalized;
		}
		if ( $blocks === [] ) {
			return new WP_Error( 'gutenberg_empty_block_spec', 'block_spec must contain at least one block.' );
		}
		return $blocks;
	}

	/**
	 * Normalize a single block value, recursively normalizing innerBlocks.
	 *
	 * @since 1.0.0
	 * @param mixed  $value Raw block value.
	 * @param string $path  Dot-notation path for error messages (e.g. "block_spec[0]").
	 * @return array{name: string, attributes: array<string, mixed>, innerBlocks: list<array<string, mixed>>}|WP_Error
	 */
	public static function normalize_block( mixed $value, string $path ): array|WP_Error {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', sprintf( '%s must be an object.', $path ) );
		}
		if ( ! array_key_exists( 'name', $value ) || ! is_string( $value['name'] ) || trim( $value['name'] ) === '' ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', sprintf( '%s.name must be a non-empty block name.', $path ) );
		}
		$name = trim( $value['name'] );

		if ( array_key_exists( 'attributes', $value ) && ! is_array( $value['attributes'] ) ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', sprintf( '%s.attributes must be an object when present.', $path ) );
		}
		/** @var array<string, mixed> $attributes */
		$attributes = is_array( $value['attributes'] ?? null ) ? $value['attributes'] : [];

		if ( array_key_exists( 'innerBlocks', $value ) && ! is_array( $value['innerBlocks'] ) ) {
			return new WP_Error( 'gutenberg_invalid_block_spec', sprintf( '%s.innerBlocks must be an array when present.', $path ) );
		}
		$inner_blocks = is_array( $value['innerBlocks'] ?? null ) ? array_values( $value['innerBlocks'] ) : [];

		$normalized_inner = [];
		for ( $i = 0; $i < count( $inner_blocks ); ++$i ) {
			$normalized = self::normalize_block( $inner_blocks[ $i ], sprintf( '%s.innerBlocks[%s]', $path, (string) $i ) );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$normalized_inner[] = $normalized;
		}

		return [
			'name'        => $name,
			'attributes'  => $attributes,
			'innerBlocks' => $normalized_inner,
		];
	}

	/**
	 * Extract the innerBlocks list from a block array, returning an empty list on missing/invalid data.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $block Normalized block array.
	 * @return list<array<string, mixed>>
	 */
	public static function block_inner_specs( array $block ): array {
		$raw = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];
		/** @var list<array<string, mixed>> $filtered */
		$filtered = array_values( array_filter( $raw, static fn( mixed $b ): bool => is_array( $b ) ) );
		return $filtered;
	}

	/**
	 * Return the top-level block names from a blocks list.
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>> $blocks Normalized block list.
	 * @return list<string>
	 */
	public static function top_level_block_names( array $blocks ): array {
		$names = [];
		foreach ( $blocks as $block ) {
			$name = is_string( $block['name'] ?? null ) ? $block['name'] : '';
			if ( $name !== '' ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Return true when every leaf block in the tree is a raw-HTML block.
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>> $blocks Normalized block list.
	 * @return bool
	 */
	public static function blocks_are_raw_html_only( array $blocks ): bool {
		$leaves = self::leaf_block_names( $blocks );
		if ( $leaves === [] ) {
			return false;
		}
		foreach ( $leaves as $name ) {
			if ( $name !== 'core/html' && $name !== 'core/freeform' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Recursively collect the names of all leaf blocks (blocks without inner blocks).
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>> $blocks Normalized block list.
	 * @return list<string>
	 */
	public static function leaf_block_names( array $blocks ): array {
		$names = [];
		foreach ( $blocks as $block ) {
			$inner = self::block_inner_specs( $block );
			if ( $inner !== [] ) {
				foreach ( self::leaf_block_names( $inner ) as $name ) {
					$names[] = $name;
				}
				continue;
			}
			$name = is_string( $block['name'] ?? null ) ? $block['name'] : '';
			if ( $name !== '' ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Validate that every block in the tree is a WPCodex-owned dynamic block.
	 *
	 * Returns a WP_Error if any non-dynamic block is found, directing the agent
	 * to use the queue flow instead.
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>> $blocks Normalized block list.
	 * @return WP_Error|null WP_Error on the first non-dynamic block; null on success.
	 */
	public static function validate_dynamic_only_blocks( array $blocks ): ?WP_Error {
		foreach ( $blocks as $block ) {
			$name = is_string( $block['name'] ?? null ) ? $block['name'] : '';
			if ( ! self::is_wpcodex_dynamic_block( $name ) ) {
				return new WP_Error(
					'gutenberg_static_blocks_require_finalization',
					sprintf(
						'Block "%s" is not a registered WPCodex-owned dynamic-only block. Native/static Gutenberg blocks must be queued with wpcodex/gutenberg-add-pending-change and finalized in a browser before they are live.',
						$name !== '' ? $name : '(missing name)'
					),
					[
						'status'                => 400,
						'finalization_required' => true,
						'queue_ability'         => 'wpcodex/gutenberg-add-pending-change',
						'enable_ability'        => 'wpcodex/gutenberg-enable-batch-finalization',
					]
				);
			}
			$inner_error = self::validate_dynamic_only_blocks( self::block_inner_specs( $block ) );
			if ( $inner_error !== null ) {
				return $inner_error;
			}
		}
		return null;
	}

	/**
	 * Return true when the given block name is a registered WPCodex dynamic block.
	 *
	 * @since 1.0.0
	 * @param string $name Block name (e.g. "wpcodex/my-block").
	 * @return bool
	 */
	public static function is_wpcodex_dynamic_block( string $name ): bool {
		if ( ! str_starts_with( $name, 'wpcodex/' ) ) {
			return false;
		}
		if ( ! class_exists( WP_Block_Type_Registry::class ) ) {
			return false;
		}
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		return $block_type !== null && $block_type->is_dynamic();
	}

	/**
	 * Build a one-line change summary string for item excerpts.
	 *
	 * @since 1.0.0
	 * @param WP_Post                    $target    Target post.
	 * @param string                     $operation Change operation name.
	 * @param list<array<string, mixed>> $blocks    Normalized block list.
	 * @return string
	 */
	public static function item_change_summary( WP_Post $target, string $operation, array $blocks ): string {
		$names        = self::top_level_block_names( $blocks );
		$name_summary = $names === []
			? __( 'no blocks', 'wpcodex' )
			: implode( ', ', array_slice( $names, 0, 5 ) );
		return sprintf(
			'%s for %s #%d: %d top-level block(s): %s',
			$operation,
			$target->post_type,
			$target->ID,
			count( $blocks ),
			$name_summary
		);
	}

	/**
	 * Return the finalization URL for a batch (currently the dashboard URL).
	 *
	 * @since 1.0.0
	 * @param int $batch_id Batch post ID.
	 * @return string Admin URL for the Block Editor Queue page.
	 */
	public static function finalization_url( int $batch_id ): string {
		unset( $batch_id );
		return self::finalizer_dashboard_url();
	}

	/**
	 * Return the admin URL for the Block Editor Queue dashboard page.
	 *
	 * @since 1.0.0
	 * @return string Admin URL.
	 */
	public static function finalizer_dashboard_url(): string {
		return add_query_arg( [ 'page' => 'wpcodex-block-editor' ], admin_url( 'admin.php' ) );
	}

	/**
	 * Return a human-readable label for a batch post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return string Label string.
	 */
	public static function batch_label( WP_Post $batch ): string {
		$label = trim( $batch->post_title );
		/* translators: %d: batch post ID */
		return $label !== '' ? $label : sprintf( __( 'Gutenberg batch #%d', 'wpcodex' ), $batch->ID );
	}

	/**
	 * Build the user-facing instruction string for the current finalizer runtime state.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return string Instruction string for the agent to pass to the user.
	 */
	public static function user_instruction( WP_Post $batch ): string {
		$runtime = self::finalizer_runtime_status( $batch );
		if ( ( $runtime['online'] ?? false ) === true && ( $runtime['can_finalize_batch'] ?? false ) === true ) {
			return sprintf(
				'The WPCodex Block Editor Queue page is open and should automatically finalize Gutenberg batch #%d: %s. Do not ask the user to do anything unless the page goes offline; stream %s with curl -N, or poll %s with curl, until the batch becomes finalized, failed, or conflicted. Do not treat these Gutenberg changes as live until finalization completes.',
				$batch->ID,
				self::batch_label( $batch ),
				(string) ( $runtime['sse_url'] ?? self::finalizer_runtime_sse_url( $batch->ID ) ),
				(string) ( $runtime['poll_url'] ?? self::finalizer_runtime_poll_url( $batch->ID ) )
			);
		}
		if ( ( $runtime['online'] ?? false ) === true ) {
			return sprintf(
				'A WPCodex Block Editor Queue page is open, but that browser user may not be able to finalize Gutenberg batch #%d: %s. Ask the user to open %s as a user who can edit every target. Do not treat these Gutenberg changes as live until finalization completes.',
				$batch->ID,
				self::batch_label( $batch ),
				self::finalizer_dashboard_url()
			);
		}
		return sprintf(
			'The WPCodex Block Editor Queue page is not currently online. Ask the user to open %s and keep it open. It will automatically finalize Gutenberg batch #%d: %s when it can. Do not treat these Gutenberg changes as live until finalization completes.',
			self::finalizer_dashboard_url(),
			$batch->ID,
			self::batch_label( $batch )
		);
	}

	/**
	 * Build a copy-back prompt summarising the terminal state of a batch.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return string Short prompt string for the agent.
	 */
	public static function copy_back_prompt( WP_Post $batch ): string {
		$status = self::gb_status( $batch->ID );
		$label  = self::batch_label( $batch );
		return match ( $status ) {
			self::STATUS_FINALIZED  => sprintf( 'Gutenberg batch #%d finalized: %s. Verify it and continue.', $batch->ID, $label ),
			self::STATUS_FAILED     => sprintf( 'Gutenberg batch #%d failed: %s. Review the reported item errors and continue.', $batch->ID, $label ),
			self::STATUS_CONFLICTED => sprintf( 'Gutenberg batch #%d conflicted: %s. Re-read the changed target and queue a fresh batch.', $batch->ID, $label ),
			self::STATUS_CANCELED   => sprintf( 'Gutenberg batch #%d canceled: %s.', $batch->ID, $label ),
			self::STATUS_STALE      => sprintf( 'Gutenberg batch #%d is stale: %s. Re-read the targets and queue a fresh batch.', $batch->ID, $label ),
			default                 => sprintf( 'Gutenberg batch #%d is %s: %s.', $batch->ID, $status, $label ),
		};
	}

	/**
	 * Count item statuses for a list of item posts.
	 *
	 * @since 1.0.0
	 * @param list<WP_Post> $items Item posts.
	 * @return array<string, int> Map of status → count.
	 */
	public static function count_item_statuses( array $items ): array {
		$counts = [];
		foreach ( $items as $item ) {
			$s            = self::gb_status( $item->ID );
			$counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
		}
		return $counts;
	}

	/**
	 * Build the shared base shape array for a batch (without inline items).
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return array<string, mixed>
	 */
	public static function shape_batch_base( WP_Post $batch ): array {
		$items            = self::get_items( $batch->ID );
		$counts           = self::count_item_statuses( $items );
		$agent_label      = self::meta_string( $batch->ID, self::META_AGENT_LABEL );
		$agent_session_id = self::meta_string( $batch->ID, self::META_AGENT_SESSION_ID );

		return [
			'batch_id'              => $batch->ID,
			'label'                 => self::batch_label( $batch ),
			'agent_label'           => $agent_label !== '' ? $agent_label : 'the originating agent',
			'agent_session_id'      => $agent_session_id,
			'agent_note'            => $batch->post_excerpt,
			'status'                => self::gb_status( $batch->ID ),
			'created_at'            => $batch->post_date_gmt,
			'ready_at'              => self::meta_string( $batch->ID, self::META_READY_AT ),
			'finalized_at'          => self::meta_string( $batch->ID, self::META_FINALIZED_AT ),
			'item_count'            => count( $items ),
			'item_counts'           => $counts,
			'last_error'            => self::meta_string( $batch->ID, self::META_LAST_ERROR ),
			'finalization_required' => ! in_array( self::gb_status( $batch->ID ), self::TERMINAL_STATUSES, true ),
			'finalization_url'      => self::finalization_url( $batch->ID ),
			'finalizer_runtime'     => self::finalizer_runtime_status( $batch ),
			'user_instruction'      => self::user_instruction( $batch ),
			'copy_back_prompt'      => self::copy_back_prompt( $batch ),
		];
	}

	/**
	 * Build the full batch shape array including inline item shapes.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return array<string, mixed>
	 */
	public static function shape_batch( WP_Post $batch ): array {
		$data          = self::shape_batch_base( $batch );
		$data['items'] = array_map(
			static fn( WP_Post $item ): array => self::shape_item( $item ),
			self::get_items( $batch->ID )
		);
		return $data;
	}

	/**
	 * Build a compact batch summary shape (no inline items list).
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return array<string, mixed>
	 */
	public static function shape_batch_summary( WP_Post $batch ): array {
		return self::shape_batch_base( $batch );
	}

	/**
	 * Build the item shape array for a single item post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item Item post.
	 * @return array<string, mixed>
	 */
	public static function shape_item( WP_Post $item ): array {
		$target_id   = self::meta_int( $item->ID, self::META_TARGET_ID );
		$target      = self::get_target( $target_id );
		$blocks      = self::item_blocks( $item );
		$block_names = is_wp_error( $blocks ) ? [] : self::top_level_block_names( $blocks );

		return [
			'item_id'               => $item->ID,
			'batch_id'              => $item->post_parent,
			'target_id'             => $target_id,
			'target_type'           => self::meta_string( $item->ID, self::META_TARGET_TYPE ),
			'target_title'          => $target instanceof WP_Post
				? self::target_title( $target )
				/* translators: %d: target post ID */
				: sprintf( __( 'Missing target #%d', 'wpcodex' ), $target_id ),
			'operation'             => self::meta_string( $item->ID, self::META_OPERATION ),
			'status'                => self::gb_status( $item->ID ),
			'created_at'            => $item->post_date_gmt,
			'finalized_at'          => self::meta_string( $item->ID, self::META_FINALIZED_AT ),
			'top_level_block_count' => is_wp_error( $blocks ) ? 0 : count( $blocks ),
			'top_level_block_names' => array_slice( $block_names, 0, 10 ),
			'change_summary'        => $item->post_excerpt,
			'validation_errors'     => self::validation_errors( $item->ID ),
			'finalization_mode'     => self::meta_string( $item->ID, self::META_FINALIZATION_MODE ),
		];
	}

	/**
	 * Decode and normalize the stored block_spec for an item.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item Item post.
	 * @return list<array<string, mixed>>|WP_Error Normalized blocks, or WP_Error if the stored spec is invalid.
	 */
	public static function item_blocks( WP_Post $item ): array|WP_Error {
		$encoded = self::meta_string( $item->ID, self::META_BLOCK_SPEC );
		if ( $encoded === '' ) {
			$encoded = $item->post_content;
		}
		/** @var mixed $decoded */
		$decoded = json_decode( $encoded, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'gutenberg_invalid_stored_block_spec', sprintf( 'Item %d has an invalid stored block_spec.', $item->ID ) );
		}
		return self::normalize_blocks( $decoded );
	}

	/**
	 * Build a pending-summary payload for a target post.
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target post ID.
	 * @param string $target_type Target post type.
	 * @return array<string, mixed> Empty array when no active item exists.
	 */
	public static function pending_summary_for_target( int $target_id, string $target_type ): array {
		$item = self::active_item_for_target( $target_id, $target_type );
		if ( ! $item instanceof WP_Post ) {
			return [];
		}
		$batch = self::find_batch( $item->post_parent );
		return [
			'batch_id'        => $item->post_parent,
			'batch_label'     => $batch instanceof WP_Post ? self::batch_label( $batch ) : '',
			'batch_status'    => $batch instanceof WP_Post ? self::gb_status( $batch->ID ) : '',
			'item_id'         => $item->ID,
			'item_status'     => self::gb_status( $item->ID ),
			'agent_label'     => $batch instanceof WP_Post ? self::meta_string( $batch->ID, self::META_AGENT_LABEL ) : '',
			'finalization_url' => $batch instanceof WP_Post ? self::finalization_url( $batch->ID ) : '',
			'warning'         => 'This target has a non-terminal queued Gutenberg change. Live saved content does not include the queued block_spec until finalization succeeds.',
		];
	}

	/**
	 * Find the first active (non-terminal) item for a target post, or null.
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target post ID.
	 * @param string $target_type Target post type.
	 * @return WP_Post|null Active item post, or null.
	 */
	public static function active_item_for_target( int $target_id, string $target_type ): ?WP_Post {
		/** @var list<WP_Post> $items */
		$items = get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query is the correct WP_Query mechanism here; no viable alternative
			'meta_query'     => [
				[ 'key' => self::META_KIND, 'value' => self::KIND_ITEM ],
				[ 'key' => self::META_TARGET_ID, 'value' => $target_id, 'compare' => '=', 'type' => 'NUMERIC' ],
				[ 'key' => self::META_TARGET_TYPE, 'value' => $target_type ],
				[ 'key' => self::META_STATUS, 'value' => self::NON_TERMINAL_STATUSES, 'compare' => 'IN' ],
			],
		] );
		return $items[0] ?? null;
	}

	/**
	 * Build the conflict payload for an existing active item.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item Active item post.
	 * @return array<string, mixed>
	 */
	public static function conflict_payload( WP_Post $item ): array {
		$batch       = self::find_batch( $item->post_parent );
		$target_id   = self::meta_int( $item->ID, self::META_TARGET_ID );
		$target_type = self::meta_string( $item->ID, self::META_TARGET_TYPE );
		$agent_label = $batch instanceof WP_Post ? self::meta_string( $batch->ID, self::META_AGENT_LABEL ) : '';

		return [
			'batch_id'       => $item->post_parent,
			'batch_label'    => $batch instanceof WP_Post ? self::batch_label( $batch ) : '',
			'batch_status'   => $batch instanceof WP_Post ? self::gb_status( $batch->ID ) : '',
			'agent_label'    => $agent_label !== '' ? $agent_label : 'the originating agent',
			'target_id'      => $target_id,
			'target_type'    => $target_type,
			'cancel_ability' => 'wpcodex/gutenberg-delete-pending-batch',
			'cancel_params'  => [ 'batch_id' => $item->post_parent ],
		];
	}

	/**
	 * Determine whether the current user may finalize all items in a batch.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return bool True if the current user has sufficient capability.
	 */
	public static function current_user_can_finalize_batch( WP_Post $batch ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		foreach ( self::get_items( $batch->ID ) as $item ) {
			$target_id = self::meta_int( $item->ID, self::META_TARGET_ID );
			if ( $target_id <= 0 || ! current_user_can( 'edit_post', $target_id ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Mark draft batches older than DRAFT_STALE_SECONDS as stale.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function mark_stale_drafts(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::DRAFT_STALE_SECONDS );
		foreach ( self::get_batches( [ self::STATUS_DRAFT ], -1 ) as $batch ) {
			if ( strcmp( $batch->post_date_gmt, $cutoff ) > 0 ) {
				continue;
			}
			self::set_status( $batch->ID, self::STATUS_STALE );
			foreach ( self::get_items( $batch->ID, [ self::STATUS_DRAFT ] ) as $item ) {
				self::set_status( $item->ID, self::STATUS_STALE );
			}
		}
	}

	/**
	 * Mark old failed batches as stale after RETENTION_SECONDS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function mark_old_failed_batches_stale(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_SECONDS );
		foreach ( self::get_batches( [ self::STATUS_FAILED ], -1 ) as $batch ) {
			$updated_at = self::meta_string( $batch->ID, self::META_STATUS_UPDATED_AT );
			if ( $updated_at !== '' && strcmp( $updated_at, $cutoff ) > 0 ) {
				continue;
			}
			self::set_status( $batch->ID, self::STATUS_STALE );
			foreach ( self::get_items( $batch->ID, self::NON_TERMINAL_STATUSES ) as $item ) {
				self::set_status( $item->ID, self::STATUS_STALE );
			}
		}
	}

	/**
	 * Release expired leases on a batch and its items, transitioning them to failed.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return void
	 */
	public static function release_expired_leases_for_batch( WP_Post $batch ): void {
		if (
			self::gb_status( $batch->ID ) === self::STATUS_RUNNING
			&& ! self::lease_is_valid( $batch->ID, self::meta_string( $batch->ID, self::META_LEASE_OWNER ) )
		) {
			self::set_status( $batch->ID, self::STATUS_FAILED );
			update_post_meta(
				$batch->ID,
				self::META_LAST_ERROR,
				__( 'A previous Block Editor Queue tab stopped before renewing its lease. Retry finalization for this batch.', 'wpcodex' )
			);
			self::clear_lease( $batch->ID );
		}

		foreach ( self::get_items( $batch->ID, [ self::STATUS_RUNNING ] ) as $item ) {
			if ( self::lease_is_valid( $item->ID, self::meta_string( $item->ID, self::META_LEASE_OWNER ) ) ) {
				continue;
			}
			self::set_status( $item->ID, self::STATUS_FAILED );
			update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, [
				[
					'message'          => 'A previous Block Editor Queue tab stopped before completing this item.',
					'category'         => 'abandoned-finalizer',
					'code'             => 'lease_expired',
					'suppressed_count' => 0,
				],
			] );
			self::clear_lease( $item->ID );
		}
	}

	/**
	 * Release expired leases for a batch and return a fresh copy of the post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return WP_Post Refreshed batch post (or the original if re-fetch fails).
	 */
	public static function refresh_batch_runtime_state( WP_Post $batch ): WP_Post {
		self::release_expired_leases_for_batch( $batch );
		return self::find_batch( $batch->ID ) ?? $batch;
	}

	/**
	 * Claim a batch for finalization by the Block Editor Queue JS runtime.
	 *
	 * @since 1.0.0
	 * @param int $batch_id Batch post ID.
	 * @return array<string, mixed>|WP_Error Claim payload with lease_owner and batch shape, or WP_Error.
	 */
	public static function claim_batch( int $batch_id ): array|WP_Error {
		$batch = self::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}
		self::release_expired_leases_for_batch( $batch );
		$batch = self::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}

		$current_status = self::gb_status( $batch->ID );
		if ( $current_status === self::STATUS_DRAFT ) {
			return new WP_Error(
				'gutenberg_batch_not_ready',
				'Draft Gutenberg batches cannot be finalized until wpcodex/gutenberg-enable-batch-finalization is called.',
				[ 'status' => 409 ]
			);
		}
		if ( ! in_array( $current_status, [ self::STATUS_READY, self::STATUS_FAILED ], true ) ) {
			return new WP_Error(
				'gutenberg_batch_not_claimable',
				sprintf( 'Gutenberg batch %d is %s and cannot be claimed for finalization.', $batch->ID, $current_status ),
				[ 'status' => 409, 'batch' => self::shape_batch( $batch ) ]
			);
		}

		$lease_owner = (string) wp_generate_uuid4();
		if ( ! self::atomic_status_transition( $batch->ID, [ self::STATUS_READY, self::STATUS_FAILED ], self::STATUS_RUNNING ) ) {
			$fresh = self::find_batch( $batch->ID );
			return new WP_Error( 'gutenberg_batch_claim_raced', 'Another Block Editor Queue tab claimed this batch first.', [
				'status' => 409,
				'batch'  => $fresh instanceof WP_Post ? self::shape_batch( $fresh ) : null,
			] );
		}

		self::set_lease( $batch->ID, $lease_owner );
		update_post_meta( $batch->ID, self::META_LAST_ERROR, '' );

		foreach ( self::get_items( $batch->ID, [ self::STATUS_FAILED, self::STATUS_CONFLICTED ] ) as $item ) {
			self::set_status( $item->ID, self::STATUS_READY );
			update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, [] );
			self::clear_lease( $item->ID );
		}

		$fresh = self::find_batch( $batch->ID );
		return [
			'lease_owner' => $lease_owner,
			'batch'       => $fresh instanceof WP_Post ? self::shape_batch( $fresh ) : self::shape_batch( $batch ),
		];
	}

	/**
	 * Claim the next ready item in a running batch.
	 *
	 * @since 1.0.0
	 * @param int    $batch_id    Batch post ID.
	 * @param string $lease_owner Batch lease owner UUID.
	 * @return array<string, mixed>|WP_Error Claim payload or WP_Error.
	 */
	public static function claim_next_item( int $batch_id, string $lease_owner ): array|WP_Error {
		$batch = self::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}
		if ( self::gb_status( $batch->ID ) !== self::STATUS_RUNNING || ! self::lease_is_valid( $batch->ID, $lease_owner ) ) {
			return new WP_Error( 'gutenberg_batch_lease_invalid', 'The batch finalization lease is no longer active.', [ 'status' => 409 ] );
		}

		self::set_lease( $batch->ID, $lease_owner );
		$item = null;
		for ( $attempt = 0; $attempt < self::ITEM_CLAIM_ATTEMPTS; ++$attempt ) {
			$ready_items = self::get_items( $batch->ID, [ self::STATUS_READY ] );
			if ( $ready_items === [] ) {
				return self::finish_batch_if_complete( $batch );
			}
			$candidate = $ready_items[0];
			if ( self::atomic_status_transition( $candidate->ID, [ self::STATUS_READY ], self::STATUS_RUNNING ) ) {
				$item = $candidate;
				break;
			}
		}

		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_claim_raced', 'Another Block Editor Queue request claimed the next item first.', [
				'status' => 409,
				'batch'  => self::shape_batch( $batch ),
			] );
		}

		self::set_lease( $item->ID, $lease_owner );
		$fresh_item   = self::find_item( $item->ID );
		$claimed_item = $fresh_item instanceof WP_Post ? $fresh_item : $item;
		$blocks       = self::item_blocks( $claimed_item );
		if ( is_wp_error( $blocks ) ) {
			$failed = self::fail_item(
				$claimed_item->ID,
				$lease_owner,
				[ [ 'message' => $blocks->get_error_message(), 'category' => 'stored-spec', 'code' => $blocks->get_error_code() ] ],
				'The stored Gutenberg block_spec is invalid; canonical content was not written.'
			);
			if ( is_wp_error( $failed ) ) {
				return $failed;
			}
			/** @var array<string, mixed> $failed */
			return $failed;
		}

		return [
			'done'  => false,
			'item'  => self::shape_item( $claimed_item ),
			'batch' => self::shape_batch( $batch ),
		];
	}

	/**
	 * Commit a list of prepared items to live post_content and finalize the batch.
	 *
	 * @since 1.0.0
	 * @param WP_Post        $batch          Batch post.
	 * @param list<WP_Post>  $prepared_items Items in STATUS_PREPARED.
	 * @return array<string, mixed>|WP_Error Done payload or WP_Error.
	 */
	public static function commit_prepared_items( WP_Post $batch, array $prepared_items ): array|WP_Error {
		if ( $prepared_items === [] ) {
			return self::finalize_batch( $batch );
		}

		foreach ( $prepared_items as $item ) {
			$target_id = self::meta_int( $item->ID, self::META_TARGET_ID );
			$target    = self::get_target( $target_id );
			if ( ! $target instanceof WP_Post ) {
				return self::fail_prepared_item( $item, [ [ 'message' => 'The target post no longer exists.' ] ], 'Target post missing; live content was left unchanged.' );
			}
			$base_hash = self::meta_string( $item->ID, self::META_BASE_CONTENT_HASH );
			if ( $base_hash !== '' && ! hash_equals( $base_hash, self::content_hash( $target->post_content ) ) ) {
				return self::conflict_prepared_item( $item );
			}
		}

		$written_items = [];
		foreach ( $prepared_items as $item ) {
			$target_id = self::meta_int( $item->ID, self::META_TARGET_ID );
			$target    = self::get_target( $target_id );
			if ( ! $target instanceof WP_Post ) {
				self::restore_written_prepared_items( $written_items );
				return self::fail_prepared_item( $item, [ [ 'message' => 'The target post no longer exists.' ] ], 'Target post missing; live content was left unchanged.' );
			}
			$base_hash = self::meta_string( $item->ID, self::META_BASE_CONTENT_HASH );
			if ( $base_hash !== '' && ! hash_equals( $base_hash, self::content_hash( $target->post_content ) ) ) {
				self::restore_written_prepared_items( $written_items );
				return self::conflict_prepared_item( $item );
			}
			$updated = wp_update_post( [ 'ID' => $target->ID, 'post_content' => self::meta_string( $item->ID, self::META_FINALIZED_CONTENT ) ], true );
			if ( is_wp_error( $updated ) ) {
				$restored = self::restore_written_prepared_items( $written_items );
				return self::fail_prepared_item(
					$item,
					[ [ 'message' => $updated->get_error_message() ] ],
					$restored
						? 'WordPress failed to write post_content; live content was left unchanged.'
						: 'WordPress failed to write post_content and rollback failed; inspect the affected targets before retrying.'
				);
			}
			$written_items[] = $item;
		}

		foreach ( $prepared_items as $item ) {
			self::set_status( $item->ID, self::STATUS_FINALIZED );
			self::clear_lease( $item->ID );
			delete_post_meta( $item->ID, self::META_BASE_CONTENT );
			delete_post_meta( $item->ID, self::META_FINALIZED_CONTENT );
		}

		self::set_status( $batch->ID, self::STATUS_FINALIZED );
		self::clear_lease( $batch->ID );
		$fresh = self::find_batch( $batch->ID ) ?? $batch;

		return [ 'done' => true, 'batch' => self::shape_batch( $fresh ) ];
	}

	/**
	 * Restore previously written prepared items by reverting to base content.
	 *
	 * @since 1.0.0
	 * @param list<WP_Post> $written_items Items already written to the DB.
	 * @return bool True if all items were restored; false if any restoration failed.
	 */
	private static function restore_written_prepared_items( array $written_items ): bool {
		$restored = true;
		foreach ( array_reverse( $written_items ) as $item ) {
			$target = self::get_target( self::meta_int( $item->ID, self::META_TARGET_ID ) );
			if ( ! $target instanceof WP_Post ) {
				$restored = false;
				continue;
			}
			$updated = wp_update_post( [ 'ID' => $target->ID, 'post_content' => self::meta_string( $item->ID, self::META_BASE_CONTENT ) ], true );
			if ( is_wp_error( $updated ) ) {
				$restored = false;
			}
		}
		return $restored;
	}

	/**
	 * Finalize a batch that has no items to commit (all items were dynamic-only).
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return array<string, mixed> Done payload.
	 */
	public static function finalize_batch( WP_Post $batch ): array {
		self::set_status( $batch->ID, self::STATUS_FINALIZED );
		self::clear_lease( $batch->ID );
		$fresh = self::find_batch( $batch->ID ) ?? $batch;
		return [ 'done' => true, 'batch' => self::shape_batch( $fresh ) ];
	}

	/**
	 * Check if a batch is complete and commit/fail as appropriate.
	 *
	 * @since 1.0.0
	 * @param WP_Post $batch Batch post.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function finish_batch_if_complete( WP_Post $batch ): array|WP_Error {
		$ready = self::get_items( $batch->ID, [ self::STATUS_READY ] );
		if ( $ready !== [] ) {
			return [ 'done' => false, 'batch' => self::shape_batch( $batch ) ];
		}
		$failed = self::get_items( $batch->ID, [ self::STATUS_FAILED, self::STATUS_CONFLICTED ] );
		if ( $failed !== [] ) {
			self::set_status( $batch->ID, self::STATUS_FAILED );
			self::clear_lease( $batch->ID );
			$fresh = self::find_batch( $batch->ID ) ?? $batch;
			return [ 'done' => true, 'batch' => self::shape_batch( $fresh ) ];
		}
		$running = self::get_items( $batch->ID, [ self::STATUS_RUNNING ] );
		if ( $running !== [] ) {
			return [ 'done' => false, 'batch' => self::shape_batch( $batch ) ];
		}
		return self::commit_prepared_items( $batch, self::get_items( $batch->ID, [ self::STATUS_PREPARED ] ) );
	}

	/**
	 * Determine whether a JS validation payload contains any failures.
	 *
	 * @since 1.0.0
	 * @param mixed $validations Raw validation payload from the JS runtime.
	 * @return bool True if any entry is invalid or the payload is not an array.
	 */
	public static function validation_payload_has_failures( mixed $validations ): bool {
		if ( ! is_array( $validations ) ) {
			return true;
		}
		return array_filter(
			$validations,
			static fn( mixed $v ): bool => ! is_array( $v ) || ( $v['isValid'] ?? false ) !== true
		) !== [];
	}

	/**
	 * Coerce a raw errors value into a list of error-row arrays.
	 *
	 * @since 1.0.0
	 * @param mixed $raw_errors Raw errors from the JS runtime or internal code.
	 * @return list<array<string, mixed>>
	 */
	public static function raw_validation_error_rows( mixed $raw_errors ): array {
		if ( ! is_array( $raw_errors ) ) {
			return [ [ 'message' => is_scalar( $raw_errors ) ? (string) $raw_errors : 'Unknown validation error.' ] ];
		}
		return array_map(
			static function ( mixed $error ): array {
				if ( is_array( $error ) ) {
					/** @var array<string, mixed> $error */
					return $error;
				}
				return [ 'message' => is_scalar( $error ) ? (string) $error : 'Unknown validation error.' ];
			},
			array_values( $raw_errors )
		);
	}

	/**
	 * Truncate and sanitize a single validation message string.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw message value.
	 * @return string Sanitized message, max 300 characters.
	 */
	private static function compact_validation_message( mixed $value ): string {
		$msg = is_scalar( $value ) ? (string) $value : 'Unknown validation error.';
		$msg = preg_replace( '/\s+/', ' ', $msg ) ?? $msg;
		return mb_substr( trim( $msg ), 0, 300 );
	}

	/**
	 * Build a compact single validation error row for storage.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $row              Raw error row.
	 * @param int                  $target_id        Target post ID.
	 * @param string               $target_title     Target post title.
	 * @param int                  $suppressed_count Number of additional errors suppressed.
	 * @return array<string, mixed>
	 */
	private static function compact_validation_error_row( array $row, int $target_id, string $target_title, int $suppressed_count ): array {
		return [
			'target_id'        => $target_id,
			'target_title'     => $target_title,
			'block_name'       => is_scalar( $row['block_name'] ?? null ) ? (string) $row['block_name'] : '',
			'path'             => is_scalar( $row['path'] ?? null ) ? (string) $row['path'] : '',
			'category'         => is_scalar( $row['category'] ?? null ) ? (string) $row['category'] : 'validation',
			'code'             => is_scalar( $row['code'] ?? null ) ? (string) $row['code'] : 'block_validation_failed',
			'message'          => self::compact_validation_message( $row['message'] ?? null ),
			'suppressed_count' => $suppressed_count,
		];
	}

	/**
	 * Compact a full validation error payload into at most 5 rows for storage.
	 *
	 * @since 1.0.0
	 * @param mixed        $raw_errors Raw errors value.
	 * @param WP_Post|null $item       Item post (used to look up target title).
	 * @return list<array<string, mixed>>
	 */
	public static function compact_validation_errors( mixed $raw_errors, ?WP_Post $item = null ): array {
		$target_id    = $item instanceof WP_Post ? self::meta_int( $item->ID, self::META_TARGET_ID ) : 0;
		$target       = $target_id > 0 ? self::get_target( $target_id ) : null;
		$target_title = $target instanceof WP_Post ? self::target_title( $target ) : '';
		$errors       = self::raw_validation_error_rows( $raw_errors );
		$suppressed   = max( 0, count( $errors ) - 5 );
		$compact      = [];
		foreach ( array_slice( $errors, 0, 5 ) as $row ) {
			$compact[] = self::compact_validation_error_row( $row, $target_id, $target_title, $suppressed );
		}
		return $compact;
	}

	/**
	 * Return the stored validation errors for an item post.
	 *
	 * @since 1.0.0
	 * @param int $item_id Item post ID.
	 * @return list<array<string, mixed>>
	 */
	public static function validation_errors( int $item_id ): array {
		/** @var mixed $value */
		$value = get_post_meta( $item_id, self::META_VALIDATION_ERRORS, true );
		if ( ! is_array( $value ) ) {
			return [];
		}
		/** @var list<array<string, mixed>> $errors */
		$errors = array_values( array_filter( $value, static fn( mixed $e ): bool => is_array( $e ) ) );
		return $errors;
	}

	/**
	 * Transition a prepared item to conflicted and mark the batch as failed.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item Prepared item post.
	 * @return WP_Error Always returns a WP_Error with status 409.
	 */
	public static function conflict_prepared_item( WP_Post $item ): WP_Error {
		self::set_status( $item->ID, self::STATUS_CONFLICTED );
		self::clear_lease( $item->ID );
		update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, self::compact_validation_errors( [ [
			'message'  => 'The target content changed after this Gutenberg item was queued. Re-read the target and queue a fresh change.',
			'category' => 'content-conflict',
			'code'     => 'base_content_changed',
		] ], $item ) );
		self::mark_batch_failed( $item->post_parent, 'At least one target changed after it was queued; live content was left unchanged.' );
		return new WP_Error(
			'gutenberg_target_changed',
			'The target content changed after this Gutenberg item was queued. Live content was left unchanged.',
			[ 'status' => 409, 'item' => self::shape_item( $item ) ]
		);
	}

	/**
	 * Fail a prepared item with a given error list and message.
	 *
	 * @since 1.0.0
	 * @param WP_Post $item    Prepared item post.
	 * @param mixed   $errors  Validation errors to store.
	 * @param string  $message Human-readable failure message.
	 * @return WP_Error Always returns a WP_Error with status 500.
	 */
	public static function fail_prepared_item( WP_Post $item, mixed $errors, string $message ): WP_Error {
		self::set_status( $item->ID, self::STATUS_FAILED );
		self::clear_lease( $item->ID );
		update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, self::compact_validation_errors( $errors, $item ) );
		self::mark_batch_failed( $item->post_parent, $message );
		return new WP_Error( 'gutenberg_prepared_item_failed', $message, [ 'status' => 500, 'item' => self::shape_item( $item ) ] );
	}

	/**
	 * Complete a running item, staging its content and advancing the batch.
	 *
	 * @since 1.0.0
	 * @param int    $item_id     Item post ID.
	 * @param string $lease_owner Lease owner UUID.
	 * @param string $content     Serialized Gutenberg block HTML from the JS runtime.
	 * @param mixed  $validations JS validation payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function complete_item( int $item_id, string $lease_owner, string $content, mixed $validations ): array|WP_Error {
		$item = self::find_item( $item_id );
		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_not_found', sprintf( 'Gutenberg item %d was not found.', $item_id ), [ 'status' => 404 ] );
		}
		if ( self::gb_status( $item->ID ) !== self::STATUS_RUNNING || ! self::lease_is_valid( $item->ID, $lease_owner ) ) {
			return new WP_Error( 'gutenberg_item_lease_invalid', 'The item finalization lease is no longer active.', [ 'status' => 409 ] );
		}
		if ( self::validation_payload_has_failures( $validations ) ) {
			return self::fail_item( $item->ID, $lease_owner, $validations, 'JS validation failed; canonical content was not written.' );
		}

		$target_id = self::meta_int( $item->ID, self::META_TARGET_ID );
		$target    = self::get_target( $target_id );
		if ( ! $target instanceof WP_Post ) {
			return self::fail_item( $item->ID, $lease_owner, [ [ 'message' => 'The target post no longer exists.' ] ], 'Target post missing.' );
		}

		$base_hash = self::meta_string( $item->ID, self::META_BASE_CONTENT_HASH );
		if ( $base_hash !== '' && ! hash_equals( $base_hash, self::content_hash( $target->post_content ) ) ) {
			return self::conflict_prepared_item( $item );
		}

		$staged = update_post_meta( $item->ID, self::META_FINALIZED_CONTENT, wp_slash( $content ) );
		if ( $staged === false ) {
			return self::fail_item( $item->ID, $lease_owner, [ [ 'message' => 'WordPress failed to stage finalized Gutenberg content.' ] ], 'WordPress failed to stage finalized content; live content was left unchanged.' );
		}

		self::set_status( $item->ID, self::STATUS_PREPARED );
		self::clear_lease( $item->ID );
		update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, [] );

		$batch        = self::find_batch( $item->post_parent );
		$batch_result = $batch instanceof WP_Post ? self::finish_batch_if_complete( $batch ) : [ 'done' => true, 'batch' => null ];
		if ( is_wp_error( $batch_result ) ) {
			return $batch_result;
		}
		$fresh_item = self::find_item( $item->ID );

		return [
			'item'  => $fresh_item instanceof WP_Post ? self::shape_item( $fresh_item ) : self::shape_item( $item ),
			'batch' => $batch_result['batch'],
			'done'  => $batch_result['done'] ?? false,
		];
	}

	/**
	 * Fail a running item with a given error list.
	 *
	 * @since 1.0.0
	 * @param int    $item_id     Item post ID.
	 * @param string $lease_owner Lease owner UUID.
	 * @param mixed  $errors      Validation errors to store.
	 * @param string $message     Human-readable failure message.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function fail_item( int $item_id, string $lease_owner, mixed $errors, string $message = '' ): array|WP_Error {
		$item = self::find_item( $item_id );
		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_not_found', sprintf( 'Gutenberg item %d was not found.', $item_id ), [ 'status' => 404 ] );
		}
		if ( self::gb_status( $item->ID ) !== self::STATUS_RUNNING || ! self::lease_is_valid( $item->ID, $lease_owner ) ) {
			return new WP_Error( 'gutenberg_item_lease_invalid', 'The item finalization lease is no longer active.', [ 'status' => 409 ] );
		}
		self::set_status( $item->ID, self::STATUS_FAILED );
		self::clear_lease( $item->ID );
		update_post_meta( $item->ID, self::META_VALIDATION_ERRORS, self::compact_validation_errors( $errors, $item ) );
		self::mark_batch_failed( $item->post_parent, $message !== '' ? $message : 'One or more Gutenberg items failed validation.' );

		$batch      = self::find_batch( $item->post_parent );
		$fresh_item = self::find_item( $item->ID );

		return [
			'item'  => $fresh_item instanceof WP_Post ? self::shape_item( $fresh_item ) : self::shape_item( $item ),
			'batch' => $batch instanceof WP_Post ? self::shape_batch( $batch ) : null,
			'done'  => true,
		];
	}

	/**
	 * Mark a batch as failed and record the error message.
	 *
	 * @since 1.0.0
	 * @param int    $batch_id Batch post ID.
	 * @param string $message  Human-readable failure message.
	 * @return void
	 */
	public static function mark_batch_failed( int $batch_id, string $message ): void {
		$batch = self::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return;
		}
		self::set_status( $batch->ID, self::STATUS_FAILED );
		self::clear_lease( $batch->ID );
		update_post_meta( $batch->ID, self::META_LAST_ERROR, $message );
	}

	/**
	 * Cancel an entire batch and all its non-finalized items.
	 *
	 * @since 1.0.0
	 * @param int $batch_id Batch post ID.
	 * @return array<string, mixed>|WP_Error Canceled batch shape, or WP_Error.
	 */
	public static function cancel_batch( int $batch_id ): array|WP_Error {
		$batch = self::find_batch( $batch_id );
		if ( ! $batch instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_batch_not_found', sprintf( 'Gutenberg batch %d was not found.', $batch_id ), [ 'status' => 404 ] );
		}
		if ( self::gb_status( $batch->ID ) === self::STATUS_FINALIZED ) {
			return new WP_Error( 'gutenberg_batch_already_finalized', 'Finalized Gutenberg batches cannot be canceled.', [ 'status' => 409 ] );
		}
		self::set_status( $batch->ID, self::STATUS_CANCELED );
		self::clear_lease( $batch->ID );
		foreach ( self::get_items( $batch->ID ) as $item ) {
			if ( self::gb_status( $item->ID ) === self::STATUS_FINALIZED ) {
				continue;
			}
			self::set_status( $item->ID, self::STATUS_CANCELED );
			self::clear_lease( $item->ID );
		}
		$fresh = self::find_batch( $batch->ID ) ?? $batch;
		return self::shape_batch( $fresh );
	}

	/**
	 * Cancel a single item without touching the target post_content.
	 *
	 * @since 1.0.0
	 * @param int $item_id Item post ID.
	 * @return array<string, mixed>|WP_Error Canceled item shape, or WP_Error.
	 */
	public static function cancel_item( int $item_id ): array|WP_Error {
		$item = self::find_item( $item_id );
		if ( ! $item instanceof WP_Post ) {
			return new WP_Error( 'gutenberg_item_not_found', sprintf( 'Gutenberg item %d was not found.', $item_id ), [ 'status' => 404 ] );
		}
		if ( in_array( self::gb_status( $item->ID ), [ self::STATUS_FINALIZED, self::STATUS_CANCELED, self::STATUS_STALE ], true ) ) {
			return new WP_Error( 'gutenberg_item_not_cancelable', 'This Gutenberg pending item is already terminal.', [ 'status' => 409 ] );
		}
		self::set_status( $item->ID, self::STATUS_CANCELED );
		self::clear_lease( $item->ID );
		$batch = self::find_batch( $item->post_parent );
		if ( $batch instanceof WP_Post && self::get_items( $batch->ID, self::NON_TERMINAL_STATUSES ) === [] ) {
			self::set_status( $batch->ID, self::STATUS_CANCELED );
			self::clear_lease( $batch->ID );
		}
		$fresh = self::find_item( $item->ID ) ?? $item;
		return self::shape_item( $fresh );
	}

	/**
	 * Build the startup instruction string for an agent based on runtime state.
	 *
	 * @since 1.0.0
	 * @return string Instruction string.
	 */
	public static function finalizer_runtime_startup_instruction(): string {
		$runtime = self::finalizer_runtime_status();
		if ( ( $runtime['online'] ?? false ) === true ) {
			return sprintf(
				'The WPCodex Block Editor Queue page is open. Keep %s open while Gutenberg static/native changes are queued and finalized. You can stream %s with curl -N, or poll %s with curl, to check whether the page is still online. If a later status shows finalizer_runtime.online=false, ask the user to reopen it before treating queued changes as live.',
				self::finalizer_dashboard_url(),
				(string) ( $runtime['sse_url'] ?? self::finalizer_runtime_sse_url() ),
				(string) ( $runtime['poll_url'] ?? self::finalizer_runtime_poll_url() )
			);
		}
		return sprintf(
			'The WPCodex Block Editor Queue page is not currently online. Before queueing Gutenberg static/native changes, ask the user to open %s in wp-admin and keep it open while you work. Stream %s with curl -N, or poll %s with curl; if it stays or becomes offline, ask the user to reopen it before treating queued changes as live.',
			self::finalizer_dashboard_url(),
			(string) ( $runtime['sse_url'] ?? self::finalizer_runtime_sse_url() ),
			(string) ( $runtime['poll_url'] ?? self::finalizer_runtime_poll_url() )
		);
	}

	/**
	 * Return (or lazily generate) the token used to gate the SSE and poll REST endpoints.
	 *
	 * @since 1.0.0
	 * @return string URL-safe base64 token string.
	 */
	public static function finalizer_runtime_poll_token(): string {
		/** @var mixed $option_value */
		$option_value = get_option( self::FINALIZER_RUNTIME_POLL_TOKEN_OPTION, '' );
		if ( is_string( $option_value ) && preg_match( '/^[A-Za-z0-9_-]{22}$/', $option_value ) === 1 ) {
			return $option_value;
		}
		$token = rtrim(
			strtr( base64_encode( random_bytes( self::FINALIZER_RUNTIME_POLL_TOKEN_BYTES ) ), '+/', '-_' ),
			'='
		);
		update_option( self::FINALIZER_RUNTIME_POLL_TOKEN_OPTION, $token, false );
		return $token;
	}

	/**
	 * Return an HMAC-derived token scoped to a specific batch ID.
	 *
	 * @since 1.0.0
	 * @param int $batch_id Batch post ID.
	 * @return string 32-char hex token.
	 */
	public static function finalizer_runtime_batch_token( int $batch_id ): string {
		return substr( hash_hmac( 'sha256', (string) $batch_id, self::finalizer_runtime_poll_token() ), 0, 32 );
	}

	/**
	 * Validate a batch-scoped token.
	 *
	 * @since 1.0.0
	 * @param int    $batch_id Batch post ID.
	 * @param string $token    Token to validate.
	 * @return bool True if valid.
	 */
	public static function finalizer_runtime_batch_token_is_valid( int $batch_id, string $token ): bool {
		return $batch_id > 0 && $token !== '' && hash_equals( self::finalizer_runtime_batch_token( $batch_id ), $token );
	}

	/**
	 * Build the query-string args for the SSE/poll REST endpoints.
	 *
	 * @since 1.0.0
	 * @param int|null $batch_id Optional batch ID to scope the token.
	 * @return array<string, string|int>
	 */
	public static function finalizer_runtime_url_args( ?int $batch_id = null ): array {
		$args = [ 'token' => self::finalizer_runtime_poll_token() ];
		if ( $batch_id !== null && $batch_id > 0 ) {
			$args['batch_id']    = $batch_id;
			$args['batch_token'] = self::finalizer_runtime_batch_token( $batch_id );
		}
		return $args;
	}

	/**
	 * Return the REST URL for the finalizer runtime poll endpoint.
	 *
	 * @since 1.0.0
	 * @param int|null $batch_id Optional batch ID to scope the token.
	 * @return string Full URL with query args.
	 */
	public static function finalizer_runtime_poll_url( ?int $batch_id = null ): string {
		return add_query_arg(
			self::finalizer_runtime_url_args( $batch_id ),
			rest_url( 'wpcodex/v1/gutenberg/finalizer-runtime/status' )
		);
	}

	/**
	 * Return the REST URL for the finalizer runtime SSE endpoint.
	 *
	 * @since 1.0.0
	 * @param int|null $batch_id Optional batch ID to scope the token.
	 * @return string Full URL with query args.
	 */
	public static function finalizer_runtime_sse_url( ?int $batch_id = null ): string {
		return add_query_arg(
			self::finalizer_runtime_url_args( $batch_id ),
			rest_url( 'wpcodex/v1/gutenberg/finalizer-runtime/events' )
		);
	}

	/**
	 * Record a heartbeat from the JS runtime and return the updated status.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Current finalizer runtime status.
	 */
	public static function record_finalizer_runtime_heartbeat(): array {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return self::finalizer_runtime_status();
		}
		$records                      = self::finalizer_runtime_records();
		$records[ (string) $user_id ] = [
			'user_id'      => $user_id,
			'last_seen'    => time(),
			'last_seen_at' => self::now_mysql(),
		];
		set_transient( self::FINALIZER_RUNTIME_TRANSIENT, $records, self::FINALIZER_RUNTIME_TTL_SECONDS );
		return self::finalizer_runtime_status();
	}

	/**
	 * Return the current finalizer runtime status array.
	 *
	 * @since 1.0.0
	 * @param WP_Post|null $batch Optional batch to check per-batch finalizability.
	 * @return array<string, mixed>
	 */
	public static function finalizer_runtime_status( ?WP_Post $batch = null ): array {
		$all_records  = self::finalizer_runtime_records();
		$records      = self::finalizer_runtime_online_records();
		$can_finalize = false;

		if ( $batch instanceof WP_Post ) {
			foreach ( $records as $record ) {
				$user_id = is_scalar( $record['user_id'] ?? null ) ? (int) $record['user_id'] : 0;
				if ( $user_id > 0 && self::finalizer_runtime_user_can_finalize_batch( $user_id, $batch ) ) {
					$can_finalize = true;
					break;
				}
			}
		}

		return [
			'online'               => $records !== [],
			'can_finalize_batch'   => $batch instanceof WP_Post ? $can_finalize : null,
			'online_runtime_count' => count( $records ),
			'dashboard_url'        => self::finalizer_dashboard_url(),
			'poll_url'             => self::finalizer_runtime_poll_url( $batch instanceof WP_Post ? $batch->ID : null ),
			'sse_url'              => self::finalizer_runtime_sse_url( $batch instanceof WP_Post ? $batch->ID : null ),
			'last_seen_at'         => self::finalizer_runtime_last_seen_at( $records ),
			'last_known_seen_at'   => self::finalizer_runtime_last_seen_at( array_values( $all_records ) ),
			'offline_reason'       => self::finalizer_runtime_offline_reason( $records, $all_records ),
			'stale_after_seconds'  => self::FINALIZER_RUNTIME_STALE_SECONDS,
		];
	}

	/**
	 * Read raw heartbeat records from the transient store.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Keyed by user_id string.
	 */
	public static function finalizer_runtime_records(): array {
		/** @var mixed $raw */
		$raw = get_transient( self::FINALIZER_RUNTIME_TRANSIENT );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$records = [];
		foreach ( array_filter( $raw, static fn( mixed $r ): bool => is_array( $r ) ) as $key => $record ) {
			$string_record = array_filter(
				$record,
				static fn( mixed $v, mixed $k ): bool => is_string( $k ),
				ARRAY_FILTER_USE_BOTH
			);
			/** @var array<string, mixed> $string_record */
			$records[ (string) $key ] = $string_record;
		}
		return $records;
	}

	/**
	 * Return only the runtime records that have sent a heartbeat recently enough.
	 *
	 * @since 1.0.0
	 * @return list<array<string, mixed>>
	 */
	public static function finalizer_runtime_online_records(): array {
		$cutoff  = time() - self::FINALIZER_RUNTIME_STALE_SECONDS;
		$records = [];
		foreach ( self::finalizer_runtime_records() as $record ) {
			$last_seen = is_scalar( $record['last_seen'] ?? null ) ? (int) $record['last_seen'] : 0;
			if ( $last_seen < $cutoff ) {
				continue;
			}
			$records[] = $record;
		}
		return $records;
	}

	/**
	 * Return a human-readable reason why the runtime is offline.
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>>          $online_records Currently online records.
	 * @param array<string, array<string, mixed>> $all_records    All records including stale.
	 * @return string "last_heartbeat_stale", "not_seen", or "" when online.
	 */
	public static function finalizer_runtime_offline_reason( array $online_records, array $all_records ): string {
		if ( $online_records !== [] ) {
			return '';
		}
		if ( $all_records !== [] ) {
			return 'last_heartbeat_stale';
		}
		return 'not_seen';
	}

	/**
	 * Return the last_seen_at timestamp from the most-recently-seen record in a list.
	 *
	 * @since 1.0.0
	 * @param list<array<string, mixed>> $records Heartbeat records.
	 * @return string MySQL datetime string, or empty string when no records.
	 */
	public static function finalizer_runtime_last_seen_at( array $records ): string {
		$latest    = 0;
		$latest_at = '';
		foreach ( $records as $record ) {
			$last_seen = is_scalar( $record['last_seen'] ?? null ) ? (int) $record['last_seen'] : 0;
			if ( $last_seen <= $latest ) {
				continue;
			}
			$latest    = $last_seen;
			$latest_at = is_scalar( $record['last_seen_at'] ?? null ) ? (string) $record['last_seen_at'] : '';
		}
		return $latest_at;
	}

	/**
	 * Determine whether a specific WP user can finalize all items in a batch.
	 *
	 * @since 1.0.0
	 * @param int     $user_id WordPress user ID.
	 * @param WP_Post $batch   Batch post.
	 * @return bool
	 */
	public static function finalizer_runtime_user_can_finalize_batch( int $user_id, WP_Post $batch ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		foreach ( self::get_items( $batch->ID ) as $item ) {
			$target_id = self::meta_int( $item->ID, self::META_TARGET_ID );
			if ( $target_id <= 0 || ! user_can( $user_id, 'edit_post', $target_id ) ) {
				return false;
			}
		}
		return true;
	}
}

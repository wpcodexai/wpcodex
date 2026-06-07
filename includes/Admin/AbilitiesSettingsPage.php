<?php
/**
 * Abilities Hub page — lists every registered WPCodex ability with an enable/disable toggle.
 *
 * Mirrors Novamira's "Abilities Hub" screen (added v1.6.0): every ability available
 * to AI agents is listed, grouped by category, and can be toggled individually.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class AbilitiesSettingsPage
 */
final class AbilitiesSettingsPage {

	/**
	 * Render the Abilities Hub page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		self::handle_toggle();
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-abilities-settings">
			<h1 class="wpcodex-page-title">
				<?php esc_html_e( 'Abilities Settings', 'wpcodex' ); ?>
			</h1>
			<p class="wpcodex-page-description">
				<?php esc_html_e( 'Enable or disable the abilities available to AI agents on this site. Every ability registered through the WordPress Abilities API appears here, including abilities added by third-party plugins.', 'wpcodex' ); ?>
			</p>

			<?php self::render_ability_groups(); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Handle ability enable/disable form submission.
	 */
	private static function handle_toggle(): void {
		if ( ! isset( $_POST['wpcodex_ability_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'wpcodex_toggle_ability', 'wpcodex_ability_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ability_id = isset( $_POST['ability_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ability_id'] ) ) : '';
		// '1' = enable, '0' = disable — sent from the hidden input.
		$enable = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];

		if ( $ability_id ) {
			$option_key = 'wpcodex_ability_' . sanitize_key( str_replace( '/', '_', $ability_id ) );
			// Store 'yes'/'no' strings — WordPress does not reliably persist
			// boolean false. Using strings avoids silent option save failures.
			update_option( $option_key, $enable ? 'yes' : 'no', false );
		}
	}

	/**
	 * Render abilities grouped by category.
	 */
	private static function render_ability_groups(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( 'The WordPress Abilities API is not available. Ensure the wordpress/mcp-adapter is installed.', 'wpcodex' );
			echo '</p></div>';
			return;
		}

		$abilities = wp_get_abilities();

		if ( empty( $abilities ) ) {
			echo '<div class="wpcodex-empty-state"><p>';
			esc_html_e( 'No abilities registered yet. Abilities appear here once the plugin is fully loaded.', 'wpcodex' );
			echo '</p></div>';
			return;
		}

		// Group by category — only wpcodex/ abilities.
		$groups = [];
		foreach ( $abilities as $id => $ability ) {
			if ( ! is_string( $id ) || ! str_starts_with( $id, 'wpcodex/' ) ) {
				continue;
			}
			// wp_get_abilities() returns WP_Ability objects — use getters.
			if ( $ability instanceof \WP_Ability ) {
				$category = $ability->get_category() ?? 'general';
				$item     = [
					'id'          => $id,
					'label'       => $ability->get_label(),
					'description' => $ability->get_description(),
					'category'    => $category,
				];
			} else {
				// Fallback for plain arrays (older / custom implementations).
				$category = $ability['category'] ?? 'general';
				$item     = array_merge( (array) $ability, [ 'id' => $id ] );
			}
			$groups[ $category ][] = $item;
		}

		$category_labels = [
			'wpcodex'        => __( 'WPCodex', 'wpcodex' ),
			'wpcodex-skills' => __( 'WPCodex Skills', 'wpcodex' ),
			'wpcodex-gutenberg' => __( 'WPCodex Gutenberg', 'wpcodex' ),
			'general'        => __( 'General', 'wpcodex' ),
		];

		foreach ( $groups as $category => $items ) {
			// Only render known wpcodex categories — skip anything else.
			if ( ! isset( $category_labels[ $category ] ) ) {
				continue;
			}
			$label = $category_labels[ $category ];
			?>
			<div class="wpcodex-ability-group">
				<h2 class="wpcodex-group-title"><?php echo esc_html( $label ); ?></h2>
				<div class="wpcodex-ability-cards">
					<?php foreach ( $items as $ability ) : ?>
						<?php self::render_ability_card( $ability ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render a single ability card with toggle.
	 *
	 * @param array<string, mixed> $ability Ability data including 'id'.
	 */
	private static function render_ability_card( array $ability ): void {
		$id          = (string) ( $ability['id'] ?? '' );
		$label       = (string) ( $ability['label'] ?? $id );
		$description = (string) ( $ability['description'] ?? '' );
		$option_key  = 'wpcodex_ability_' . sanitize_key( str_replace( '/', '_', $id ) );

		// Read stored value: 'yes' = enabled, 'no' = disabled.
		// Default 'yes' so abilities are enabled until explicitly turned off.
		$stored  = get_option( $option_key, 'yes' );
		$enabled = 'no' !== $stored; // anything that isn't 'no' is treated as enabled.

		$toggle_id = 'wpcodex-ability-' . sanitize_html_class( str_replace( '/', '-', $id ) );
		?>
		<div class="wpcodex-ability-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
			<div class="wpcodex-ability-card__header">
				<span class="wpcodex-ability-card__name"><?php echo esc_html( $label ); ?></span>
				<span class="wpcodex-ability-card__id"><?php echo esc_html( $id ); ?></span>
			</div>
			<?php if ( $description ) : ?>
				<p class="wpcodex-ability-card__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<div class="wpcodex-ability-card__footer">
				<form method="post" action="">
					<?php wp_nonce_field( 'wpcodex_toggle_ability', 'wpcodex_ability_nonce' ); ?>
					<input type="hidden" name="ability_id" value="<?php echo esc_attr( $id ); ?>">
					<?php // enabled field: send '0' to disable (currently on), '1' to enable (currently off). ?>
					<input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
					<label class="wpcodex-toggle" for="<?php echo esc_attr( $toggle_id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $toggle_id ); ?>"
							<?php checked( $enabled ); ?>
							onchange="this.closest('form').submit()"
						>
						<span class="wpcodex-toggle__slider"></span>
						<span class="wpcodex-toggle__label">
							<?php echo $enabled
								? esc_html__( 'Enabled', 'wpcodex' )
								: esc_html__( 'Disabled', 'wpcodex' ); ?>
						</span>
					</label>
				</form>
			</div>
		</div>
		<?php
	}
}
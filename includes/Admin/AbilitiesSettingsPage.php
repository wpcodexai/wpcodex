<?php
/**
 * Abilities page — lists every registered WPWorker ability with an enable/disable toggle.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Admin;

use WPWorker\Abilities\Abilities;

/**
 * Class AbilitiesSettingsPage
 */
final class AbilitiesSettingsPage {

	/**
	 * Render the Abilities page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'worker-ai' ) );
		}

		self::handle_toggle();
		?>
		<div class="wrap wpworker-wrap" id="wpworker-abilities-settings">
			<h1 class="wpworker-page-title">
				<?php esc_html_e( 'Abilities Settings', 'worker-ai' ); ?>
			</h1>
			<p class="wpworker-page-description">
				<?php esc_html_e( 'Enable or disable the abilities available to AI agents on this site. Every ability registered through the WordPress Abilities API appears here, including abilities added by third-party plugins.', 'worker-ai' ); ?>
			</p>

			<?php self::render_ability_groups(); ?>
		</div>
		<?php
	}

	/**
	 * Handle ability enable/disable form submission.
	 */
	private static function handle_toggle(): void {
		if ( ! isset( $_POST['wpworker_ability_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'wpworker_toggle_ability', 'wpworker_ability_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ability_id = isset( $_POST['ability_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ability_id'] ) ) : '';
		// '1' = enable, '0' = disable — sent from the hidden input.
		$enable = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];

		if ( $ability_id ) {
			$option_key = 'wpworker_ability_' . sanitize_key( str_replace( '/', '_', $ability_id ) );
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
			esc_html_e( 'The WordPress Abilities API is not available. Ensure the wordpress/mcp-adapter is installed.', 'worker-ai' );
			echo '</p></div>';
			return;
		}

		// Read the full in-memory index (enabled + disabled) built during
		// wp_abilities_api_init. wp_get_abilities() only returns currently
		// registered (enabled) abilities, so disabled abilities would silently
		// disappear from this page and become impossible to re-enable.
		$index = Abilities::get_all_ability_data();

		if ( empty( $index ) ) {
			echo '<div class="wpworker-empty-state"><p>';
			esc_html_e( 'No abilities registered yet. Abilities appear here once the plugin is fully loaded.', 'worker-ai' );
			echo '</p></div>';
			return;
		}

		// Group by category — only wpworker/ abilities.
		$groups = [];
		foreach ( $index as $id => $item ) {
			if ( ! is_string( $id ) || ! str_starts_with( $id, 'wpworker/' ) ) {
				continue;
			}
			$category            = (string) ( $item['category'] ?? 'general' );
			$groups[ $category ][] = $item;
		}

		$category_labels = [
			'wpworker-general'        => __( 'General', 'worker-ai' ),
			'wpworker'        => __( 'Worker AI', 'worker-ai' ),
			'wpworker-skills' => __( 'Worker AI Skills', 'worker-ai' ),
			'wpworker-gutenberg' => __( 'Worker AI Gutenberg', 'worker-ai' ),
			'wpworker-site' => __( 'Worker AI Site', 'worker-ai' ),
			'wpworker-plugins' => __( 'Plugins', 'worker-ai' ),
			'wpworker-themes' => __( 'Themes', 'worker-ai' ),
		];

		foreach ( $groups as $category => $items ) {
			// Only render known wpworker categories — skip anything else.
			if ( ! isset( $category_labels[ $category ] ) ) {
				continue;
			}
			$label = $category_labels[ $category ];
			?>
			<div class="wpworker-ability-group">
				<h2 class="wpworker-group-title"><?php echo esc_html( $label ); ?></h2>
				<div class="wpworker-ability-cards">
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
		$option_key  = 'wpworker_ability_' . sanitize_key( str_replace( '/', '_', $id ) );

		// Read stored value: 'yes' = enabled, 'no' = disabled.
		// Default 'yes' so abilities are enabled until explicitly turned off.
		$stored  = get_option( $option_key, 'yes' );
		$enabled = 'no' !== $stored; // anything that isn't 'no' is treated as enabled.

		$toggle_id = 'wpworker-ability-' . sanitize_html_class( str_replace( '/', '-', $id ) );
		?>
		<div class="wpworker-ability-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
			<div class="wpworker-ability-card__header">
				<span class="wpworker-ability-card__name"><?php echo esc_html( $label ); ?></span>
				<span class="wpworker-ability-card__id"><?php echo esc_html( $id ); ?></span>
			</div>
			<?php if ( $description ) : ?>
				<p class="wpworker-ability-card__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<div class="wpworker-ability-card__footer">
				<form method="post" action="">
					<?php wp_nonce_field( 'wpworker_toggle_ability', 'wpworker_ability_nonce' ); ?>
					<input type="hidden" name="ability_id" value="<?php echo esc_attr( $id ); ?>">
					<?php // enabled field: send '0' to disable (currently on), '1' to enable (currently off). ?>
					<input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
					<label class="wpworker-toggle" for="<?php echo esc_attr( $toggle_id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $toggle_id ); ?>"
							<?php checked( $enabled ); ?>
							onchange="this.closest('form').submit()"
						>
						<span class="wpworker-toggle__slider"></span>
						<span class="wpworker-toggle__label">
							<?php echo $enabled
								? esc_html__( 'Enabled', 'worker-ai' )
								: esc_html__( 'Disabled', 'worker-ai' ); ?>
						</span>
					</label>
				</form>
			</div>
		</div>
		<?php
	}
}
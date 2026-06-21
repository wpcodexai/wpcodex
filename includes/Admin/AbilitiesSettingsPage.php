<?php
/**
 * Abilities page — lists every registered AllyWorker ability with an enable/disable toggle.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Admin;

use AllyWorker\Abilities\Abilities;

/**
 * Class AbilitiesSettingsPage
 */
final class AbilitiesSettingsPage {

	/**
	 * Render the Abilities page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'allyworker' ) );
		}

		self::handle_toggle();
		?>
		<div class="wrap allyworker-wrap" id="allyworker-abilities-settings">
			<h1 class="allyworker-page-title">
				<?php esc_html_e( 'Abilities Settings', 'allyworker' ); ?>
			</h1>
			<p class="allyworker-page-description">
				<?php esc_html_e( 'Enable or disable the abilities available to AI agents on this site. Every ability registered through the WordPress Abilities API appears here, including abilities added by third-party plugins.', 'allyworker' ); ?>
			</p>

			<?php self::render_ability_groups(); ?>
		</div>
		<?php
	}

	/**
	 * Handle ability enable/disable form submission.
	 */
	private static function handle_toggle(): void {
		if ( ! isset( $_POST['allyworker_ability_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'allyworker_toggle_ability', 'allyworker_ability_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ability_id = isset( $_POST['ability_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ability_id'] ) ) : '';
		// '1' = enable, '0' = disable — sent from the hidden input.
		$enable = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];

		if ( $ability_id ) {
			$option_key = 'allyworker_ability_' . sanitize_key( str_replace( '/', '_', $ability_id ) );
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
			esc_html_e( 'The WordPress Abilities API is not available. Ensure the wordpress/mcp-adapter is installed.', 'allyworker' );
			echo '</p></div>';
			return;
		}

		// Read the full in-memory index (enabled + disabled) built during
		// wp_abilities_api_init. wp_get_abilities() only returns currently
		// registered (enabled) abilities, so disabled abilities would silently
		// disappear from this page and become impossible to re-enable.
		$index = Abilities::get_all_ability_data();

		if ( empty( $index ) ) {
			echo '<div class="allyworker-empty-state"><p>';
			esc_html_e( 'No abilities registered yet. Abilities appear here once the plugin is fully loaded.', 'allyworker' );
			echo '</p></div>';
			return;
		}

		// Group by category — only allyworker/ abilities.
		$groups = [];
		foreach ( $index as $id => $item ) {
			if ( ! is_string( $id ) || ! str_starts_with( $id, 'allyworker/' ) ) {
				continue;
			}
			$category            = (string) ( $item['category'] ?? 'general' );
			$groups[ $category ][] = $item;
		}

		$category_labels = [
			'allyworker-general'        => __( 'General', 'allyworker' ),
			'allyworker'        => __( 'AllyWorker', 'allyworker' ),
			'allyworker-skills' => __( 'AllyWorker Skills', 'allyworker' ),
			'allyworker-gutenberg' => __( 'AllyWorker Gutenberg', 'allyworker' ),
			'allyworker-site' => __( 'AllyWorker Site', 'allyworker' ),
			'allyworker-plugins' => __( 'Plugins', 'allyworker' ),
			'allyworker-themes' => __( 'Themes', 'allyworker' ),
		];

		foreach ( $groups as $category => $items ) {
			// Only render known allyworker categories — skip anything else.
			if ( ! isset( $category_labels[ $category ] ) ) {
				continue;
			}
			$label = $category_labels[ $category ];
			?>
			<div class="allyworker-ability-group">
				<h2 class="allyworker-group-title"><?php echo esc_html( $label ); ?></h2>
				<div class="allyworker-ability-cards">
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
		$option_key  = 'allyworker_ability_' . sanitize_key( str_replace( '/', '_', $id ) );

		// Read stored value: 'yes' = enabled, 'no' = disabled.
		// Default 'yes' so abilities are enabled until explicitly turned off.
		$stored  = get_option( $option_key, 'yes' );
		$enabled = 'no' !== $stored; // anything that isn't 'no' is treated as enabled.

		$toggle_id = 'allyworker-ability-' . sanitize_html_class( str_replace( '/', '-', $id ) );
		?>
		<div class="allyworker-ability-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
			<div class="allyworker-ability-card__header">
				<span class="allyworker-ability-card__name"><?php echo esc_html( $label ); ?></span>
				<span class="allyworker-ability-card__id"><?php echo esc_html( $id ); ?></span>
			</div>
			<?php if ( $description ) : ?>
				<p class="allyworker-ability-card__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<div class="allyworker-ability-card__footer">
				<form method="post" action="">
					<?php wp_nonce_field( 'allyworker_toggle_ability', 'allyworker_ability_nonce' ); ?>
					<input type="hidden" name="ability_id" value="<?php echo esc_attr( $id ); ?>">
					<?php // enabled field: send '0' to disable (currently on), '1' to enable (currently off). ?>
					<input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
					<label class="allyworker-toggle" for="<?php echo esc_attr( $toggle_id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $toggle_id ); ?>"
							<?php checked( $enabled ); ?>
							onchange="this.closest('form').submit()"
						>
						<span class="allyworker-toggle__slider"></span>
						<span class="allyworker-toggle__label">
							<?php echo $enabled
								? esc_html__( 'Enabled', 'allyworker' )
								: esc_html__( 'Disabled', 'allyworker' ); ?>
						</span>
					</label>
				</form>
			</div>
		</div>
		<?php
	}
}

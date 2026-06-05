<?php
/**
 * Settings Page — ability toggles.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class SettingsPage
 */
class SettingsPage {

	public const PAGE_SLUG    = 'wpcodex-settings';
	public const OPTION_GROUP = 'wpcodex_settings';

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'wpcodex',
			__( 'Settings', 'wpcodex' ),
			__( 'Settings', 'wpcodex' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function register_settings(): void {
		$abilities = [
			'wpcodex_enable_php_execute' => __( 'PHP Execution', 'wpcodex' ),
			'wpcodex_enable_wpcli'       => __( 'WP-CLI', 'wpcodex' ),
			'wpcodex_enable_file_write'  => __( 'File Write / Edit / Delete', 'wpcodex' ),
			'wpcodex_enable_db_query'    => __( 'Database Query', 'wpcodex' ),
		];

		foreach ( $abilities as $option => $label ) {
			register_setting( self::OPTION_GROUP, $option, [
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			] );

			add_settings_field(
				$option,
				$label,
				static function () use ( $option, $label ): void {
					$value = get_option( $option, true );
					printf(
						'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
						esc_attr( $option ),
						checked( 1, (int) $value, false ),
						esc_html( $label )
					);
				},
				self::PAGE_SLUG,
				'wpcodex_abilities_section'
			);
		}

		add_settings_section(
			'wpcodex_abilities_section',
			__( 'AI Abilities', 'wpcodex' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Enable or disable individual AI abilities.', 'wpcodex' ) . '</p>';
			},
			self::PAGE_SLUG
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPCodex Settings', 'wpcodex' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
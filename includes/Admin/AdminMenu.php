<?php
/**
 * Admin Menu registration.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class AdminMenu
 */
class AdminMenu {

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
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'WPCodex', 'wpcodex' ),
			'WPCodex',
			'manage_options',
			'wpcodex',
			[ $this, 'render_dashboard' ],
			'dashicons-rest-api',
			80
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}
		echo '<div class="wrap" id="wpcodex-app">';
		echo '<h1>' . esc_html__( 'WPCodex', 'wpcodex' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Enqueue compiled admin assets — only on WPCodex pages.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'wpcodex' ) ) {
			return;
		}

		$asset_file = WPCODEX_DIR . 'assets/admin/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [], 'version' => WPCODEX_VERSION ];

		wp_enqueue_style(
			'wpcodex-admin',
			WPCODEX_URL . 'assets/admin/admin.css',
			[],
			$asset['version']
		);

		wp_enqueue_script(
			'wpcodex-admin',
			WPCODEX_URL . 'assets/admin/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script( 'wpcodex-admin', 'wpcodexData', [
			'apiBase' => esc_url_raw( rest_url( 'wpcodex/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => [
				'copied' => __( 'Copied!', 'wpcodex' ),
				'error'  => __( 'Error. Please try again.', 'wpcodex' ),
			],
		] );
	}
}
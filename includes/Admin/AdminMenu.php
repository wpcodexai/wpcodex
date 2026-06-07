<?php
/**
 * Admin Menu — registers the WPCodex top-level menu and all sub-pages.
 *
 * Menu structure (mirrors Novamira's layout):
 *   WPCodex
 *   ├── Configuration   ← default landing page (abilities on/off + connect)
 *   ├── Abilities Settings
 *   ├── Skills
 *   ├── Sandbox
 *   ├── Block Editor
 *   └── Get Pro  (styled accent link)
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class AdminMenu
 */
final class AdminMenu {

	/** Top-level menu slug. */
	public const MENU_SLUG = 'wpcodex';

	/** Option that gates whether AI abilities are active. */
	public const ABILITIES_ENABLED_OPTION = 'wpcodex_abilities_enabled';

	/** @var self|null */
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head',            [ $this, 'inline_menu_styles' ] );
		add_action( 'admin_bar_menu',        [ $this, 'admin_bar_indicator' ], 100 );
		new ConfigurationPage();
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function register_menus(): void {
		add_menu_page(
			__( 'WPCodex', 'wpcodex' ),
			__( 'WPCodex', 'wpcodex' ),
			'manage_options',
			self::MENU_SLUG,
			[ ConfigurationPage::class, 'render' ],
			$this->get_menu_icon(),
			80
		);

		// First submenu replaces the auto-generated duplicate parent entry.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configuration — WPCodex', 'wpcodex' ),
			__( 'Configuration', 'wpcodex' ),
			'manage_options',
			self::MENU_SLUG,
			[ ConfigurationPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Abilities Settings — WPCodex', 'wpcodex' ),
			__( 'Abilities Settings', 'wpcodex' ),
			'manage_options',
			'wpcodex-abilities',
			[ AbilitiesSettingsPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Skills — WPCodex', 'wpcodex' ),
			__( 'Skills', 'wpcodex' ),
			'manage_options',
			'wpcodex-skills',
			[ SkillsPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Sandbox — WPCodex', 'wpcodex' ),
			__( 'Sandbox', 'wpcodex' ),
			'manage_options',
			'wpcodex-sandbox',
			[ SandboxPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Block Editor — WPCodex', 'wpcodex' ),
			__( 'Block Editor', 'wpcodex' ),
			'manage_options',
			'wpcodex-block-editor-queue',
			[ BlockEditorPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Get Pro — WPCodex', 'wpcodex' ),
			__( 'Get Pro', 'wpcodex' ),
			'manage_options',
			'wpcodex-get-pro',
			[ $this, 'render_get_pro_redirect' ]
		);
	}

	// -------------------------------------------------------------------------
	// Admin bar indicator
	// -------------------------------------------------------------------------

	/**
	 * Show a red "WPCodex ON" indicator in the admin bar when abilities are enabled.
	 * Mirrors Novamira's persistent visual reminder.
	 */
	public function admin_bar_indicator( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::are_abilities_enabled() ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'wpcodex-indicator',
			'title' => '<span class="wpcodex-ab-dot"></span>'
				. esc_html__( 'WPCodex ON', 'wpcodex' ),
			'href'  => admin_url( 'admin.php?page=wpcodex' ),
			'meta'  => [ 'title' => __( 'AI Abilities are active — click to configure', 'wpcodex' ) ],
		] );

		// Toggle off child node.
		$wp_admin_bar->add_node( [
			'parent' => 'wpcodex-indicator',
			'id'     => 'wpcodex-indicator-off',
			'title'  => esc_html__( 'Turn off AI Abilities', 'wpcodex' ),
			'href'   => wp_nonce_url(
				admin_url( 'admin.php?page=wpcodex&wpcodex_toggle=off' ),
				'wpcodex_toggle_abilities'
			),
		] );
	}

	// -------------------------------------------------------------------------
	// Page callbacks
	// -------------------------------------------------------------------------

	public function render_get_pro_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}
		?>
		<script>window.location.href = 'https://wpcodex.ai/pro/';</script>
		<div class="wrap"><p><?php esc_html_e( 'Redirecting to WPCodex Pro…', 'wpcodex' ); ?></p></div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_wpcodex_page( $hook_suffix ) ) {
			return;
		}

		$asset_file = WPCODEX_DIR . 'assets/admin/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? (array) require $asset_file
			: [ 'dependencies' => [], 'version' => WPCODEX_VERSION ];

		wp_enqueue_style(
			'wpcodex-admin',
			WPCODEX_URL . 'assets/admin/admin.css',
			[],
			$asset['version'] ?? WPCODEX_VERSION
		);

		wp_enqueue_script(
			'wpcodex-admin',
			WPCODEX_URL . 'assets/admin/admin.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? WPCODEX_VERSION,
			true
		);

		wp_localize_script(
			'wpcodex-admin',
			'wpcodexData',
			[
				'restUrl'          => esc_url_raw( rest_url() ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'currentPage'      => $this->current_page_id( $hook_suffix ),
				'adminUrl'         => esc_url_raw( admin_url( 'admin.php' ) ),
				'abilitiesEnabled' => self::are_abilities_enabled(),
				// NOTE: we intentionally do NOT call wp_get_abilities() here.
				// Doing so during admin_enqueue_scripts fires the Abilities registry
				// singleton too early, triggering other plugins' category hooks before
				// their categories are registered (causes PHP notices from e.g. Novamira).
				'i18n' => [
					'saved'    => __( 'Saved.', 'wpcodex' ),
					'deleted'  => __( 'Deleted.', 'wpcodex' ),
					'error'    => __( 'Something went wrong. Please try again.', 'wpcodex' ),
					'confirm'  => __( 'Are you sure?', 'wpcodex' ),
					'enabled'  => __( 'Enabled', 'wpcodex' ),
					'disabled' => __( 'Disabled', 'wpcodex' ),
					'enable'   => __( 'Enable', 'wpcodex' ),
					'disable'  => __( 'Disable', 'wpcodex' ),
					'edit'     => __( 'Edit', 'wpcodex' ),
					'delete'   => __( 'Delete', 'wpcodex' ),
					'cancel'   => __( 'Cancel', 'wpcodex' ),
					'save'     => __( 'Save', 'wpcodex' ),
				],
			]
		);

		wp_set_script_translations( 'wpcodex-admin', 'wpcodex' );
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Whether AI abilities are currently enabled sitewide.
	 */
	public static function are_abilities_enabled(): bool {
		return (bool) get_option( self::ABILITIES_ENABLED_OPTION, false );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function is_wpcodex_page( string $hook_suffix ): bool {
		return str_contains( $hook_suffix, 'wpcodex' );
	}

	private function current_page_id( string $hook_suffix ): string {
		$map = [
			'toplevel_page_wpcodex'                    => 'configuration',
			'wpcodex_page_wpcodex-abilities'           => 'abilities-settings',
			'wpcodex_page_wpcodex-skills'              => 'skills',
			'wpcodex_page_wpcodex-sandbox'             => 'sandbox',
			'wpcodex_page_wpcodex-block-editor-queue'  => 'block-editor',
			'wpcodex_page_wpcodex-get-pro'             => 'get-pro',
		];
		return $map[ $hook_suffix ] ?? 'configuration';
	}

	/**
	 * SVG icon — "c" lettermark in WordPress admin grey (transparent bg,
	 * white letter so it works in both light and dark admin colour schemes).
	 */
	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">'
			. '<rect width="20" height="20" rx="3" fill="#a7aaad"/>'
			. '<text x="4" y="15" font-family="sans-serif" font-size="13" font-weight="bold" fill="#1e2327">c</text>'
			. '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
	
	// -------------------------------------------------------------------------
	// Inline styles — must be in <head>, not an enqueued stylesheet.
	// The admin bar and admin menu are rendered outside plugin-scoped CSS,
	// so attribute selectors like #wpadminbar and #adminmenu need to be
	// injected directly into admin_head to take effect reliably.
	// -------------------------------------------------------------------------

	public function inline_menu_styles(): void {
		?>
		<style>
			/* ── Get Pro accent colour ── */
			#adminmenu a[href$="wpcodex-get-pro"] {
				color: #f5a623 !important;
				font-weight: 600;
			}
			#adminmenu a[href$="wpcodex-get-pro"]:hover {
				color: #f5a623 !important;
				text-decoration: underline;
			}

			/* ── Admin bar "WPCodex ON" indicator ── */
			#wpadminbar #wp-admin-bar-wpcodex-indicator > .ab-item {
				background: #d63638 !important;
				color: #fff !important;
				font-weight: 600;
			}
			#wpadminbar #wp-admin-bar-wpcodex-indicator > .ab-item:hover {
				background: #b32d2e !important;
			}
			.wpcodex-ab-dot {
				display: inline-block;
				width: 8px;
				height: 8px;
				background: #fff;
				border-radius: 50%;
				margin-right: 6px;
				vertical-align: middle;
				animation: wpcodex-pulse 2s infinite;
			}
			@keyframes wpcodex-pulse {
				0%, 100% { opacity: 1; }
				50%       { opacity: .4; }
			}
		</style>
		<?php
	}

}

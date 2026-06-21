<?php
/**
 * Admin Menu — registers the AllyWorker top-level menu and all sub-pages.
 *
 * Menu structure (mirrors AllyWorker's layout):
 *   AllyWorker
 *   ├── Configuration   ← default landing page (abilities on/off + connect)
 *   ├── Abilities Settings
 *   ├── Skills
 *   ├── Sandbox
 *   ├── Block Editor
 *   └── Get Pro  (styled accent link)
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Admin;

/**
 * Class AdminMenu
 *
 * @since 1.0.0
 */
final class AdminMenu {

	/**
	 * Top-level menu slug.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const MENU_SLUG = 'allyworker';

	/**
	 * Option that gates whether AI abilities are active.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const ABILITIES_ENABLED_OPTION = 'allyworker_abilities_enabled';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   self|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns (and lazily creates) the singleton instance.
	 *
	 * @since  1.0.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sets up admin hooks and instantiates the configuration page.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head',            [ $this, 'inline_menu_styles' ] );
		add_action( 'admin_bar_menu',        [ $this, 'admin_bar_indicator' ], 100 );
		new ConfigurationPage();
	}

	/**
	 * Registers the top-level menu page and all sub-pages.
	 *
	 * @since 1.0.0
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'AllyWorker', 'allyworker' ),
			__( 'AllyWorker', 'allyworker' ),
			'manage_options',
			self::MENU_SLUG,
			[ ConfigurationPage::class, 'render' ],
			$this->get_menu_icon(),
			80
		);

		// First submenu replaces the auto-generated duplicate parent entry.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configuration — AllyWorker', 'allyworker' ),
			__( 'Configuration', 'allyworker' ),
			'manage_options',
			self::MENU_SLUG,
			[ ConfigurationPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Abilities Settings — AllyWorker', 'allyworker' ),
			__( 'Abilities Settings', 'allyworker' ),
			'manage_options',
			'allyworker-abilities',
			[ AbilitiesSettingsPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Skills — AllyWorker', 'allyworker' ),
			__( 'Skills', 'allyworker' ),
			'manage_options',
			'allyworker-skills',
			[ SkillsPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Sandbox — AllyWorker', 'allyworker' ),
			__( 'Sandbox', 'allyworker' ),
			'manage_options',
			'allyworker-sandbox',
			[ SandboxPage::class, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Block Editor — AllyWorker', 'allyworker' ),
			__( 'Block Editor', 'allyworker' ),
			'manage_options',
			'allyworker-block-editor-queue',
			[ BlockEditorPage::class, 'render' ]
		);

		// add_submenu_page(
		// 	self::MENU_SLUG,
		// 	__( 'Get Pro — AllyWorker', 'allyworker' ),
		// 	__( 'Get Pro', 'allyworker' ),
		// 	'manage_options',
		// 	'allyworker-get-pro',
		// 	[ $this, 'render_get_pro_redirect' ]
		// );
	}

	/**
	 * Shows a red "AllyWorker ON" indicator in the admin bar when abilities are enabled.
	 *
	 * @since 1.0.0
	 * @param \WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public function admin_bar_indicator( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::are_abilities_enabled() ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'allyworker-indicator',
			'title' => '<span class="allyworker-ab-dot"></span>'
				. esc_html__( 'AllyWorker ON', 'allyworker' ),
			'href'  => admin_url( 'admin.php?page=allyworker' ),
			'meta'  => [ 'title' => __( 'AI Abilities are active — click to configure', 'allyworker' ) ],
		] );

		// Toggle off child node.
		$wp_admin_bar->add_node( [
			'parent' => 'allyworker-indicator',
			'id'     => 'allyworker-indicator-off',
			'title'  => esc_html__( 'Turn off AI Abilities', 'allyworker' ),
			'href'   => wp_nonce_url(
				admin_url( 'admin.php?page=allyworker&allyworker_toggle=off' ),
				'allyworker_toggle_abilities'
			),
		] );
	}

	// Page callbacks
	/**
	 * Renders the Get Pro redirect page.
	 *
	 * @since 1.0.0
	 */
	public function render_get_pro_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'allyworker' ) );
		}
		?>
		<script>window.location.href = 'https://allyworker.com/pro/';</script>
		<div class="wrap"><p><?php esc_html_e( 'Redirecting to AllyWorker Pro…', 'allyworker' ); ?></p></div>
		<?php
	}

	// Assets
	/**
	 * Enqueues admin scripts and styles for AllyWorker pages.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_allyworker_page( $hook_suffix ) ) {
			return;
		}

		$asset_file = ALLY_WORKER_DIR . 'assets/admin/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? (array) require $asset_file
			: [ 'dependencies' => [], 'version' => ALLY_WORKER_VERSION ];

		wp_enqueue_style(
			'allyworker-admin',
			ALLY_WORKER_URL . 'assets/admin/admin.css',
			[],
			$asset['version'] ?? ALLY_WORKER_VERSION
		);

		wp_enqueue_script(
			'allyworker-admin',
			ALLY_WORKER_URL . 'assets/admin/admin.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? ALLY_WORKER_VERSION,
			true
		);

		wp_localize_script(
			'allyworker-admin',
			'allyworkerData',
			[
				'restUrl'          => esc_url_raw( rest_url() ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'currentPage'      => $this->current_page_id( $hook_suffix ),
				'adminUrl'         => esc_url_raw( admin_url( 'admin.php' ) ),
				'abilitiesEnabled' => self::are_abilities_enabled(),
				// NOTE: we intentionally do NOT call wp_get_abilities() here.
				// Doing so during admin_enqueue_scripts fires the Abilities registry
				// singleton too early, triggering other plugins' category hooks before
				// their categories are registered.
				'i18n' => [
					'saved'    => __( 'Saved.', 'allyworker' ),
					'deleted'  => __( 'Deleted.', 'allyworker' ),
					'error'    => __( 'Something went wrong. Please try again.', 'allyworker' ),
					'confirm'  => __( 'Are you sure?', 'allyworker' ),
					'enabled'  => __( 'Enabled', 'allyworker' ),
					'disabled' => __( 'Disabled', 'allyworker' ),
					'enable'   => __( 'Enable', 'allyworker' ),
					'disable'  => __( 'Disable', 'allyworker' ),
					'edit'     => __( 'Edit', 'allyworker' ),
					'delete'   => __( 'Delete', 'allyworker' ),
					'cancel'   => __( 'Cancel', 'allyworker' ),
					'save'     => __( 'Save', 'allyworker' ),
				],
			]
		);

		wp_set_script_translations( 'allyworker-admin', 'allyworker' );
	}

	/**
	 * Checks whether AI abilities are currently enabled sitewide.
	 *
	 * @since  1.0.0
	 * @return bool True when abilities are enabled; false otherwise.
	 */
	public static function are_abilities_enabled(): bool {
		return (bool) get_option( self::ABILITIES_ENABLED_OPTION, false );
	}

	// Private helpers
	/**
	 * Checks whether the given hook suffix belongs to an AllyWorker admin page.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix The current admin page hook suffix.
	 * @return bool True when on an AllyWorker admin page; false otherwise.
	 */
	private function is_allyworker_page( string $hook_suffix ): bool {
		return str_contains( $hook_suffix, 'allyworker' );
	}

	/**
	 * Returns the AllyWorker page ID for the given hook suffix.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix The current admin page hook suffix.
	 * @return string The page identifier, e.g. 'configuration', 'skills'.
	 */
	private function current_page_id( string $hook_suffix ): string {
		$map = [
			'toplevel_page_allyworker'                    => 'configuration',
			'allyworker_page_allyworker-abilities'           => 'abilities-settings',
			'allyworker_page_allyworker-skills'              => 'skills',
			'allyworker_page_allyworker-sandbox'             => 'sandbox',
			'allyworker_page_allyworker-block-editor-queue'  => 'block-editor',
			'allyworker_page_allyworker-get-pro'             => 'get-pro',
		];
		return $map[ $hook_suffix ] ?? 'configuration';
	}

	/**
	 * Returns the base64-encoded SVG icon for the admin menu.
	 *
	 * Uses currentColor so WordPress admin CSS controls hover/active/focus
	 * states automatically.
	 *
	 * @since  1.0.0
	 * @return string Base64-encoded data URI for the SVG icon.
	 */
	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
			. '<polygon points="50,5 90,28 90,72 50,95 10,72 10,28" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>'
			. '<path d="M32,33 L44,47 L32,57" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<line x1="52" y1="57" x2="68" y2="57" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>'
			. '<line x1="32" y1="68" x2="62" y2="68" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	// -------------------------------------------------------------------------
	// Inline styles — must be in <head>, not an enqueued stylesheet.
	// The admin bar and admin menu are rendered outside plugin-scoped CSS,
	// so attribute selectors like #wpadminbar and #adminmenu need to be
	// injected directly into admin_head to take effect reliably.
	// -------------------------------------------------------------------------

	/**
	 * Outputs inline CSS for the admin menu accent colour and admin bar indicator.
	 *
	 * @since 1.0.0
	 */
	public function inline_menu_styles(): void {
		?>
		<style>
			/* ── Get Pro accent colour ── */
			#adminmenu a[href$="allyworker-get-pro"] {
				color: #f5a623 !important;
				font-weight: 600;
			}
			#adminmenu a[href$="allyworker-get-pro"]:hover {
				color: #f5a623 !important;
				text-decoration: underline;
			}

			/* ── Admin bar "AllyWorker ON" indicator ── */
			#wpadminbar #wp-admin-bar-allyworker-indicator > .ab-item {
				background: #d63638 !important;
				color: #fff !important;
				font-weight: 600;
			}
			#wpadminbar #wp-admin-bar-allyworker-indicator > .ab-item:hover {
				background: #b32d2e !important;
			}
			.allyworker-ab-dot {
				display: inline-block;
				width: 8px;
				height: 8px;
				background: #fff;
				border-radius: 50%;
				margin-right: 6px;
				vertical-align: middle;
				animation: allyworker-pulse 2s infinite;
			}
			@keyframes allyworker-pulse {
				0%, 100% { opacity: 1; }
				50%       { opacity: .4; }
			}
		</style>
		<?php
	}

}

<?php
/**
 * Connect Page — Application Password setup + MCP config generator.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

/**
 * Class ConnectPage
 */
class ConnectPage {

	public const PAGE_SLUG = 'wpcodex-connect';

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
	}

	public function add_submenu(): void {
		add_submenu_page(
			'wpcodex',
			__( 'Connect', 'wpcodex' ),
			__( 'Connect', 'wpcodex' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$mcp_url = rest_url( 'mcp/wpcodex' );
		$prompt  = $this->build_prompt( $mcp_url );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connect Your AI Client', 'wpcodex' ); ?></h1>

			<?php if ( ! is_ssl() ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'Warning: Your site is not using HTTPS. Application Password credentials will be transmitted in plain text. Enable SSL before using WPCodex.', 'wpcodex' ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Step 1: Create an Application Password', 'wpcodex' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: link to Application Passwords */
					esc_html__( 'Go to %s, scroll to Application Passwords, enter a name (e.g. "Claude Code"), and click Add New Application Password.', 'wpcodex' ),
					'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">'
					. esc_html__( 'your profile', 'wpcodex' )
					. '</a>'
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Step 2: Copy the setup prompt', 'wpcodex' ); ?></h2>
			<p><?php esc_html_e( 'Paste this prompt into your AI client. The agent will write the MCP configuration for you.', 'wpcodex' ); ?></p>

			<textarea
				id="wpcodex-connect-prompt"
				class="large-text code"
				rows="10"
				readonly
				aria-label="<?php esc_attr_e( 'MCP setup prompt', 'wpcodex' ); ?>"
			><?php echo esc_textarea( $prompt ); ?></textarea>

			<button type="button" class="button button-primary" id="wpcodex-copy-prompt" data-target="wpcodex-connect-prompt">
				<?php esc_html_e( 'Copy Prompt', 'wpcodex' ); ?>
			</button>

			<hr>

			<h2><?php esc_html_e( 'MCP Details', 'wpcodex' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'MCP Endpoint', 'wpcodex' ); ?></th>
					<td><code><?php echo esc_html( $mcp_url ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Authentication', 'wpcodex' ); ?></th>
					<td><?php esc_html_e( 'WordPress Application Password (username + generated password)', 'wpcodex' ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function build_prompt( string $mcp_url ): string {
		return sprintf(
			__(
				"Configure WPCodex as an MCP server using these details:\n\nMCP URL: %s\nAuthentication: WordPress Application Password\n  Username: %s\n  Password: [the Application Password you just created]\n\nAdd this server to your AI client's MCP config, reload the session, then call wpcodex/site-info to confirm the connection.",
				'wpcodex'
			),
			$mcp_url,
			wp_get_current_user()->user_login
		);
	}
}
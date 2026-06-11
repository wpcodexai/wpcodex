<?php
/**
 * Figma Integration page.
 *
 * Primary section: Official Figma MCP (https://mcp.figma.com/mcp) — shows
 * per-client JSON config snippets, no WordPress backend needed.
 *
 * Secondary section: WPCodex Figma Abilities — PAT-based token stored in WP,
 * bundles figma-get-file / figma-get-node / figma-get-images inside the
 * wpcodex MCP connection.
 *
 * @package WPCodex\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

use WPCodex\Runner\FigmaClient;

/**
 * Class FigmaConnectPage
 */
final class FigmaConnectPage {

	/** Official Figma remote MCP endpoint. */
	private const FIGMA_MCP_URL = 'https://mcp.figma.com/mcp';

	/** AJAX action names (PAT section). */
	private const AJAX_CONNECT    = 'wpcodex_figma_connect';
	private const AJAX_DISCONNECT = 'wpcodex_figma_disconnect';
	private const AJAX_TOGGLE     = 'wpcodex_figma_toggle';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_CONNECT,    [ $this, 'ajax_connect' ] );
		add_action( 'wp_ajax_' . self::AJAX_DISCONNECT, [ $this, 'ajax_disconnect' ] );
		add_action( 'wp_ajax_' . self::AJAX_TOGGLE,     [ $this, 'ajax_toggle' ] );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$pat_enabled   = (bool) get_option( FigmaClient::OPTION_ENABLED, false );
		$pat_connected = FigmaClient::is_connected();
		$user_data     = $pat_connected ? (array) get_option( FigmaClient::OPTION_USER, [] ) : [];
		$user_name     = is_string( $user_data['handle'] ?? null ) ? $user_data['handle'] : '';
		$user_email    = is_string( $user_data['email'] ?? null ) ? $user_data['email'] : '';

		$clients = self::get_client_configs();
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-integrations">
			<h1 class="wpcodex-page-title"><?php esc_html_e( 'Integrations', 'wpcodex' ); ?></h1>

			<!-- ══════════════════════════════════════════════════════════════
			     SECTION 1 — Official Figma MCP
			     ══════════════════════════════════════════════════════════════ -->
			<div class="wpcodex-integration-card" id="wpcodex-figma-official-card">

				<div class="wpcodex-integration-card__header">
					<div class="wpcodex-integration-card__icon">
						<?php echo self::figma_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded ?>
					</div>
					<div class="wpcodex-integration-card__info">
						<h2 class="wpcodex-integration-card__title">
							<?php esc_html_e( 'Figma MCP', 'wpcodex' ); ?>
							<span class="wpcodex-badge wpcodex-badge--official"><?php esc_html_e( 'Official', 'wpcodex' ); ?></span>
						</h2>
						<p class="wpcodex-integration-card__desc">
							<?php esc_html_e( "Figma's official MCP server. Add it to your AI client once — the first connection opens an OAuth login in your browser and handles authentication automatically.", 'wpcodex' ); ?>
						</p>
					</div>
				</div>

				<div class="wpcodex-integration-card__body">
					<div class="wpcodex-integration-card__divider"></div>

					<!-- MCP URL row -->
					<div class="wpcodex-figma-url-row">
						<span class="wpcodex-figma-url-label"><?php esc_html_e( 'MCP server URL', 'wpcodex' ); ?></span>
						<code class="wpcodex-figma-url-value" id="wpcodex-figma-mcp-url"><?php echo esc_html( self::FIGMA_MCP_URL ); ?></code>
						<button type="button" class="button button-secondary wpcodex-figma-copy-url-btn" data-copy="<?php echo esc_attr( self::FIGMA_MCP_URL ); ?>">
							<?php esc_html_e( 'Copy URL', 'wpcodex' ); ?>
						</button>
					</div>

					<!-- Per-client JSON config tabs -->
					<div style="margin-top:20px;">
						<p class="wpcodex-integration-card__desc" style="margin-bottom:10px;">
							<?php esc_html_e( 'Or copy the ready-made config snippet for your AI client:', 'wpcodex' ); ?>
						</p>

						<div class="wpcodex-client-tabs" id="wpcodex-figma-client-tabs">
							<?php
							$first = true;
							foreach ( $clients as $slug => $client ) :
								?>
								<button type="button"
								        class="wpcodex-client-tab<?php echo $first ? ' is-active' : ''; ?>"
								        data-client="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $client['label'] ); ?>
								</button>
								<?php $first = false; ?>
							<?php endforeach; ?>
						</div>

						<div class="wpcodex-config-block wpcodex-figma-config-block">
							<pre id="wpcodex-figma-config-code"></pre>
							<button type="button" class="button wpcodex-copy-code-btn" id="wpcodex-figma-copy-config">
								<?php esc_html_e( 'Copy', 'wpcodex' ); ?>
							</button>
						</div>

						<div class="wpcodex-config-footer">
							<div id="wpcodex-figma-config-hint" class="wpcodex-config-hint"></div>
							<div id="wpcodex-figma-config-paths" class="wpcodex-config-paths" style="display:none;"></div>
						</div>
					</div>

					<!-- OAuth note -->
					<div class="notice notice-info inline" style="margin:16px 0 0;">
						<p style="margin:0;">
							<strong><?php esc_html_e( 'First use:', 'wpcodex' ); ?></strong>
							<?php esc_html_e( 'When your AI client first calls a Figma tool, your browser will open a Figma OAuth login page. Approve it once and the token is stored automatically — no manual key management needed.', 'wpcodex' ); ?>
						</p>
					</div>
				</div>
			</div><!-- .wpcodex-integration-card (official) -->

			<!-- ══════════════════════════════════════════════════════════════
			     SECTION 2 — WPCodex Figma Abilities (PAT, bundled)
			     ══════════════════════════════════════════════════════════════ -->
			<div class="wpcodex-integration-card <?php echo $pat_enabled ? 'is-enabled' : ''; ?>" id="wpcodex-figma-card">

				<div class="wpcodex-integration-card__header">
					<div class="wpcodex-integration-card__icon wpcodex-integration-card__icon--sm">
						<?php echo self::wpcodex_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded ?>
					</div>
					<div class="wpcodex-integration-card__info">
						<h2 class="wpcodex-integration-card__title">
							<?php esc_html_e( 'WPCodex Figma Abilities', 'wpcodex' ); ?>
							<span class="wpcodex-badge"><?php esc_html_e( 'Optional', 'wpcodex' ); ?></span>
						</h2>
						<p class="wpcodex-integration-card__desc">
							<?php esc_html_e( 'Exposes figma-get-file, figma-get-node, and figma-get-images directly inside your wpcodex MCP connection — useful when you want the agent to fetch a design and build it in WordPress in a single session without switching MCP servers.', 'wpcodex' ); ?>
						</p>
					</div>
					<div class="wpcodex-integration-card__toggle-wrap">
						<label class="wpcodex-toggle" aria-label="<?php esc_attr_e( 'Enable WPCodex Figma abilities', 'wpcodex' ); ?>">
							<input
								type="checkbox"
								id="wpcodex-figma-enabled"
								<?php checked( $pat_enabled ); ?>
							>
							<span class="wpcodex-toggle__track"></span>
							<span class="wpcodex-toggle__label">
								<?php echo $pat_enabled ? esc_html__( 'Enabled', 'wpcodex' ) : esc_html__( 'Disabled', 'wpcodex' ); ?>
							</span>
						</label>
					</div>
				</div>

				<div class="wpcodex-integration-card__body" <?php echo ! $pat_enabled ? 'style="display:none;"' : ''; ?> id="wpcodex-figma-body">
					<div class="wpcodex-integration-card__divider"></div>

					<!-- PAT connection status -->
					<?php if ( $pat_connected ) : ?>
						<div class="wpcodex-figma-status wpcodex-figma-status--connected" id="wpcodex-figma-status">
							<span class="wpcodex-figma-status__dot"></span>
							<div class="wpcodex-figma-status__text">
								<strong><?php esc_html_e( 'Connected', 'wpcodex' ); ?></strong>
								<?php if ( '' !== $user_name ) : ?>
									&mdash; <?php echo esc_html( $user_name ); ?>
								<?php endif; ?>
								<?php if ( '' !== $user_email ) : ?>
									<span class="wpcodex-figma-status__email">(<?php echo esc_html( $user_email ); ?>)</span>
								<?php endif; ?>
							</div>
							<button type="button" class="button button-secondary" id="wpcodex-figma-disconnect">
								<?php esc_html_e( 'Disconnect', 'wpcodex' ); ?>
							</button>
						</div>
					<?php else : ?>
						<div class="wpcodex-figma-status wpcodex-figma-status--disconnected" id="wpcodex-figma-status">
							<span class="wpcodex-figma-status__dot"></span>
							<div class="wpcodex-figma-status__text">
								<?php esc_html_e( 'Not connected — Personal Access Token required.', 'wpcodex' ); ?>
							</div>
							<button type="button" class="button button-primary" id="wpcodex-figma-connect-btn">
								<?php esc_html_e( 'Connect via Token', 'wpcodex' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<p class="description" style="margin-top:10px;">
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: link to Figma settings */
								__( 'Generate a token at <a href="%s" target="_blank" rel="noopener noreferrer">figma.com/settings</a> → Personal access tokens.', 'wpcodex' )
							),
							'https://www.figma.com/settings'
						);
						?>
					</p>
				</div>
			</div><!-- .wpcodex-integration-card (pat) -->
		</div><!-- .wpcodex-wrap -->

		<!-- ══════════════════════════════════════════════════════════════
		     PAT Connect Modal
		     ══════════════════════════════════════════════════════════════ -->
		<div id="wpcodex-figma-modal" class="wpcodex-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wpcodex-figma-modal-title">
			<div class="wpcodex-modal__backdrop" id="wpcodex-figma-modal-backdrop"></div>
			<div class="wpcodex-modal__dialog">
				<div class="wpcodex-modal__header">
					<h2 class="wpcodex-modal__title" id="wpcodex-figma-modal-title">
						<?php esc_html_e( 'Connect Figma via Personal Access Token', 'wpcodex' ); ?>
					</h2>
					<button type="button" class="wpcodex-modal__close" id="wpcodex-figma-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wpcodex' ); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="wpcodex-modal__body">
					<ol class="wpcodex-modal__steps">
						<li>
							<?php
							printf(
								wp_kses_post(
									/* translators: %s: link to Figma settings */
									__( 'Open <a href="%s" target="_blank" rel="noopener noreferrer">figma.com/settings</a>.', 'wpcodex' )
								),
								'https://www.figma.com/settings'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Scroll to "Personal access tokens" and click "Generate new token".', 'wpcodex' ); ?></li>
						<li><?php esc_html_e( 'Give it a name (e.g. "WPCodex"), set expiry if desired, then copy the token.', 'wpcodex' ); ?></li>
						<li><?php esc_html_e( 'Paste it below and click "Verify & Save".', 'wpcodex' ); ?></li>
					</ol>

					<div style="margin-top:16px;">
						<label for="wpcodex-figma-token-input" style="display:block; margin-bottom:6px; font-weight:600;">
							<?php esc_html_e( 'Personal Access Token', 'wpcodex' ); ?>
							<span aria-hidden="true" style="color:#d63638; margin-left:2px;">*</span>
						</label>
						<input
							type="password"
							id="wpcodex-figma-token-input"
							class="regular-text"
							placeholder="figd_..."
							autocomplete="off"
							style="width:100%;"
						>
					</div>

					<div id="wpcodex-figma-modal-error" class="notice notice-error inline" style="display:none; margin:12px 0 0;">
						<p id="wpcodex-figma-modal-error-text" style="margin:0;"></p>
					</div>
				</div>
				<div class="wpcodex-modal__footer">
					<button type="button" class="button button-secondary" id="wpcodex-figma-modal-cancel">
						<?php esc_html_e( 'Cancel', 'wpcodex' ); ?>
					</button>
					<button type="button" class="button button-primary" id="wpcodex-figma-modal-save">
						<?php esc_html_e( 'Verify & Save', 'wpcodex' ); ?>
					</button>
					<span class="spinner" id="wpcodex-figma-modal-spinner" style="float:none; margin:0 0 0 4px; display:none;"></span>
				</div>
			</div>
		</div>

		<?php
		wp_add_inline_script(
			'wpcodex-admin',
			'window.wpcodexFigma = ' . wp_json_encode( [
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'connectNonce'     => wp_create_nonce( self::AJAX_CONNECT ),
				'disconnectNonce'  => wp_create_nonce( self::AJAX_DISCONNECT ),
				'toggleNonce'      => wp_create_nonce( self::AJAX_TOGGLE ),
				'ajaxConnect'      => self::AJAX_CONNECT,
				'ajaxDisconnect'   => self::AJAX_DISCONNECT,
				'ajaxToggle'       => self::AJAX_TOGGLE,
				'isConnected'      => $pat_connected,
				'userName'         => $user_name,
				'userEmail'        => $user_email,
				'clients'          => $clients,
				'l10n'             => [
					'connected'         => __( 'Connected', 'wpcodex' ),
					'notConnected'      => __( 'Not connected — Personal Access Token required.', 'wpcodex' ),
					'connect'           => __( 'Connect via Token', 'wpcodex' ),
					'disconnect'        => __( 'Disconnect', 'wpcodex' ),
					'enabled'           => __( 'Enabled', 'wpcodex' ),
					'disabled'          => __( 'Disabled', 'wpcodex' ),
					'verifying'         => __( 'Verifying…', 'wpcodex' ),
					'tokenRequired'     => __( 'Please enter your Personal Access Token.', 'wpcodex' ),
					'disconnectConfirm' => __( 'Disconnect Figma? The WPCodex Figma abilities will stop being available to AI agents.', 'wpcodex' ),
					'error'             => __( 'Something went wrong. Please try again.', 'wpcodex' ),
					'copied'            => __( 'Copied!', 'wpcodex' ),
					'copy'              => __( 'Copy', 'wpcodex' ),
					'copyUrl'           => __( 'Copy URL', 'wpcodex' ),
				],
			] ) . ';',
			'before'
		);
	}

	// -------------------------------------------------------------------------
	// AJAX: Toggle PAT abilities enabled/disabled
	// -------------------------------------------------------------------------

	public function ajax_toggle(): void {
		check_ajax_referer( self::AJAX_TOGGLE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$enabled = filter_var( $_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
		update_option( FigmaClient::OPTION_ENABLED, $enabled, false );

		wp_send_json_success( [ 'enabled' => $enabled ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Connect (verify token + save)
	// -------------------------------------------------------------------------

	public function ajax_connect(): void {
		check_ajax_referer( self::AJAX_CONNECT, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token ) {
			wp_send_json_error( __( 'Token is required.', 'wpcodex' ) );
		}

		$result = FigmaClient::instance()->verify_token( $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		update_option( FigmaClient::OPTION_TOKEN, $token, false );
		update_option( FigmaClient::OPTION_ENABLED, true, false );

		$user_info = [
			'handle' => is_string( $result['handle'] ?? null ) ? $result['handle'] : '',
			'email'  => is_string( $result['email'] ?? null ) ? $result['email'] : '',
			'id'     => is_string( $result['id'] ?? null ) ? $result['id'] : '',
		];
		update_option( FigmaClient::OPTION_USER, $user_info, false );

		wp_send_json_success( [
			'handle' => $user_info['handle'],
			'email'  => $user_info['email'],
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Disconnect
	// -------------------------------------------------------------------------

	public function ajax_disconnect(): void {
		check_ajax_referer( self::AJAX_DISCONNECT, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wpcodex' ) );
		}

		delete_option( FigmaClient::OPTION_TOKEN );
		delete_option( FigmaClient::OPTION_USER );

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Client config snippets
	// -------------------------------------------------------------------------

	/**
	 * Returns per-client JSON config arrays for the official Figma MCP.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_client_configs(): array {
		$url = self::FIGMA_MCP_URL;

		$standard_json = wp_json_encode(
			[
				'mcpServers' => [
					'figma' => [
						'type' => 'http',
						'url'  => $url,
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		$vscode_json = wp_json_encode(
			[
				'servers' => [
					'figma' => [
						'type' => 'http',
						'url'  => $url,
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		$windsurf_json = wp_json_encode(
			[
				'mcpServers' => [
					'figma' => [
						'serverUrl' => $url,
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		return [
			'claude-code'    => [
				'label'   => 'Claude Code',
				'json'    => (string) $standard_json,
				'hint'    => __( 'Add to your MCP config, then run: /mcp', 'wpcodex' ),
				'paths'   => [ '~/.claude/settings.json', '.claude/settings.json (project)' ],
			],
			'claude-desktop' => [
				'label'   => 'Claude Desktop',
				'json'    => (string) $standard_json,
				'hint'    => __( 'Paste into your Claude Desktop config file, then restart the app.', 'wpcodex' ),
				'paths'   => [
					'macOS: ~/Library/Application Support/Claude/claude_desktop_config.json',
					'Windows: %APPDATA%\\Claude\\claude_desktop_config.json',
				],
			],
			'cursor'         => [
				'label'   => 'Cursor',
				'json'    => (string) $standard_json,
				'hint'    => __( 'Add to your Cursor MCP config, then reload the window.', 'wpcodex' ),
				'paths'   => [ '~/.cursor/mcp.json', '.cursor/mcp.json (project)' ],
			],
			'vscode'         => [
				'label'   => 'VS Code',
				'json'    => (string) $vscode_json,
				'hint'    => __( 'Add to your VS Code MCP config (requires GitHub Copilot extension).', 'wpcodex' ),
				'paths'   => [ '.vscode/mcp.json (project)', '~/.vscode/mcp.json (global)' ],
			],
			'windsurf'       => [
				'label'   => 'Windsurf',
				'json'    => (string) $windsurf_json,
				'hint'    => __( 'Add to your Windsurf MCP config, then restart Windsurf.', 'wpcodex' ),
				'paths'   => [ '~/.codeium/windsurf/mcp_config.json' ],
			],
			'codex'          => [
				'label'   => 'Codex',
				'json'    => (string) $standard_json,
				'hint'    => __( 'Add to your Codex MCP config file.', 'wpcodex' ),
				'paths'   => [ '~/.codex/config.json' ],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Icons
	// -------------------------------------------------------------------------

	private static function figma_icon(): string {
		return '<svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
			. '<rect width="38" height="38" rx="8" fill="#1E1E1E"/>'
			. '<path d="M14.5 28C16.433 28 18 26.433 18 24.5V21H14.5C12.567 21 11 22.567 11 24.5C11 26.433 12.567 28 14.5 28Z" fill="#0ACF83"/>'
			. '<path d="M11 19C11 17.067 12.567 15.5 14.5 15.5H18V22.5H14.5C12.567 22.5 11 20.933 11 19Z" fill="#A259FF"/>'
			. '<path d="M11 13.5C11 11.567 12.567 10 14.5 10H18V17H14.5C12.567 17 11 15.433 11 13.5Z" fill="#F24E1E"/>'
			. '<path d="M18 10H21.5C23.433 10 25 11.567 25 13.5C25 15.433 23.433 17 21.5 17H18V10Z" fill="#FF7262"/>'
			. '<path d="M25 19C25 20.933 23.433 22.5 21.5 22.5C19.567 22.5 18 20.933 18 19C18 17.067 19.567 15.5 21.5 15.5C23.433 15.5 25 17.067 25 19Z" fill="#1ABCFE"/>'
			. '</svg>';
	}

	private static function wpcodex_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="32" height="32" aria-hidden="true" focusable="false">'
			. '<polygon points="50,5 90,28 90,72 50,95 10,72 10,28" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>'
			. '<path d="M32,33 L44,47 L32,57" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<line x1="52" y1="57" x2="68" y2="57" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>'
			. '</svg>';
		return $svg;
	}
}

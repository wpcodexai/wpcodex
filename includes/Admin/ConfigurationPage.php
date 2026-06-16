<?php
/**
 * Configuration page — main landing page of the WPWorker admin.
 *
 * Step 1 — Enable AI Abilities (on/off toggle + security warning)
 * Step 2 — Application Password (generate inline, list existing, revoke)
 * Step 3 — Connect Your AI Client (paste prompt + per-client JSON snippets)
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Admin;

/**
 * Class ConfigurationPage
 */
final class ConfigurationPage {

	/** AJAX action names. */
	private const AJAX_GENERATE  = 'wpworker_generate_app_password';
	private const AJAX_REVOKE    = 'wpworker_revoke_app_password';

	/** Application name used when auto-creating passwords. */
	private const APP_NAME_DEFAULT = 'Worker AI';

	/**
	 * Register AJAX handlers for the Configuration page.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_GENERATE, [ $this, 'ajax_generate_password' ] );
		add_action( 'wp_ajax_' . self::AJAX_REVOKE,   [ $this, 'ajax_revoke_password' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'worker-ai' ) );
		}

		self::handle_toggle();

		$enabled   = AdminMenu::are_abilities_enabled();
		$mcp_url   = rest_url( 'mcp/wpworker' );
		$user      = wp_get_current_user();
		$username  = $user->user_login;
		$app_pw_ok = self::application_passwords_available();
		$site_slug = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?? 'site' );
		$default_name = 'wpworker-' . $site_slug;

		// Existing application passwords for this user (WPWorker-generated ones).
		$existing_passwords = self::get_existing_passwords( $user->ID );
		?>
		<div class="wrap wpworker-wrap wpworker-config" id="wpworker-configuration">
			<h1 class="wpworker-page-title"><?php esc_html_e( 'Configuration', 'worker-ai' ); ?></h1>

			<!-- ══════════════════════════════════════════════════════════════
			     STEP 1 — Enable AI Abilities
			     ══════════════════════════════════════════════════════════════ -->
			<div class="wpworker-config__card <?php echo $enabled ? 'is-active' : ''; ?>">
				<div class="wpworker-config__card-header">
					<span class="wpworker-config__step">1</span>
					<h2 class="wpworker-config__card-title"><?php esc_html_e( 'Enable AI Abilities', 'worker-ai' ); ?></h2>
					<span class="wpworker-config__status <?php echo $enabled ? 'is-on' : 'is-off'; ?>">
						<?php echo $enabled ? esc_html__( 'ON', 'worker-ai' ) : esc_html__( 'OFF', 'worker-ai' ); ?>
					</span>
				</div>

				<p class="wpworker-config__card-desc">
					<?php esc_html_e( 'Activates the MCP server and registers all Worker AI abilities so AI agents can connect to this site.', 'worker-ai' ); ?>
				</p>

				<div class="wpworker-config__security-note">
					<strong><?php esc_html_e( 'Security note:', 'worker-ai' ); ?></strong>
					<?php esc_html_e( 'When enabled, AI agents can execute PHP code and perform filesystem operations on this site. For development and staging environments only. Always keep backups.', 'worker-ai' ); ?>
				</div>

				<form method="post" action="" style="margin-top:16px;">
					<?php wp_nonce_field( 'wpworker_toggle_abilities', 'wpworker_toggle_nonce' ); ?>
					<input type="hidden" name="wpworker_abilities_toggle" value="1">
					<input type="hidden" name="wpworker_abilities_value" value="<?php echo $enabled ? '0' : '1'; ?>">
					<button type="submit" class="button <?php echo $enabled ? 'button-secondary wpworker-config__btn-disable' : 'button-primary'; ?>">
						<?php echo $enabled
							? esc_html__( 'Disable AI Abilities', 'worker-ai' )
							: esc_html__( 'Enable AI Abilities', 'worker-ai' ); ?>
					</button>
				</form>
			</div>

			<!-- ══════════════════════════════════════════════════════════════
			     STEP 2 — Application Password
			     ══════════════════════════════════════════════════════════════ -->
			<div class="wpworker-config__card"
			     id="wpworker-step-2"
			     data-step="2"
			     <?php echo ! $enabled ? 'style="display:none;"' : ''; ?>>
				<div class="wpworker-config__card-header">
					<span class="wpworker-config__step">2</span>
					<h2 class="wpworker-config__card-title"><?php esc_html_e( 'Create an Application Password', 'worker-ai' ); ?></h2>
				</div>

				<p class="wpworker-config__card-desc">
					<?php esc_html_e( 'Generate an application password that your AI client will use to authenticate with WordPress. The password is embedded into the connection text in step 3.', 'worker-ai' ); ?>
				</p>

				<?php if ( ! $app_pw_ok ) : ?>
					<div class="notice notice-error inline" style="margin:0 0 16px;">
					<p>
						<strong><?php esc_html_e( 'HTTPS required.', 'worker-ai' ); ?></strong>
						<?php esc_html_e( 'Application Passwords transmit credentials over the network. Enable SSL before connecting an AI client.', 'worker-ai' ); ?>
						<?php esc_html_e( 'For local development, add to wp-config.php:', 'worker-ai' ); ?>
						<code>define( 'WP_ENVIRONMENT_TYPE', 'local' );</code>
					</p>
				</div>
				<?php else : ?>

					<!-- Generated password reveal (hidden until AJAX response) -->
					<div id="wpworker-pw-reveal" style="display:none; margin-bottom:16px;">
						<div class="notice notice-success inline" style="margin:0 0 10px;">
							<p style="margin:0;">
								<?php esc_html_e( 'Application password generated. It is now embedded in the connection text in step 3. Save it somewhere safe: it will not be shown in full again.', 'worker-ai' ); ?>
							</p>
						</div>
						<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
							<code id="wpworker-pw-value" style="font-size:.875rem; padding:5px 10px; background:#f0f0f0; border-radius:3px;"></code>
							<button type="button" class="button" onclick="wpworkerCopyPassword(this)">
								<?php esc_html_e( 'Copy password only', 'worker-ai' ); ?>
							</button>
						</div>
					</div>

					<!-- Generate form -->
					<div id="wpworker-pw-generate-form">
						<?php $has_passwords = ! empty( $existing_passwords ); ?>
						<div id="wpworker-pw-name-wrap" style="margin-bottom:12px;<?php echo $has_passwords ? '' : ' display:none;'; ?>">
							<label for="wpworker-pw-name" style="display:block; margin-bottom:4px; font-weight:600;">
								<?php esc_html_e( 'Name', 'worker-ai' ); ?>
								<span style="color:#d63638; margin-left:2px;" aria-hidden="true">*</span>
							</label>
							<input
								type="text"
								id="wpworker-pw-name"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. Cursor on laptop, Claude Desktop', 'worker-ai' ); ?>"
								maxlength="70"
							>
							<p class="description" style="margin-top:4px;">
								<?php esc_html_e( 'A unique label for this credential — one per AI client.', 'worker-ai' ); ?>
							</p>
						</div>
						<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
							<button type="button" class="button button-primary" id="wpworker-pw-generate-btn" onclick="wpworkerGeneratePassword(this)">
								<?php esc_html_e( 'Generate application password', 'worker-ai' ); ?>
							</button>
							<span id="wpworker-pw-spinner" class="spinner" style="float:none; margin:0; display:none;"></span>
						</div>
					</div>

					<!-- Divider -->
					<hr style="margin:20px 0; border:none; border-top:1px solid #dcdcde;">

					<!-- Go to Application Passwords -->
					<div>
						<p class="wpworker-config__card-desc" style="margin-bottom:10px;">
							<?php
							printf(
								wp_kses_post(
									/* translators: %s: link to the Application Passwords section of the user profile */
									__( 'Prefer to manage passwords manually? Go to %s, enter a name like <strong>"Claude Code"</strong>, and click <strong>Add New Application Password</strong>. Copy the generated password — it is shown only once.', 'worker-ai' )
								),
								'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '" target="_blank">'
								. esc_html__( 'your profile → Application Passwords', 'worker-ai' )
								. '</a>'
							);
							?>
						</p>
						<p class="wpworker-config__card-desc" style="margin-bottom:12px;">
							<?php esc_html_e( 'Create one password per AI client so you can revoke access individually.', 'worker-ai' ); ?>
						</p>
						<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"
						   class="button button-primary" target="_blank">
							<?php esc_html_e( 'Go to Application Passwords ↗', 'worker-ai' ); ?>
						</a>
					</div>

					<!-- Existing passwords table — only WPWorker-prefixed, shown at the bottom -->
					<div id="wpworker-pw-existing" style="<?php echo empty( $existing_passwords ) ? 'display:none;' : ''; ?>margin-top:20px; padding-top:20px; border-top:1px solid #dcdcde;">
						<p class="wpworker-pw-section-label">
							<?php esc_html_e( 'Manage existing application passwords', 'worker-ai' ); ?>
							&nbsp;<span id="wpworker-pw-count">(<?php echo esc_html( (string) count( $existing_passwords ) ); ?>)</span>
						</p>
						<div class="wpworker-pw-table-wrap">
							<table class="wpworker-pw-table" id="wpworker-pw-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Name', 'worker-ai' ); ?></th>
										<th><?php esc_html_e( 'Created', 'worker-ai' ); ?></th>
										<th><?php esc_html_e( 'Last Used', 'worker-ai' ); ?></th>
										<th></th>
									</tr>
								</thead>
								<tbody id="wpworker-pw-tbody">
									<?php foreach ( $existing_passwords as $pw ) : ?>
										<tr data-uuid="<?php echo esc_attr( $pw['uuid'] ); ?>">
											<td class="wpworker-pw-table__name"><?php echo esc_html( $pw['display_name'] ); ?></td>
											<td class="wpworker-pw-table__meta"><?php echo esc_html( $pw['created'] ); ?></td>
											<td class="wpworker-pw-table__meta"><?php echo esc_html( $pw['last_used'] ); ?></td>
											<td class="wpworker-pw-table__actions">
												<button type="button" class="button button-small wpworker-pw-revoke-btn"
												        onclick="wpworkerRevokePassword('<?php echo esc_js( $pw['uuid'] ); ?>', this)">
													<?php esc_html_e( 'Revoke', 'worker-ai' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

				<?php endif; ?>
			</div>

			<!-- ══════════════════════════════════════════════════════════════
			     STEP 3 — Connect Your AI Client
			     ══════════════════════════════════════════════════════════════ -->
			<?php $step3_hidden = true; // Only revealed via JS after a password is generated. ?>
			<div class="wpworker-config__card"
			     id="wpworker-step-3"
			     data-step="3"
			     <?php echo $step3_hidden ? 'style="display:none;"' : ''; ?>>
				<div class="wpworker-config__card-header">
					<span class="wpworker-config__step">3</span>
					<h2 class="wpworker-config__card-title"><?php esc_html_e( 'Connect Your AI Client', 'worker-ai' ); ?></h2>
				</div>

				<p style="margin:0 0 12px;">
					<?php esc_html_e( 'Copy the block below and paste it to your AI agent.', 'worker-ai' ); ?>
				</p>

				<div class="notice notice-info inline" style="margin:0 0 12px;">
					<p style="margin:0;">
						<strong><?php esc_html_e( 'This prompt shares your application password with your AI agent.', 'worker-ai' ); ?></strong>
						<?php
						printf(
							wp_kses(
								/* translators: %s: link */
								__( 'Prefer to keep it private? Use the <button type="button" class="button-link" onclick="wpworkerOpenManualConfig()">manual configuration</button> and paste the snippet into the config file yourself.', 'worker-ai' ),
								[ 'button' => [ 'type' => [], 'class' => [], 'onclick' => [] ] ]
							)
						);
						?>
					</p>
				</div>

				<!-- Paste block -->
				<div class="wpworker-paste-block">
					<div class="wpworker-paste-content is-expanded" id="wpworker-paste-content">
						<pre id="wpworker-paste-text"></pre>
					</div>
					<div class="wpworker-paste-actions">
						<button type="button" class="button-link" id="wpworker-paste-expand"
						        onclick="wpworkerToggleExpandPaste(this)" aria-expanded="true"
						        aria-controls="wpworker-paste-content">
							<?php esc_html_e( 'Show less', 'worker-ai' ); ?>
						</button>
						<button type="button" class="button button-primary" onclick="wpworkerCopyPaste(this)">
							<?php esc_html_e( 'Copy prompt', 'worker-ai' ); ?>
						</button>
						<p id="wpworker-paste-warning" style="display:none; margin:0; color:#d63638; font-size:13px; font-weight:600;">
							<?php esc_html_e( "Don't share with anyone: it contains an application password that grants access to this WordPress site.", 'worker-ai' ); ?>
						</p>
					</div>
				</div>

				<!-- Server name -->
				<p style="margin:14px 0 4px;">
					<button type="button" class="button-link" id="wpworker-name-toggle"
					        aria-expanded="false" aria-controls="wpworker-name-field"
					        onclick="wpworkerToggleServerName(this)">
						<?php esc_html_e( 'Change server name (optional)', 'worker-ai' ); ?>
					</button>
				</p>
				<div id="wpworker-name-field" style="display:none; margin:6px 0 14px;">
					<input type="text" id="wpworker-mcp-name"
					       value="<?php echo esc_attr( $default_name ); ?>"
					       placeholder="<?php echo esc_attr( $default_name ); ?>"
					       maxlength="25" style="width:240px;"
					       oninput="wpworkerUpdateName(this.value)">
					<p class="description" style="margin:6px 0 0;">
						<?php esc_html_e( 'Editing here updates the connection text and JSON snippets below in real time. Each AI client config keeps its own name once saved on its side.', 'worker-ai' ); ?>
					</p>
					<div id="wpworker-name-warning" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
						<p style="margin:0;"><?php esc_html_e( 'Maximum 25 characters reached. Required for client compatibility.', 'worker-ai' ); ?></p>
					</div>
					<div id="wpworker-name-suggestion" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
						<p style="margin:0;"><?php esc_html_e( 'Tip: keep "wpworker" in the name so you (and your AI agent) can tell this MCP server apart from others.', 'worker-ai' ); ?></p>
					</div>
				</div>

				<!-- Manual / per-client JSON config -->
				<p style="margin:6px 0 4px;">
					<button type="button" class="button-link" id="wpworker-manual-toggle"
					        aria-expanded="false" aria-controls="wpworker-manual-config"
					        onclick="wpworkerToggleManualConfig(this)">
						<?php esc_html_e( 'Need the JSON config for a specific client?', 'worker-ai' ); ?>
					</button>
				</p>
				<div id="wpworker-manual-config" style="display:none;">
					<p class="description" style="margin:0 0 12px;">
						<?php esc_html_e( 'Select your AI client to copy the JSON snippet for its config file.', 'worker-ai' ); ?>
					</p>

					<div class="notice notice-warning inline" style="margin:0 0 14px;">
						<p style="margin:0;">
							<strong><?php esc_html_e( 'Node.js 20.1 or higher is required.', 'worker-ai' ); ?></strong>
							<?php esc_html_e( 'These configs use', 'worker-ai' ); ?>
							<code>npx</code>
							<?php esc_html_e( 'to run the MCP transport. Run', 'worker-ai' ); ?>
							<code>node -v</code>
							<?php esc_html_e( 'in your terminal to check. If your version is below 20.1, download the latest LTS from', 'worker-ai' ); ?>
							<a href="https://nodejs.org/" target="_blank" rel="noopener noreferrer">nodejs.org</a>.
							<?php
							printf(
								wp_kses(
									/* translators: %s: button label */
									__( "Can't install Node.js? Use the <strong>npx-free alternative</strong> below instead.", 'worker-ai' ),
									[ 'strong' => [] ]
								)
							);
							?>
						</p>
					</div>

					<div class="wpworker-client-tabs" id="wpworker-manual-tabs">
						<?php
						$manual_clients = [
							'claude-code'    => 'Claude Code',
							'claude-desktop' => 'Claude Desktop',
							'codex'          => 'Codex',
							'antigravity'    => 'Antigravity',
							'cursor'         => 'Cursor',
							'vscode'         => 'VS Code',
							'github-copilot' => 'GitHub Copilot',
							'windsurf'       => 'Windsurf',
							'cline'          => 'Cline',
							'gemini-cli'     => 'Gemini CLI',
							'roo-code'       => 'Roo Code',
							'amazon-q'       => 'Amazon Q',
							'zed'            => 'Zed',
							'kilo-code'      => 'Kilo Code',
							'opencode'       => 'OpenCode',
						];
						$first = true;
						foreach ( $manual_clients as $slug => $label ) :
							?>
							<button type="button"
							        class="wpworker-client-tab<?php echo $first ? ' is-active' : ''; ?>"
							        data-client="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $label ); ?>
							</button>
							<?php $first = false; ?>
						<?php endforeach; ?>
					</div>
					<div class="wpworker-config-block">
						<pre id="wpworker-config-code"></pre>
						<button type="button" class="button wpworker-copy-code-btn" onclick="wpworkerCopyConfig(this)">
							<?php esc_html_e( 'Copy', 'worker-ai' ); ?>
						</button>
					</div>
					<div class="wpworker-config-footer">
						<div id="wpworker-config-hint" class="wpworker-config-hint"></div>
						<div id="wpworker-config-paths" class="wpworker-config-paths" style="display:none;"></div>
					</div>
				</div>

				<!-- npx-free alternative -->
				<p style="margin:6px 0 4px;">
					<button type="button" class="button-link" id="wpworker-npxless-toggle"
					        aria-expanded="false" aria-controls="wpworker-npxless-config"
					        onclick="wpworkerToggleNpxless(this)">
						<?php esc_html_e( 'Configs above not working? Try this npx-free alternative.', 'worker-ai' ); ?>
					</button>
				</p>
				<div id="wpworker-npxless-config" style="display:none;">
					<p class="description" style="margin:0 0 12px;">
						<?php esc_html_e( 'Copy this configuration snippet to connect using direct HTTP (no Node/npx required).', 'worker-ai' ); ?>
					</p>
					<div class="wpworker-client-tabs">
						<button type="button" class="wpworker-client-tab wpworker-npxless-tab is-active"
						        data-client="claude">
							<?php esc_html_e( 'Claude Code', 'worker-ai' ); ?>
						</button>
						<button type="button" class="wpworker-client-tab wpworker-npxless-tab"
						        data-client="codex">
							<?php esc_html_e( 'Codex', 'worker-ai' ); ?>
						</button>
					</div>
					<div class="wpworker-config-block">
						<pre id="wpworker-npxless-code"></pre>
						<button type="button" class="button wpworker-copy-code-btn" onclick="wpworkerCopyNpxless(this)">
							<?php esc_html_e( 'Copy', 'worker-ai' ); ?>
						</button>
					</div>
					<div class="wpworker-config-footer">
						<div id="wpworker-npxless-hint" class="wpworker-config-hint"></div>
						<div id="wpworker-npxless-paths" class="wpworker-config-paths"></div>
					</div>
				</div>
			</div>
		</div><!-- .wpworker-config -->

		<?php
		// Pass server-side data to the external configuration.js module.
		// All logic lives in src/admin/components/configuration.js — no inline JS here.
		wp_add_inline_script(
			'wpworker-admin',
			'window.wpworkerConfig = ' . wp_json_encode( [
				'mcpUrl'          => $mcp_url,
				'username'        => $username,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'       => wp_create_nonce( self::AJAX_GENERATE ),
				'revokeNonce'     => wp_create_nonce( self::AJAX_REVOKE ),
				'defaultName'     => $default_name,
				'pasteTemplate'   => self::get_paste_template( $mcp_url, $username ),
				'ajaxGenerate'    => self::AJAX_GENERATE,
				'ajaxRevoke'      => self::AJAX_REVOKE,
				'step2Complete'   => ! empty( $existing_passwords ),
				'l10n'            => [
					'copied'          => __( 'Copied!', 'worker-ai' ),
					'showLess'        => __( 'Show less', 'worker-ai' ),
					'showFull'        => __( 'Show full text', 'worker-ai' ),
					'generateAnother' => __( 'Generate another application password', 'worker-ai' ),
					'never'           => __( 'Never', 'worker-ai' ),
					'revoke'          => __( 'Revoke', 'worker-ai' ),
					'revokeConfirm'   => __( 'Revoke this application password? The AI client using it will lose access immediately.', 'worker-ai' ),
					'errorNameRequired' => __( 'Please enter a name for this password.', 'worker-ai' ),
					'errorGenerate'   => __( 'Error generating password.', 'worker-ai' ),
					'errorNetwork'    => __( 'Network error. Please try again.', 'worker-ai' ),
					'completeStep1'   => __( 'Complete step 1 first', 'worker-ai' ),
					'completeStep2'   => __( 'Complete step 2 first', 'worker-ai' ),
				],
			] ) . ';',
			'before'
		);
	}

	// Action handlers
	private static function handle_toggle(): void {
		if ( ! isset( $_POST['wpworker_abilities_toggle'] ) ) {
			return;
		}
		check_admin_referer( 'wpworker_toggle_abilities', 'wpworker_toggle_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( AdminMenu::ABILITIES_ENABLED_OPTION, '1' === wp_unslash( $_POST['wpworker_abilities_value'] ?? '0' ), false );
	}

	// AJAX handlers
	public function ajax_generate_password(): void {
		check_ajax_referer( self::AJAX_GENERATE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'worker-ai' ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( __( 'Application Passwords are not available.', 'worker-ai' ) );
		}

		$raw_name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// First password (no name provided) → stored as "WPWorker".
		// Subsequent passwords → stored as "WPWorker: {name}".
		if ( $raw_name === '' ) {
			$name = 'Worker AI';
		} else {
			$name = 'Worker AI: ' . $raw_name;
		}
		$user_id  = get_current_user_id();

		$result = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => $name ] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// $result[0] = plain-text password, $result[1] = array of app password data.
		$plain    = $result[0];
		$app_data = $result[1];

		wp_send_json_success( [
			'password' => \WP_Application_Passwords::chunk_password( $plain ),
			'uuid'     => $app_data['uuid'],
			'name'     => $app_data['name'],
			'created'  => wp_date( get_option( 'date_format' ) . ' g:i a', $app_data['created'] ),
		] );
	}

	public function ajax_revoke_password(): void {
		check_ajax_referer( self::AJAX_REVOKE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'worker-ai' ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( __( 'Application Passwords are not available.', 'worker-ai' ) );
		}

		$uuid    = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';
		$user_id = get_current_user_id();
		$result  = \WP_Application_Passwords::delete_application_password( $user_id, $uuid );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	// Helpers
	/**
	 * @return array<int, array<string, string>>
	 */
	private static function get_existing_passwords( int $user_id ): array {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return [];
		}

		$all = \WP_Application_Passwords::get_user_application_passwords( $user_id );
		if ( ! is_array( $all ) ) {
			return [];
		}

		$result = [];
		foreach ( $all as $pw ) {
			$pw_name = (string) ( $pw['name'] ?? '' );

			// Only show passwords created by WPWorker: exact "WPWorker" or "WPWorker: *".
			if ( $pw_name !== 'Worker AI' && stripos( $pw_name, 'Worker AI: ' ) !== 0 ) {
				continue;
			}

			$result[] = [
				'uuid'         => (string) ( $pw['uuid'] ?? '' ),
				'name'         => $pw_name,
				// Show the full stored name (prefix visible in the table).
				'display_name' => $pw_name,
				'created'      => $pw['created'] ? (string) wp_date( get_option( 'date_format' ) . ' g:i a', $pw['created'] ) : '—',
				'last_used'    => $pw['last_used'] ? (string) wp_date( get_option( 'date_format' ), $pw['last_used'] ) : __( 'Never', 'worker-ai' ),
			];
		}

		return $result;
	}

	private static function application_passwords_available(): bool {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return false;
		}
		return is_ssl() || in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}

	private static function get_paste_template( string $mcp_url = '', string $username = '' ): string {
		return "I want to add this WordPress site as an MCP server to this AI client.\n\n"
			. "Connection details:\n"
			. "- Server URL: " . $mcp_url . "\n"
			. "- Username: " . $username . "\n"
			. "- Application password: __WPWORKER_PW_SLOT__\n"
			. "- Server name to use in the config: __WPWORKER_MCP_NAME__\n"
			. "- Transport: @automattic/mcp-wordpress-remote via npx\n\n"
			. "Setup rules:\n"
			. "- Pass credentials ONLY as env vars: WP_API_URL, WP_API_USERNAME, WP_API_PASSWORD. Do NOT use CLI flags like --url or --password (the package ignores them).\n"
			. "- args array must be exactly [\"-y\", \"@automattic/mcp-wordpress-remote@latest\"].\n\n"
			. "Don't ask me to confirm choices already specified above. After writing the config, restart or reload the MCP session (most clients require it), then verify by listing the server's tools. If it fails, show me the stderr from the npx process before proposing changes.\n\n"
			. "If you cannot modify the config of this AI client from here, tell me to expand \"Need the JSON config for a specific client?\" on the Worker AI Configuration page and copy the snippet manually.";
	}
}

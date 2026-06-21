<?php
/**
 * Configuration page — main landing page of the AllyWorker admin.
 *
 * Step 1 — Enable AI Abilities (on/off toggle + security warning)
 * Step 2 — Application Password (generate inline, list existing, revoke)
 * Step 3 — Connect Your AI Client (paste prompt + per-client JSON snippets)
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Admin;

/**
 * Class ConfigurationPage
 */
final class ConfigurationPage {

	/** AJAX action names. */
	private const AJAX_GENERATE  = 'allyworker_generate_app_password';
	private const AJAX_REVOKE    = 'allyworker_revoke_app_password';

	/** Application name used when auto-creating passwords. */
	private const APP_NAME_DEFAULT = 'AllyWorker';

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
			wp_die( esc_html__( 'Insufficient permissions.', 'allyworker' ) );
		}

		self::handle_toggle();

		$enabled   = AdminMenu::are_abilities_enabled();
		$mcp_url   = rest_url( 'mcp/allyworker' );
		$user      = wp_get_current_user();
		$username  = $user->user_login;
		$app_pw_ok = self::application_passwords_available();
		$site_slug = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?? 'site' );
		$default_name = 'allyworker-' . $site_slug;

		// Existing application passwords for this user (AllyWorker-generated ones).
		$existing_passwords = self::get_existing_passwords( $user->ID );
		?>
		<div class="wrap allyworker-wrap allyworker-config" id="allyworker-configuration">
			<h1 class="allyworker-page-title"><?php esc_html_e( 'Configuration', 'allyworker' ); ?></h1>

			<!-- ══════════════════════════════════════════════════════════════
			     STEP 1 — Enable AI Abilities
			     ══════════════════════════════════════════════════════════════ -->
			<div class="allyworker-config__card <?php echo $enabled ? 'is-active' : ''; ?>">
				<div class="allyworker-config__card-header">
					<span class="allyworker-config__step">1</span>
					<h2 class="allyworker-config__card-title"><?php esc_html_e( 'Enable AI Abilities', 'allyworker' ); ?></h2>
					<span class="allyworker-config__status <?php echo $enabled ? 'is-on' : 'is-off'; ?>">
						<?php echo $enabled ? esc_html__( 'ON', 'allyworker' ) : esc_html__( 'OFF', 'allyworker' ); ?>
					</span>
				</div>

				<p class="allyworker-config__card-desc">
					<?php esc_html_e( 'Activates the MCP server and registers all AllyWorker abilities so AI agents can connect to this site.', 'allyworker' ); ?>
				</p>

				<div class="allyworker-config__security-note">
					<strong><?php esc_html_e( 'Security note:', 'allyworker' ); ?></strong>
					<?php esc_html_e( 'When enabled, AI agents can execute PHP code and perform filesystem operations on this site. For development and staging environments only. Always keep backups.', 'allyworker' ); ?>
				</div>

				<form method="post" action="" style="margin-top:16px;">
					<?php wp_nonce_field( 'allyworker_toggle_abilities', 'allyworker_toggle_nonce' ); ?>
					<input type="hidden" name="allyworker_abilities_toggle" value="1">
					<input type="hidden" name="allyworker_abilities_value" value="<?php echo $enabled ? '0' : '1'; ?>">
					<button type="submit" class="button <?php echo $enabled ? 'button-secondary allyworker-config__btn-disable' : 'button-primary'; ?>">
						<?php echo $enabled
							? esc_html__( 'Disable AI Abilities', 'allyworker' )
							: esc_html__( 'Enable AI Abilities', 'allyworker' ); ?>
					</button>
				</form>
			</div>

			<!-- ══════════════════════════════════════════════════════════════
			     STEP 2 — Application Password
			     ══════════════════════════════════════════════════════════════ -->
			<div class="allyworker-config__card"
			     id="allyworker-step-2"
			     data-step="2"
			     <?php echo ! $enabled ? 'style="display:none;"' : ''; ?>>
				<div class="allyworker-config__card-header">
					<span class="allyworker-config__step">2</span>
					<h2 class="allyworker-config__card-title"><?php esc_html_e( 'Create an Application Password', 'allyworker' ); ?></h2>
				</div>

				<p class="allyworker-config__card-desc">
					<?php esc_html_e( 'Generate an application password that your AI client will use to authenticate with WordPress. The password is embedded into the connection text in step 3.', 'allyworker' ); ?>
				</p>

				<?php if ( ! $app_pw_ok ) : ?>
					<div class="notice notice-error inline" style="margin:0 0 16px;">
					<p>
						<strong><?php esc_html_e( 'HTTPS required.', 'allyworker' ); ?></strong>
						<?php esc_html_e( 'Application Passwords transmit credentials over the network. Enable SSL before connecting an AI client.', 'allyworker' ); ?>
						<?php esc_html_e( 'For local development, add to wp-config.php:', 'allyworker' ); ?>
						<code>define( 'WP_ENVIRONMENT_TYPE', 'local' );</code>
					</p>
				</div>
				<?php else : ?>

					<!-- Generated password reveal (hidden until AJAX response) -->
					<div id="allyworker-pw-reveal" style="display:none; margin-bottom:16px;">
						<div class="notice notice-success inline" style="margin:0 0 10px;">
							<p style="margin:0;">
								<?php esc_html_e( 'Application password generated. It is now embedded in the connection text in step 3. Save it somewhere safe: it will not be shown in full again.', 'allyworker' ); ?>
							</p>
						</div>
						<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
							<code id="allyworker-pw-value" style="font-size:.875rem; padding:5px 10px; background:#f0f0f0; border-radius:3px;"></code>
							<button type="button" class="button" onclick="allyworkerCopyPassword(this)">
								<?php esc_html_e( 'Copy password only', 'allyworker' ); ?>
							</button>
						</div>
					</div>

					<!-- Generate form -->
					<div id="allyworker-pw-generate-form">
						<?php $has_passwords = ! empty( $existing_passwords ); ?>
						<div id="allyworker-pw-name-wrap" style="margin-bottom:12px;<?php echo $has_passwords ? '' : ' display:none;'; ?>">
							<label for="allyworker-pw-name" style="display:block; margin-bottom:4px; font-weight:600;">
								<?php esc_html_e( 'Name', 'allyworker' ); ?>
								<span style="color:#d63638; margin-left:2px;" aria-hidden="true">*</span>
							</label>
							<input
								type="text"
								id="allyworker-pw-name"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. Cursor on laptop, Claude Desktop', 'allyworker' ); ?>"
								maxlength="70"
							>
							<p class="description" style="margin-top:4px;">
								<?php esc_html_e( 'A unique label for this credential — one per AI client.', 'allyworker' ); ?>
							</p>
						</div>
						<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
							<button type="button" class="button button-primary" id="allyworker-pw-generate-btn" onclick="allyworkerGeneratePassword(this)">
								<?php esc_html_e( 'Generate application password', 'allyworker' ); ?>
							</button>
							<span id="allyworker-pw-spinner" class="spinner" style="float:none; margin:0; display:none;"></span>
						</div>
					</div>

					<!-- Divider -->
					<hr style="margin:20px 0; border:none; border-top:1px solid #dcdcde;">

					<!-- Go to Application Passwords -->
					<div>
						<p class="allyworker-config__card-desc" style="margin-bottom:10px;">
							<?php
							printf(
								wp_kses_post(
									/* translators: %s: link to the Application Passwords section of the user profile */
									__( 'Prefer to manage passwords manually? Go to %s, enter a name like <strong>"Claude Code"</strong>, and click <strong>Add New Application Password</strong>. Copy the generated password — it is shown only once.', 'allyworker' )
								),
								'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '" target="_blank">'
								. esc_html__( 'your profile → Application Passwords', 'allyworker' )
								. '</a>'
							);
							?>
						</p>
						<p class="allyworker-config__card-desc" style="margin-bottom:12px;">
							<?php esc_html_e( 'Create one password per AI client so you can revoke access individually.', 'allyworker' ); ?>
						</p>
						<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"
						   class="button button-primary" target="_blank">
							<?php esc_html_e( 'Go to Application Passwords ↗', 'allyworker' ); ?>
						</a>
					</div>

					<!-- Existing passwords table — only AllyWorker-prefixed, shown at the bottom -->
					<div id="allyworker-pw-existing" style="<?php echo empty( $existing_passwords ) ? 'display:none;' : ''; ?>margin-top:20px; padding-top:20px; border-top:1px solid #dcdcde;">
						<p class="allyworker-pw-section-label">
							<?php esc_html_e( 'Manage existing application passwords', 'allyworker' ); ?>
							&nbsp;<span id="allyworker-pw-count">(<?php echo esc_html( (string) count( $existing_passwords ) ); ?>)</span>
						</p>
						<div class="allyworker-pw-table-wrap">
							<table class="allyworker-pw-table" id="allyworker-pw-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Name', 'allyworker' ); ?></th>
										<th><?php esc_html_e( 'Created', 'allyworker' ); ?></th>
										<th><?php esc_html_e( 'Last Used', 'allyworker' ); ?></th>
										<th></th>
									</tr>
								</thead>
								<tbody id="allyworker-pw-tbody">
									<?php foreach ( $existing_passwords as $pw ) : ?>
										<tr data-uuid="<?php echo esc_attr( $pw['uuid'] ); ?>">
											<td class="allyworker-pw-table__name"><?php echo esc_html( $pw['display_name'] ); ?></td>
											<td class="allyworker-pw-table__meta"><?php echo esc_html( $pw['created'] ); ?></td>
											<td class="allyworker-pw-table__meta"><?php echo esc_html( $pw['last_used'] ); ?></td>
											<td class="allyworker-pw-table__actions">
												<button type="button" class="button button-small allyworker-pw-revoke-btn"
												        onclick="allyworkerRevokePassword('<?php echo esc_js( $pw['uuid'] ); ?>', this)">
													<?php esc_html_e( 'Revoke', 'allyworker' ); ?>
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
			<div class="allyworker-config__card"
			     id="allyworker-step-3"
			     data-step="3"
			     <?php echo $step3_hidden ? 'style="display:none;"' : ''; ?>>
				<div class="allyworker-config__card-header">
					<span class="allyworker-config__step">3</span>
					<h2 class="allyworker-config__card-title"><?php esc_html_e( 'Connect Your AI Client', 'allyworker' ); ?></h2>
				</div>

				<p style="margin:0 0 12px;">
					<?php esc_html_e( 'Copy the block below and paste it to your AI agent.', 'allyworker' ); ?>
				</p>

				<div class="notice notice-info inline" style="margin:0 0 12px;">
					<p style="margin:0;">
						<strong><?php esc_html_e( 'This prompt shares your application password with your AI agent.', 'allyworker' ); ?></strong>
						<?php
						printf(
							wp_kses(
								/* translators: %s: link */
								__( 'Prefer to keep it private? Use the <button type="button" class="button-link" onclick="allyworkerOpenManualConfig()">manual configuration</button> and paste the snippet into the config file yourself.', 'allyworker' ),
								[ 'button' => [ 'type' => [], 'class' => [], 'onclick' => [] ] ]
							)
						);
						?>
					</p>
				</div>

				<!-- Paste block -->
				<div class="allyworker-paste-block">
					<div class="allyworker-paste-content is-expanded" id="allyworker-paste-content">
						<pre id="allyworker-paste-text"></pre>
					</div>
					<div class="allyworker-paste-actions">
						<button type="button" class="button-link" id="allyworker-paste-expand"
						        onclick="allyworkerToggleExpandPaste(this)" aria-expanded="true"
						        aria-controls="allyworker-paste-content">
							<?php esc_html_e( 'Show less', 'allyworker' ); ?>
						</button>
						<button type="button" class="button button-primary" onclick="allyworkerCopyPaste(this)">
							<?php esc_html_e( 'Copy prompt', 'allyworker' ); ?>
						</button>
						<p id="allyworker-paste-warning" style="display:none; margin:0; color:#d63638; font-size:13px; font-weight:600;">
							<?php esc_html_e( "Don't share with anyone: it contains an application password that grants access to this WordPress site.", 'allyworker' ); ?>
						</p>
					</div>
				</div>

				<!-- Server name -->
				<p style="margin:14px 0 4px;">
					<button type="button" class="button-link" id="allyworker-name-toggle"
					        aria-expanded="false" aria-controls="allyworker-name-field"
					        onclick="allyworkerToggleServerName(this)">
						<?php esc_html_e( 'Change server name (optional)', 'allyworker' ); ?>
					</button>
				</p>
				<div id="allyworker-name-field" style="display:none; margin:6px 0 14px;">
					<input type="text" id="allyworker-mcp-name"
					       value="<?php echo esc_attr( $default_name ); ?>"
					       placeholder="<?php echo esc_attr( $default_name ); ?>"
					       maxlength="25" style="width:240px;"
					       oninput="allyworkerUpdateName(this.value)">
					<p class="description" style="margin:6px 0 0;">
						<?php esc_html_e( 'Editing here updates the connection text and JSON snippets below in real time. Each AI client config keeps its own name once saved on its side.', 'allyworker' ); ?>
					</p>
					<div id="allyworker-name-warning" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
						<p style="margin:0;"><?php esc_html_e( 'Maximum 25 characters reached. Required for client compatibility.', 'allyworker' ); ?></p>
					</div>
					<div id="allyworker-name-suggestion" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
						<p style="margin:0;"><?php esc_html_e( 'Tip: keep "allyworker" in the name so you (and your AI agent) can tell this MCP server apart from others.', 'allyworker' ); ?></p>
					</div>
				</div>

				<!-- Manual / per-client JSON config -->
				<p style="margin:6px 0 4px;">
					<button type="button" class="button-link" id="allyworker-manual-toggle"
					        aria-expanded="false" aria-controls="allyworker-manual-config"
					        onclick="allyworkerToggleManualConfig(this)">
						<?php esc_html_e( 'Need the JSON config for a specific client?', 'allyworker' ); ?>
					</button>
				</p>
				<div id="allyworker-manual-config" style="display:none;">
					<p class="description" style="margin:0 0 12px;">
						<?php esc_html_e( 'Select your AI client to copy the JSON snippet for its config file.', 'allyworker' ); ?>
					</p>

					<div class="notice notice-warning inline" style="margin:0 0 14px;">
						<p style="margin:0;">
							<strong><?php esc_html_e( 'Node.js 20.1 or higher is required.', 'allyworker' ); ?></strong>
							<?php esc_html_e( 'These configs use', 'allyworker' ); ?>
							<code>npx</code>
							<?php esc_html_e( 'to run the MCP transport. Run', 'allyworker' ); ?>
							<code>node -v</code>
							<?php esc_html_e( 'in your terminal to check. If your version is below 20.1, download the latest LTS from', 'allyworker' ); ?>
							<a href="https://nodejs.org/" target="_blank" rel="noopener noreferrer">nodejs.org</a>.
							<?php
							printf(
								wp_kses(
									/* translators: %s: button label */
									__( "Can't install Node.js? Use the <strong>npx-free alternative</strong> below instead.", 'allyworker' ),
									[ 'strong' => [] ]
								)
							);
							?>
						</p>
					</div>

					<div class="allyworker-client-tabs" id="allyworker-manual-tabs">
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
							        class="allyworker-client-tab<?php echo $first ? ' is-active' : ''; ?>"
							        data-client="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $label ); ?>
							</button>
							<?php $first = false; ?>
						<?php endforeach; ?>
					</div>
					<div class="allyworker-config-block">
						<pre id="allyworker-config-code"></pre>
						<button type="button" class="button allyworker-copy-code-btn" onclick="allyworkerCopyConfig(this)">
							<?php esc_html_e( 'Copy', 'allyworker' ); ?>
						</button>
					</div>
					<div class="allyworker-config-footer">
						<div id="allyworker-config-hint" class="allyworker-config-hint"></div>
						<div id="allyworker-config-paths" class="allyworker-config-paths" style="display:none;"></div>
					</div>
				</div>

				<!-- npx-free alternative -->
				<p style="margin:6px 0 4px;">
					<button type="button" class="button-link" id="allyworker-npxless-toggle"
					        aria-expanded="false" aria-controls="allyworker-npxless-config"
					        onclick="allyworkerToggleNpxless(this)">
						<?php esc_html_e( 'Configs above not working? Try this npx-free alternative.', 'allyworker' ); ?>
					</button>
				</p>
				<div id="allyworker-npxless-config" style="display:none;">
					<p class="description" style="margin:0 0 12px;">
						<?php esc_html_e( 'Copy this configuration snippet to connect using direct HTTP (no Node/npx required).', 'allyworker' ); ?>
					</p>
					<div class="allyworker-client-tabs">
						<button type="button" class="allyworker-client-tab allyworker-npxless-tab is-active"
						        data-client="claude">
							<?php esc_html_e( 'Claude Code', 'allyworker' ); ?>
						</button>
						<button type="button" class="allyworker-client-tab allyworker-npxless-tab"
						        data-client="codex">
							<?php esc_html_e( 'Codex', 'allyworker' ); ?>
						</button>
					</div>
					<div class="allyworker-config-block">
						<pre id="allyworker-npxless-code"></pre>
						<button type="button" class="button allyworker-copy-code-btn" onclick="allyworkerCopyNpxless(this)">
							<?php esc_html_e( 'Copy', 'allyworker' ); ?>
						</button>
					</div>
					<div class="allyworker-config-footer">
						<div id="allyworker-npxless-hint" class="allyworker-config-hint"></div>
						<div id="allyworker-npxless-paths" class="allyworker-config-paths"></div>
					</div>
				</div>
			</div>
		</div><!-- .allyworker-config -->

		<?php
		// Pass server-side data to the external configuration.js module.
		// All logic lives in src/admin/components/configuration.js — no inline JS here.
		wp_add_inline_script(
			'allyworker-admin',
			'window.allyworkerConfig = ' . wp_json_encode( [
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
					'copied'          => __( 'Copied!', 'allyworker' ),
					'showLess'        => __( 'Show less', 'allyworker' ),
					'showFull'        => __( 'Show full text', 'allyworker' ),
					'generateAnother' => __( 'Generate another application password', 'allyworker' ),
					'never'           => __( 'Never', 'allyworker' ),
					'revoke'          => __( 'Revoke', 'allyworker' ),
					'revokeConfirm'   => __( 'Revoke this application password? The AI client using it will lose access immediately.', 'allyworker' ),
					'errorNameRequired' => __( 'Please enter a name for this password.', 'allyworker' ),
					'errorGenerate'   => __( 'Error generating password.', 'allyworker' ),
					'errorNetwork'    => __( 'Network error. Please try again.', 'allyworker' ),
					'completeStep1'   => __( 'Complete step 1 first', 'allyworker' ),
					'completeStep2'   => __( 'Complete step 2 first', 'allyworker' ),
				],
			] ) . ';',
			'before'
		);
	}

	// Action handlers
	private static function handle_toggle(): void {
		if ( ! isset( $_POST['allyworker_abilities_toggle'] ) ) {
			return;
		}
		check_admin_referer( 'allyworker_toggle_abilities', 'allyworker_toggle_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( AdminMenu::ABILITIES_ENABLED_OPTION, '1' === wp_unslash( $_POST['allyworker_abilities_value'] ?? '0' ), false );
	}

	// AJAX handlers
	public function ajax_generate_password(): void {
		check_ajax_referer( self::AJAX_GENERATE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'allyworker' ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( __( 'Application Passwords are not available.', 'allyworker' ) );
		}

		$raw_name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// First password (no name provided) → stored as "AllyWorker".
		// Subsequent passwords → stored as "AllyWorker: {name}".
		if ( $raw_name === '' ) {
			$name = 'AllyWorker';
		} else {
			$name = 'AllyWorker: ' . $raw_name;
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
			wp_send_json_error( __( 'Insufficient permissions.', 'allyworker' ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( __( 'Application Passwords are not available.', 'allyworker' ) );
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

			// Only show passwords created by AllyWorker: exact "AllyWorker" or "AllyWorker: *".
			if ( $pw_name !== 'AllyWorker' && stripos( $pw_name, 'AllyWorker: ' ) !== 0 ) {
				continue;
			}

			$result[] = [
				'uuid'         => (string) ( $pw['uuid'] ?? '' ),
				'name'         => $pw_name,
				// Show the full stored name (prefix visible in the table).
				'display_name' => $pw_name,
				'created'      => $pw['created'] ? (string) wp_date( get_option( 'date_format' ) . ' g:i a', $pw['created'] ) : '—',
				'last_used'    => $pw['last_used'] ? (string) wp_date( get_option( 'date_format' ), $pw['last_used'] ) : __( 'Never', 'allyworker' ),
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
			. "- Application password: __ALLYWORKER_PW_SLOT__\n"
			. "- Server name to use in the config: __ALLYWORKER_MCP_NAME__\n"
			. "- Transport: @automattic/mcp-wordpress-remote via npx\n\n"
			. "Setup rules:\n"
			. "- Pass credentials ONLY as env vars: WP_API_URL, WP_API_USERNAME, WP_API_PASSWORD. Do NOT use CLI flags like --url or --password (the package ignores them).\n"
			. "- args array must be exactly [\"-y\", \"@automattic/mcp-wordpress-remote@latest\"].\n\n"
			. "Don't ask me to confirm choices already specified above. After writing the config, restart or reload the MCP session (most clients require it), then verify by listing the server's tools. If it fails, show me the stderr from the npx process before proposing changes.\n\n"
			. "If you cannot modify the config of this AI client from here, tell me to expand \"Need the JSON config for a specific client?\" on the AllyWorker Configuration page and copy the snippet manually.";
	}
}

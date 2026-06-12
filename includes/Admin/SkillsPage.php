<?php
/**
 * Skills admin page — create, edit, delete, and toggle skills.
 *
 * Matches WPCodex's Skills page: card layout, enable/disable toggle,
 * inline editor with name/description/body fields, and file upload.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex\Admin;

use WPCodex\Skills\Notices as SkillNotices;
use WPCodex\Skills\Repository;
use WPCodex\Skills\Sources;

/**
 * Class SkillsPage
 */
final class SkillsPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpcodex' ) );
		}

		$action  = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notices = self::handle_actions();

		// Pick up notices that were passed via redirect (e.g. after new skill creation).
		$redirect_notice = isset( $_GET['wpcodex_notice'] ) ? sanitize_key( $_GET['wpcodex_notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'created' === $redirect_notice ) {
			$notices[] = [ 'type' => 'success', 'message' => __( 'Skill created.', 'wpcodex' ) ];
		}

		// Show the MCP-reload reminder only when no action notice is already present
		// (i.e. the mutation was made externally via MCP, not from this UI).
		if ( empty( $notices ) ) {
			$reload = SkillNotices::pending_reload_notice();
			if ( null !== $reload ) {
				$notices[] = $reload;
			}
		} else {
			// Consume the transient silently so it doesn't carry over to the next load.
			SkillNotices::pending_reload_notice();
		}

		switch ( $action ) {
			case 'edit':
			case 'new':
				self::render_edit( $action, $notices );
				break;
			default:
				self::render_list( $notices );
				break;
		}
	}

	/**
	 * Handle form submissions and return any notices.
	 *
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_actions(): array {
		$notices = [];

		if ( ! isset( $_POST['wpcodex_skills_nonce'] ) ) {
			return $notices;
		}

		check_admin_referer( 'wpcodex_skills_action', 'wpcodex_skills_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return $notices;
		}

		$form_action = isset( $_POST['form_action'] ) ? sanitize_key( $_POST['form_action'] ) : '';

		switch ( $form_action ) {
			case 'save':
				$notices = array_merge( $notices, self::handle_save() );
				break;

			case 'delete':
				$notices = array_merge( $notices, self::handle_delete() );
				break;

			case 'toggle':
				$notices = array_merge( $notices, self::handle_toggle() );
				break;

			case 'upload':
				$notices = array_merge( $notices, self::handle_upload() );
				break;

			case 'restore':
				$notices = array_merge( $notices, self::handle_restore() );
				break;
		}

		return $notices;
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_save(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in handle_actions()
		$name            = sanitize_title( wp_unslash( $_POST['skill_name'] ?? '' ) );
		$description     = sanitize_text_field( wp_unslash( $_POST['skill_description'] ?? '' ) );
		$body            = wp_kses_post( wp_unslash( $_POST['skill_body'] ?? '' ) );
		$enable_agentic  = isset( $_POST['enable_agentic'] ) && '1' === $_POST['enable_agentic'];
		$enable_prompt   = isset( $_POST['enable_prompt'] )  && '1' === $_POST['enable_prompt'];
		$original_name   = sanitize_title( wp_unslash( $_POST['original_name'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$is_edit         = '' !== $original_name;

		if ( ! $name || ! $description || ! $body ) {
			return [ [ 'type' => 'error', 'message' => __( 'Name, description, and body are all required.', 'wpcodex' ) ] ];
		}

		$repo = Repository::instance();

		if ( $is_edit ) {
			$result = $repo->update( $original_name, compact( 'description', 'body', 'enable_agentic', 'enable_prompt' ) );

			if ( is_wp_error( $result ) ) {
				return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
			}

			return [ [ 'type' => 'success', 'message' => __( 'Skill updated.', 'wpcodex' ) ] ];
		}

		// New skill — create then redirect to its edit page so the notice shows there.
		$result = $repo->create( compact( 'name', 'description', 'body', 'enable_agentic', 'enable_prompt' ) );

		if ( is_wp_error( $result ) ) {
			return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'            => 'wpcodex-skills',
					'action'          => 'edit',
					'name'            => $name,
					'wpcodex_notice'  => 'created',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_delete(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle_actions()
		$name   = sanitize_title( wp_unslash( $_POST['skill_name'] ?? '' ) );
		$result = Repository::instance()->delete( $name );

		if ( is_wp_error( $result ) ) {
			return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
		}

		return [ [ 'type' => 'success', 'message' => __( 'Skill deleted.', 'wpcodex' ) ] ];
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_toggle(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in handle_actions()
		$name    = sanitize_title( wp_unslash( $_POST['skill_name'] ?? '' ) );
		$field   = sanitize_key( wp_unslash( $_POST['toggle_field'] ?? '' ) );
		$value   = isset( $_POST['toggle_value'] ) && '1' === $_POST['toggle_value'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $field, [ 'enable_agentic', 'enable_prompt' ], true ) ) {
			return [];
		}

		$result = Repository::instance()->update( $name, [ $field => $value ] );

		if ( is_wp_error( $result ) ) {
			return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
		}

		return [ [ 'type' => 'success', 'message' => __( 'Skill updated.', 'wpcodex' ) ] ];
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_upload(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handle_actions(); $_FILES keys are standard PHP upload fields
		if ( ! isset( $_FILES['skill_file'] ) || UPLOAD_ERR_OK !== $_FILES['skill_file']['error'] ) {
			return [ [ 'type' => 'error', 'message' => __( 'File upload failed.', 'wpcodex' ) ] ];
		}

		$tmp  = $_FILES['skill_file']['tmp_name'];
		$name = sanitize_file_name( $_FILES['skill_file']['name'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! str_ends_with( $name, '.md' ) ) {
			return [ [ 'type' => 'error', 'message' => __( 'Only .md files can be uploaded as skills.', 'wpcodex' ) ] ];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $tmp );

		if ( false === $content ) {
			return [ [ 'type' => 'error', 'message' => __( 'Could not read the uploaded file.', 'wpcodex' ) ] ];
		}

		// Parse frontmatter.
		$parsed = self::parse_frontmatter( $content );

		if ( empty( $parsed['name'] ) || empty( $parsed['description'] ) ) {
			return [ [ 'type' => 'error', 'message' => __( 'The uploaded skill file must contain a YAML frontmatter block with at least "name" and "description" fields.', 'wpcodex' ) ] ];
		}

		$result = Repository::instance()->create( [
			'name'           => sanitize_title( $parsed['name'] ),
			'description'    => sanitize_text_field( $parsed['description'] ),
			'body'           => wp_kses_post( $parsed['body'] ),
			'enable_agentic' => $parsed['enable_agentic'] ?? true,
			'enable_prompt'  => $parsed['enable_prompt'] ?? true,
		] );

		if ( is_wp_error( $result ) ) {
			return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
		}

		return [ [ 'type' => 'success', 'message' => __( 'Skill imported from file.', 'wpcodex' ) ] ];
	}

	/**
	 * @return array{type: string, message: string}[]
	 */
	private static function handle_restore(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle_actions()
		$revision_id = isset( $_POST['revision_id'] ) ? (int) $_POST['revision_id'] : 0;

		if ( $revision_id <= 0 ) {
			return [ [ 'type' => 'error', 'message' => __( 'Invalid revision ID.', 'wpcodex' ) ] ];
		}

		$result = Repository::instance()->restore_revision( $revision_id );

		if ( is_wp_error( $result ) ) {
			return [ [ 'type' => 'error', 'message' => $result->get_error_message() ] ];
		}

		return [ [ 'type' => 'success', 'message' => __( 'Revision restored successfully.', 'wpcodex' ) ] ];
	}


	/**
	 * @param array{type: string, message: string}[] $notices
	 */
	private static function render_list( array $notices ): void {
		$all_skills    = Sources::all();
		$user_skills   = array_values( array_filter( $all_skills, static fn( array $s ) => 'user-db' === ( $s['source'] ?? '' ) ) );

		// Group non-user-db skills by source for separate read-only sections.
		/** @var array<string, array{label: string, skills: list<array<string, mixed>>}> $external_groups */
		$external_groups = [];
		foreach ( $all_skills as $skill ) {
			$src = (string) ( $skill['source'] ?? '' );
			if ( 'user-db' === $src ) {
				continue;
			}
			if ( ! isset( $external_groups[ $src ] ) ) {
				$external_groups[ $src ] = [
					'label'  => (string) ( $skill['source_label'] ?? $src ),
					'skills' => [],
				];
			}
			$external_groups[ $src ]['skills'][] = $skill;
		}
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-skills">
			<div class="wpcodex-page-header">
				<h1 class="wpcodex-page-title"><?php esc_html_e( 'Skills', 'wpcodex' ); ?></h1>
				<div class="wpcodex-page-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcodex-skills&action=new' ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( '+ New Skill', 'wpcodex' ); ?>
					</a>
				</div>
			</div>
			<div class="wp-header-end"></div>
			<?php self::render_notices( $notices ); ?>
			<p class="wpcodex-page-description">
				<?php esc_html_e( 'Skills are Markdown playbooks stored in the WordPress database. The agent reads their descriptions at session start and loads the body when the description matches the task.', 'wpcodex' ); ?>
			</p>

			<!-- Upload form -->
			<div class="wpcodex-upload-area">
				<form method="post" enctype="multipart/form-data" action="">
					<?php wp_nonce_field( 'wpcodex_skills_action', 'wpcodex_skills_nonce' ); ?>
					<input type="hidden" name="form_action" value="upload">
					<label for="wpcodex-skill-upload" class="wpcodex-upload-label">
						<?php esc_html_e( 'Import skill from .md file:', 'wpcodex' ); ?>
					</label>
					<input type="file" id="wpcodex-skill-upload" name="skill_file" accept=".md">
					<button type="submit" class="button"><?php esc_html_e( 'Upload', 'wpcodex' ); ?></button>
				</form>
			</div>

			<!-- Your skills grid -->
			<?php if ( empty( $user_skills ) && empty( $external_groups ) ) : ?>
				<div class="wpcodex-empty-state">
					<p><?php esc_html_e( 'No skills yet. Create your first skill or upload a .md file.', 'wpcodex' ); ?></p>
				</div>
			<?php else : ?>
				<?php if ( ! empty( $user_skills ) ) : ?>
					<div class="wpcodex-cards">
						<?php foreach ( $user_skills as $skill ) : ?>
							<?php self::render_skill_card( $skill ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php foreach ( $external_groups as $group ) : ?>
					<h2 style="margin-top:2em;">
						<?php
						printf(
							/* translators: %s: source label, e.g. "Built-in" */
							esc_html__( 'Skills from %s', 'wpcodex' ),
							esc_html( $group['label'] )
						);
						?>
						<span style="font-size:0.75em;font-weight:400;color:#666;margin-left:0.5em;">
							<?php esc_html_e( '(read-only)', 'wpcodex' ); ?>
						</span>
					</h2>
					<div class="wpcodex-cards">
						<?php foreach ( $group['skills'] as $skill ) : ?>
							<?php self::render_skill_card( $skill, true ); ?>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>                   $skill
	 * @param array{type: string, message: string}[] $notices
	 */
	private static function render_edit( string $action, array $notices ): void {
		$name  = isset( $_GET['name'] ) ? sanitize_title( wp_unslash( $_GET['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skill = 'edit' === $action && $name ? Repository::instance()->find( $name ) : null;
		$title = 'edit' === $action ? __( 'Edit Skill', 'wpcodex' ) : __( 'New Skill', 'wpcodex' );
		?>
		<div class="wrap wpcodex-wrap" id="wpcodex-skill-editor">
			<div class="wpcodex-page-header">
				<h1 class="wpcodex-page-title"><?php echo esc_html( $title ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcodex-skills' ) ); ?>"
				   class="button">&larr; <?php esc_html_e( 'Back to Skills', 'wpcodex' ); ?></a>
			</div>
			<div class="wp-header-end"></div>
			<?php self::render_notices( $notices ); ?>

			<?php
			// Post back to the current page so notices render here, not on the list.
			$self_url = add_query_arg(
				array_filter( [
					'page'   => 'wpcodex-skills',
					'action' => $action,
					'name'   => ( 'edit' === $action && isset( $skill['name'] ) ) ? (string) $skill['name'] : null,
				] ),
				admin_url( 'admin.php' )
			);
			?>
			<form method="post" action="<?php echo esc_url( $self_url ); ?>">
				<?php wp_nonce_field( 'wpcodex_skills_action', 'wpcodex_skills_nonce' ); ?>
				<input type="hidden" name="form_action"   value="save">
				<input type="hidden" name="original_name" value="<?php echo esc_attr( $skill['name'] ?? '' ); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="skill_name"><?php esc_html_e( 'Name (slug)', 'wpcodex' ); ?></label></th>
						<td>
							<input
								type="text"
								id="skill_name"
								name="skill_name"
								value="<?php echo esc_attr( $skill['name'] ?? '' ); ?>"
								class="regular-text"
								<?php echo $skill ? 'readonly' : ''; ?>
								required
							>
							<p class="description"><?php esc_html_e( 'Lowercase, hyphen-separated. Cannot be changed after creation.', 'wpcodex' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skill_description"><?php esc_html_e( 'Description (trigger)', 'wpcodex' ); ?></label></th>
						<td>
							<input
								type="text"
								id="skill_description"
								name="skill_description"
								value="<?php echo esc_attr( $skill['description'] ?? '' ); ?>"
								class="large-text"
								required
							>
							<p class="description"><?php esc_html_e( 'The trigger sentence the agent reads at session start. Write it so the agent knows when to use this skill.', 'wpcodex' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skill_body"><?php esc_html_e( 'Body (Markdown)', 'wpcodex' ); ?></label></th>
						<td>
							<textarea
								id="skill_body"
								name="skill_body"
								rows="20"
								class="large-text code wpcodex-skill-editor"
								required
							><?php echo esc_textarea( $skill['body'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Plain Markdown. This is loaded into the agent\'s context when the description matches.', 'wpcodex' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Agentic mode', 'wpcodex' ); ?></th>
						<td>
							<label>
								<input type="hidden"   name="enable_agentic" value="0">
								<input type="checkbox" name="enable_agentic" value="1"
								       <?php checked( $skill['enable_agentic'] ?? true ); ?>>
								<?php esc_html_e( 'Fire automatically when description matches (enable_agentic)', 'wpcodex' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prompt menu', 'wpcodex' ); ?></th>
						<td>
							<label>
								<input type="hidden"   name="enable_prompt" value="0">
								<input type="checkbox" name="enable_prompt" value="1"
								       <?php checked( $skill['enable_prompt'] ?? true ); ?>>
								<?php esc_html_e( 'Show in AI client prompt menu (enable_prompt)', 'wpcodex' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( $skill ? __( 'Update Skill', 'wpcodex' ) : __( 'Create Skill', 'wpcodex' ) ); ?>
			</form>

			<?php if ( 'edit' === $action && null !== $skill ) : ?>
				<?php
				$revisions = Repository::instance()->get_revisions( (string) $skill['name'] );
				if ( ! empty( $revisions ) ) :
				?>
				<div class="wpcodex-revisions" style="margin-top: 2em;">
					<h2><?php esc_html_e( 'Revision History', 'wpcodex' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Up to 10 previous versions. Restoring a revision snapshots the current state first, so every restore is reversible.', 'wpcodex' ); ?></p>
					<table class="widefat striped" style="margin-top: 0.75em;">
						<thead>
							<tr>
								<th><?php esc_html_e( '#', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Saved', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Description', 'wpcodex' ); ?></th>
								<th><?php esc_html_e( 'Body preview', 'wpcodex' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $revisions as $index => $rev ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( count( $revisions ) - $index ) ); ?></td>
									<td style="white-space:nowrap;"><?php echo esc_html( (string) $rev['created_at'] ); ?></td>
									<td><?php echo esc_html( mb_strimwidth( (string) $rev['description'], 0, 60, '…' ) ); ?></td>
									<td><code style="font-size:0.85em;"><?php echo esc_html( mb_strimwidth( (string) $rev['body'], 0, 90, '…' ) ); ?></code></td>
									<td>
										<form method="post"
										      action="<?php echo esc_url( admin_url( 'admin.php?page=wpcodex-skills&action=edit&name=' . urlencode( (string) $skill['name'] ) ) ); ?>"
										      style="display:inline;">
											<?php wp_nonce_field( 'wpcodex_skills_action', 'wpcodex_skills_nonce' ); ?>
											<input type="hidden" name="form_action"  value="restore">
											<input type="hidden" name="revision_id"  value="<?php echo esc_attr( (string) $rev['id'] ); ?>">
											<input type="hidden" name="skill_name"   value="<?php echo esc_attr( (string) $skill['name'] ); ?>">
											<button type="submit"
											        class="button button-small"
											        onclick="return confirm('<?php echo esc_js( __( 'Restore this revision? The current state will be saved first.', 'wpcodex' ) ); ?>')">
												<?php esc_html_e( 'Restore', 'wpcodex' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $skill
	 * @param bool                 $readonly True for built-in/external sources — hides Edit and Delete.
	 */
	private static function render_skill_card( array $skill, bool $readonly = false ): void {
		$name           = (string) ( $skill['name'] ?? $skill['slug'] ?? '' );
		$description    = (string) ( $skill['description'] ?? '' );
		$enable_agentic = (bool) ( $skill['enable_agentic'] ?? true );
		$enable_prompt  = (bool) ( $skill['enable_prompt'] ?? false );
		$edit_url       = admin_url( 'admin.php?page=wpcodex-skills&action=edit&name=' . urlencode( $name ) );
		?>
		<div class="wpcodex-card<?php echo $readonly ? ' wpcodex-card--readonly' : ''; ?>">
			<div class="wpcodex-card__header">
				<span class="wpcodex-card__name"><?php echo esc_html( $name ); ?></span>
				<div class="wpcodex-card__badges">
					<?php if ( $enable_agentic ) : ?>
						<span class="wpcodex-badge wpcodex-badge--agentic"><?php esc_html_e( 'Agentic', 'wpcodex' ); ?></span>
					<?php endif; ?>
					<?php if ( $enable_prompt ) : ?>
						<span class="wpcodex-badge wpcodex-badge--prompt"><?php esc_html_e( 'Prompt', 'wpcodex' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<p class="wpcodex-card__description"><?php echo esc_html( $description ); ?></p>
			<?php if ( ! $readonly ) : ?>
			<div class="wpcodex-card__actions">
				<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
					<?php esc_html_e( 'Edit', 'wpcodex' ); ?>
				</a>
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'wpcodex_skills_action', 'wpcodex_skills_nonce' ); ?>
					<input type="hidden" name="form_action" value="delete">
					<input type="hidden" name="skill_name"  value="<?php echo esc_attr( $name ); ?>">
					<button type="submit" class="button button-small button-link-delete"
					        onclick="return confirm('<?php echo esc_js( __( 'Delete this skill? This cannot be undone.', 'wpcodex' ) ); ?>')">
						<?php esc_html_e( 'Delete', 'wpcodex' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array{type: string, message: string}[] $notices
	 */
	private static function render_notices( array $notices ): void {
		foreach ( $notices as $notice ) {
			$type    = in_array( $notice['type'], [ 'success', 'error', 'warning', 'info' ], true ) ? $notice['type'] : 'info';
			$updated = 'success' === $type ? ' notice-success' : ' notice-' . $type;
			printf(
				'<div class="notice%s is-dismissible"><p>%s</p></div>',
				esc_attr( $updated ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Parse YAML-style frontmatter from a Markdown document.
	 *
	 * Handles the simple key: value format used in skill files:
	 *   ---
	 *   name: my-skill
	 *   description: One-line trigger
	 *   enable_agentic: true
	 *   enable_prompt: false
	 *   ---
	 *
	 * @return array{name?: string, description?: string, enable_agentic?: bool, enable_prompt?: bool, body: string}
	 */
	private static function parse_frontmatter( string $content ): array {
		$result = [ 'body' => $content ];

		if ( ! str_starts_with( trim( $content ), '---' ) ) {
			return $result;
		}

		$parts = preg_split( '/^---\s*$/m', $content, 3 );

		if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
			return $result;
		}

		$frontmatter = trim( $parts[1] );
		$result['body'] = trim( $parts[2] );

		foreach ( explode( "\n", $frontmatter ) as $line ) {
			$line = trim( $line );
			if ( ! str_contains( $line, ':' ) ) {
				continue;
			}
			[ $key, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
			$key = strtolower( $key );

			switch ( $key ) {
				case 'name':
					$result['name'] = $value;
					break;
				case 'description':
					$result['description'] = $value;
					break;
				case 'enable_agentic':
					$result['enable_agentic'] = in_array( strtolower( $value ), [ 'true', '1', 'yes' ], true );
					break;
				case 'enable_prompt':
					$result['enable_prompt'] = in_array( strtolower( $value ), [ 'true', '1', 'yes' ], true );
					break;
			}
		}

		return $result;
	}
}

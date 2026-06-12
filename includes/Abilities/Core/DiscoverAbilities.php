<?php
/**
 * Ability: mcp-adapter/discover-abilities
 *
 * Replaces the MCP Adapter's built-in mcp-adapter/discover-abilities so the
 * response includes WPCodex environment instructions and the skill catalog.
 * Agents read this at session start and immediately know what tools and skills
 * are available without a separate tool call.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Core;

use WPCodex\Abilities\AbstractAbility;
use WPCodex\Skills\Sources;

class DiscoverAbilities extends AbstractAbility {

	/** {@inheritDoc} */
	public function get_name(): string {
		return 'mcp-adapter/discover-abilities';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Discover Abilities', 'wpcodex' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Discover all available WordPress abilities. Returns the full ability list plus WPCodex environment instructions and skill catalog. Call this at the start of every session.',
			'wpcodex'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => new \stdClass(),
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'instructions' => [
					'type'        => 'string',
					'description' => 'WPCodex environment instructions and skill catalog for this session.',
				],
				'abilities'    => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'name'        => [ 'type' => 'string' ],
							'label'       => [ 'type' => 'string' ],
							'description' => [ 'type' => 'string' ],
						],
						'required' => [ 'name', 'label', 'description' ],
					],
				],
			],
			'required' => [ 'instructions', 'abilities' ],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/**
	 * Custom permission callback: returns a WP_Error on auth failure so the
	 * MCP adapter can return the correct HTTP status code.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'wpcodex_not_authenticated', 'Authentication required.', [ 'status' => 401 ] );
		}
		/** @var string $cap */
		$cap = apply_filters( 'wpcodex_discover_abilities_capability', 'read' );
		if ( ! current_user_can( $cap ) ) {
			return new \WP_Error( 'wpcodex_insufficient_capability', 'Insufficient capability.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		// Build ability list — only public tool-type abilities.
		$ability_list = [];
		if ( function_exists( 'wp_get_abilities' ) ) {
			foreach ( wp_get_abilities() as $ability ) {
				if ( ! ( $ability instanceof \WP_Ability ) ) {
					continue;
				}
				$meta = $ability->get_meta();
				if ( ! ( $meta['mcp']['public'] ?? false ) ) {
					continue;
				}
				if ( ( $meta['mcp']['type'] ?? 'tool' ) !== 'tool' ) {
					continue;
				}
				$ability_list[] = [
					'name'        => $ability->get_name(),
					'label'       => $ability->get_label(),
					'description' => $ability->get_description(),
				];
			}
		}

		return [
			'instructions' => self::build_instructions(),
			'abilities'    => $ability_list,
		];
	}

	public function register(): void {
		// wp_get_ability( 'name' ) calls WP_Abilities_Registry::get_registered()
		// which triggers _doing_it_wrong() in WP 6.9 when the ability doesn't exist.
		// The safe way to check existence is via wp_get_abilities().
		$registered = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : [];

		// Unregister the MCP Adapter's default so we can replace it.
		if ( isset( $registered['mcp-adapter/discover-abilities'] )
			&& function_exists( 'wp_unregister_ability' )
		) {
			wp_unregister_ability( 'mcp-adapter/discover-abilities' );
		}

		// Guard: if unregister failed (locked ability), don't double-register.
		$registered_after = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : [];
		if ( isset( $registered_after['mcp-adapter/discover-abilities'] ) ) {
			return;
		}

		wp_register_ability( $this->get_name(), $this->get_config() );
	}

	/**
	 * Build the session instructions block including the skill catalog.
	 */
	private static function build_instructions(): string {
		$site_url = home_url();
		$sandbox  = WPCODEX_SANDBOX_DIR;

		$lines = [
			'## WPCodex — AI Operating System for WordPress',
			'',
			'You are connected to a WordPress site via the WPCodex MCP server.',
			'',
			'**Site:** ' . $site_url,
			'**Sandbox directory:** ' . $sandbox,
			'',
			'## Environment',
			'',
			'WordPress ' . get_bloginfo( 'version' ) . ' — PHP ' . PHP_VERSION . ' — Locale: ' . get_locale(),
		];

		// Multilingual plugin detection.
		$multilingual = self::get_active_languages();
		if ( null !== $multilingual && [] !== $multilingual['languages'] ) {
			$lines[] = 'Multilingual (' . $multilingual['plugin'] . '): ' . implode( ', ', $multilingual['languages'] );
		}

		$lines[] = '';

		// Installed plugins inventory.
		if ( function_exists( 'get_plugins' ) ) {
			/** @var array<string, array{Name?: string, Version?: string}> $all_plugins */
			$all_plugins = get_plugins();
			if ( [] !== $all_plugins ) {
				$lines[] = 'Installed plugins:';
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$name           = $plugin_data['Name'] ?? $plugin_file;
					$version        = $plugin_data['Version'] ?? '';
					$version_suffix = '' !== $version ? ' v' . $version : '';
					$active         = is_plugin_active( $plugin_file ) ? 'active' : 'inactive';
					$lines[]        = '- ' . $name . $version_suffix . ' (' . $active . ')';
				}
				$lines[] = '';
			}
		}

		// Abilities & tools overview.
		$lines = array_merge( $lines, [
			'### Abilities',
			'',
			'- Execute arbitrary PHP with `wpcodex/php-execute` — full WordPress environment available (`$wpdb`, all functions, all plugins).',
			'- Run WP-CLI commands with `wpcodex/wpcli-run`.',
			'- Read, write, edit, delete, and list files with the `wpcodex/file-*` abilities.',
			'- Persist PHP code across requests by writing files to the sandbox directory and using `wpcodex/file-disable` / `wpcodex/file-enable` to control loading.',
			'- Manage skill playbooks with `wpcodex/skill-*` abilities.',
			'',
		] );

		// WordPress-native development guidelines.
		$lines = array_merge( $lines, [
			'## WordPress-native development',
			'',
			'IMPORTANT: Prefer WordPress-native features to store and manage data.',
			'Do not hardcode content in PHP arrays when WordPress has a better mechanism:',
			'- Custom post types (register_post_type) for structured content (unless a data-modeling plugin owns it — see below)',
			'- Taxonomies (register_taxonomy) for categorization (same caveat)',
			'- Post meta / custom fields (update_post_meta) for additional data on posts (same caveat)',
			'- Options API (update_option) for settings and configuration',
			'- Custom database tables via $wpdb only when the above are insufficient',
			'',
			'Take advantage of active plugins. If a data-modeling plugin is in the installed-plugins inventory above',
			'(ACF / ACF Pro, JetEngine, Pods, ACPT, Meta Box, Toolset, Custom Post Type UI, WooCommerce, etc.),',
			'use it for the task it owns — never write a custom register_post_type / register_taxonomy / register_meta',
			'call in PHP for content the active plugin can model through its own UI/API.',
			'',
			'Use WordPress hooks (actions/filters), template hierarchy, and REST API conventions.',
			'Write code that integrates with WordPress, not code that ignores it.',
			'',
		] );

		// Building pages context.
		$lines = array_merge( $lines, self::build_building_context_lines() );
		$lines[] = '';

		// Rules.
		$lines = array_merge( $lines, [
			'## Rules',
			'',
			'- Always inspect before modifying. Use `wpcodex/file-read` or `wpcodex/php-execute` to understand the current state.',
			'- For destructive operations, confirm with the user first.',
			'- Sandbox files run on every WordPress request — keep them lean and non-blocking.',
			'',
		] );

		$catalog = self::build_skill_catalog();
		if ( '' !== $catalog ) {
			$lines[] = $catalog;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Detect active multilingual plugin and language list.
	 *
	 * Supports WPML, Polylang, and TranslatePress.
	 *
	 * @return array{plugin: string, languages: string[]}|null
	 */
	private static function get_active_languages(): ?array {
		// WPML.
		if ( function_exists( 'icl_get_languages' ) ) {
			/** @var array<string, array{language_code: string}>|false $wpml_languages */
			$wpml_languages = icl_get_languages( 'skip_missing=0' );
			if ( is_array( $wpml_languages ) ) {
				return [ 'plugin' => 'WPML', 'languages' => array_column( $wpml_languages, 'language_code' ) ];
			}
		}

		// Polylang.
		if ( function_exists( 'pll_languages_list' ) ) {
			/** @var string[]|false $languages */
			$languages = pll_languages_list();
			if ( is_array( $languages ) ) {
				return [ 'plugin' => 'Polylang', 'languages' => $languages ];
			}
		}

		// TranslatePress.
		if ( class_exists( 'TRP_Translate_Press' ) ) {
			/** @var array{translation-languages?: string[]} $trp_settings */
			$trp_settings = get_option( 'trp_settings', [] );
			return [ 'plugin' => 'TranslatePress', 'languages' => $trp_settings['translation-languages'] ?? [] ];
		}

		return null;
	}

	/**
	 * Build the active theme + building mode context lines.
	 *
	 * @return list<string>
	 */
	private static function build_building_context_lines(): array {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return [];
		}

		$theme      = wp_get_theme();
		$theme_desc = (string) $theme->get( 'Name' );
		if ( $theme->get_template() !== $theme->get_stylesheet() ) {
			$parent = $theme->parent();
			$theme_desc .= ' (child theme of ' . ( $parent instanceof \WP_Theme ? (string) $parent->get( 'Name' ) : $theme->get_template() ) . ')';
		}

		return [
			'## Building pages and layout',
			'',
			'Active theme: ' . $theme_desc . '.',
			'',
			'Before building or restructuring a page\'s content or layout, check the installed-plugins inventory above for page builders (which replace the editor) and block libraries (which extend Gutenberg), then ask the user which approach to use: a page builder, Gutenberg, classic theme templates, a child theme, or a custom theme. Ask once and follow that choice; do not mix approaches (e.g. Gutenberg blocks in a page-builder page).',
		];
	}

	/**
	 * Build the ## Available Skills catalog block from all registered sources
	 * (user DB + external plugin sources via the wpcodex_skill_sources filter).
	 */
	private static function build_skill_catalog(): string {
		// Use Sources::discoverable() so external plugin skills are included.
		$agentic = Sources::discoverable( 'agentic' );

		if ( empty( $agentic ) ) {
			return '';
		}

		$lines = [
			'## Available Skills',
			'',
			'Call `wpcodex/skill-read` with the skill name to load its full instructions.',
			'',
		];

		foreach ( $agentic as $skill ) {
			$name         = esc_html( (string) ( $skill['name'] ?? $skill['slug'] ?? '' ) );
			$description  = esc_html( (string) ( $skill['description'] ?? '' ) );
			$source_label = (string) ( $skill['source_label'] ?? '' );

			$badge = ( '' !== $source_label && 'User' !== $source_label )
				? ' [' . esc_html( $source_label ) . ']'
				: '';

			$lines[] = sprintf( '- **`%s`**%s — %s', $name, $badge, $description );
		}

		$lines[] = '';
		return implode( "\n", $lines );
	}
}

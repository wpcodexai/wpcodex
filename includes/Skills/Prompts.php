<?php
/**
 * Skills prompts — registers one MCP prompt-type ability per prompt-enabled skill.
 *
 * The MCP Adapter auto-discovers abilities with meta.mcp.type = 'prompt' and
 * exposes them via the protocol's prompts/list and prompts/get endpoints so AI
 * clients with native prompt support can invoke skills from their prompt menu.
 *
 * @package WPWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WPWorker\Skills;

/**
 * Class Prompts
 *
 * @since 1.0.0
 */
class Prompts {

	/**
	 * Wires the wp_abilities_api_init hook at priority 500.
	 *
	 * Priority 500 — after core abilities (10) and before the collect hook (PHP_INT_MAX).
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register' ], 500 );
	}

	/**
	 * Registers one SkillPromptAbility per discoverable prompt-mode skill.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( Sources::discoverable( 'prompt' ) as $skill ) {
			$slug = (string) ( $skill['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}

			$description = (string) ( $skill['description'] ?? '' );
			$body        = Parser::render_skill_md( [
				'name'           => $slug,
				'description'    => $description,
				'body'           => (string) ( $skill['body'] ?? '' ),
				'enable_prompt'  => (bool) ( $skill['enable_prompt']  ?? true ),
				'enable_agentic' => (bool) ( $skill['enable_agentic'] ?? true ),
			] );

			( new SkillPromptAbility(
				$slug,
				(string) ( $skill['name'] ?? $slug ),
				$description,
				$body,
			) )->register();
		}
	}
}

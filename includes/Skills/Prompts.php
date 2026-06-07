<?php
/**
 * Skills prompts — registers one MCP prompt-type ability per prompt-enabled skill.
 *
 * The MCP Adapter auto-discovers abilities with meta.mcp.type = 'prompt' and
 * exposes them via the protocol's prompts/list and prompts/get endpoints so AI
 * clients with native prompt support can invoke skills from their prompt menu.
 *
 * @package WPCodex\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Skills;

/**
 * Class Prompts
 */
class Prompts {

	/**
	 * Register one ability per discoverable prompt-mode skill.
	 * Called on wp_abilities_api_init at priority 500.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( Sources::discoverable( 'prompt' ) as $skill ) {
			$slug = (string) ( $skill['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}

			$name        = (string) ( $skill['name'] ?? $slug );
			$description = (string) ( $skill['description'] ?? '' );
			$body        = Parser::render_skill_md( [
				'name'           => $slug,
				'description'    => $description,
				'body'           => (string) ( $skill['body'] ?? '' ),
				'enable_prompt'  => (bool) ( $skill['enable_prompt']  ?? true ),
				'enable_agentic' => (bool) ( $skill['enable_agentic'] ?? true ),
			] );

			wp_register_ability( "wpcodex/skill-prompt-{$slug}", [
				'label'       => $name,
				'description' => $description,
				'category'    => 'wpcodex-skills',

				'input_schema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],

				'output_schema' => [
					'type'       => 'object',
					'properties' => [
						'messages' => [ 'type' => 'array' ],
					],
				],

				'execute_callback' => static function () use ( $body ): array {
					return [
						'messages' => [
							[
								'role'    => 'user',
								'content' => [ 'type' => 'text', 'text' => $body ],
							],
						],
					];
				},

				'permission_callback' => [ \WPCodex\Utils\Helpers::class, 'ability_permission' ],

				'meta' => [
					'mcp' => [ 'public' => true, 'type' => 'prompt' ],
				],
			] );
		}
	}
}

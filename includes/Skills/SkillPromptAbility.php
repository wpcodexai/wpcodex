<?php
/**
 * Concrete ability wrapping one prompt-enabled skill.
 *
 * Extends AbstractAbility and overrides get_config() to emit
 * meta.mcp.type = 'prompt' so the MCP Adapter exposes it via the
 * prompts/list and prompts/get endpoints instead of tools/list.
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Skills;

use AllyWorker\Abilities\AbstractAbility;

/**
 * Class SkillPromptAbility
 *
 * @since 1.0.0
 */
class SkillPromptAbility extends AbstractAbility {

	/**
	 * Sets skill data for this prompt ability.
	 *
	 * @since 1.0.0
	 * @param string $slug        Skill slug, used to build the ability name.
	 * @param string $label       Human-readable skill name.
	 * @param string $description Short description shown in the prompt menu.
	 * @param string $body        Rendered Markdown body injected into the user message.
	 */
	public function __construct(
		private string $slug,
		private string $label,
		private string $description,
		private string $body,
	) {}

	/** {@inheritDoc} */
	public function get_name(): string {
		return "allyworker/skill-prompt-{$this->slug}";
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return $this->label;
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return $this->description;
	}

	/** {@inheritDoc} */
	public function get_category(): string {
		return 'allyworker-skills';
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
				'messages' => [ 'type' => 'array' ],
			],
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): array {
		unset( $input );
		return [
			'messages' => [
				[
					'role'    => 'user',
					'content' => [ 'type' => 'text', 'text' => $this->body ],
				],
			],
		];
	}

	/**
	 * Builds the ability config with meta.mcp.type set to 'prompt'.
	 *
	 * @since  1.0.0
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		$config                      = parent::get_config();
		$config['meta']['mcp']['type'] = 'prompt';
		return $config;
	}
}

<?php
/**
 * Ability: wpcodex/skill-list
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Skills;

use WPCodex\Skills\Repository;
use WPCodex\Utils\Helpers;

class SkillList {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/skill-list', [
			'label'       => __( 'List Skills', 'wpcodex' ),
			'description' => __(
				'List all available WPCodex skills with their names, descriptions, and enabled flags. Load this at session start to discover standing instructions for this site.',
				'wpcodex'
			),
			'category'    => 'wpcodex-skills',

			'input_schema'  => [ 'type' => 'object', 'properties' => [], 'required' => [] ],
			'output_schema' => [ 'type' => 'string', 'description' => 'JSON array of skill metadata objects.' ],

			'execute_callback' => static function ( array $args ): string {
				$skills = Repository::instance()->all();
				return wp_json_encode( $skills, JSON_PRETTY_PRINT ) ?: '[]';
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

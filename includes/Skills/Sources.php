<?php
/**
 * Skills source registry — aggregates skills from multiple sources.
 *
 * Sources are registered via the allyworker_skill_sources filter. Each source
 * provides a loader callable that returns a list of skill records.
 *
 * @package AllyWorker
 */

declare( strict_types=1 );

namespace AllyWorker\Skills;

/**
 * Class Sources
 */
class Sources {

	/** Priority for the user DB source — higher = appears later in catalog. */
	public const USER_DB_PRIORITY = 50;

	/**
	 * Return the sorted list of registered skill sources.
	 *
	 * Each entry: { id, priority, label, loader: callable(): array<skill> }
	 * where a skill is: { slug, name, description, body, enable_prompt, enable_agentic }
	 *
	 * @return list<array{id: string, priority: int, label: string, loader: callable}>
	 */
	public static function registry(): array {
		$default = [
			'user-db' => [
				'id'       => 'user-db',
				'priority' => self::USER_DB_PRIORITY,
				'label'    => 'User',
				'loader'   => [ self::class, 'load_user_db' ],
			],
		];

		/** @var array<string, array{id: string, priority: int, label: string, loader: callable}> $sources */
		$sources = apply_filters( 'allyworker_skill_sources', $default );

		$list = array_values( $sources );
		usort( $list, static fn( array $a, array $b ): int => $a['priority'] <=> $b['priority'] );
		return $list;
	}

	/**
	 * Return all skills from all sources with source annotations.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		$result = [];
		foreach ( self::registry() as $entry ) {
			foreach ( ( $entry['loader'] )() as $skill ) {
				$skill['source']       = $entry['id'];
				$skill['source_label'] = $entry['label'];
				$result[]              = $skill;
			}
		}
		return $result;
	}

	/**
	 * Return all skills where description is non-empty and the given flag is on.
	 *
	 * @param 'agentic'|'prompt' $mode
	 * @return list<array<string, mixed>>
	 */
	public static function discoverable( string $mode ): array {
		$key     = 'agentic' === $mode ? 'enable_agentic' : 'enable_prompt';
		$default = 'agentic' === $mode; // agentic defaults on, prompt defaults off.
		$result  = [];

		foreach ( self::all() as $skill ) {
			if ( trim( (string) ( $skill['description'] ?? '' ) ) === '' ) {
				continue;
			}
			if ( trim( (string) ( $skill['body'] ?? '' ) ) === '' ) {
				continue;
			}
			if ( ! ( $skill[ $key ] ?? $default ) ) {
				continue;
			}
			$result[] = $skill;
		}

		return $result;
	}

	/**
	 * Check whether a slug exists in any source except 'user-db'.
	 *
	 * Used before create/write operations to prevent overwriting external sources.
	 *
	 * @param string $slug Normalised skill slug.
	 * @return string|null Source label of the first external match, or null if not found.
	 */
	public static function exists_in_external_source( string $slug ): ?string {
		foreach ( self::registry() as $entry ) {
			if ( 'user-db' === $entry['id'] ) {
				continue;
			}
			foreach ( ( $entry['loader'] )() as $skill ) {
				if ( Parser::normalize_slug( (string) ( $skill['slug'] ?? $skill['name'] ?? '' ) ) === $slug ) {
					return $entry['label'];
				}
			}
		}
		return null;
	}

	/**
	 * Find a single skill by slug across all sources and return it with source annotations.
	 *
	 * The input $slug is normalised before comparison so callers do not need
	 * to pre-process the value they receive from user/agent input.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find( string $slug ): ?array {
		$normalized = Parser::normalize_slug( $slug );
		foreach ( self::registry() as $entry ) {
			foreach ( ( $entry['loader'] )() as $skill ) {
				$skill_slug = Parser::normalize_slug( (string) ( $skill['slug'] ?? $skill['name'] ?? '' ) );
				if ( $skill_slug === $normalized ) {
					$skill['source']       = $entry['id'];
					$skill['source_label'] = $entry['label'];
					return $skill;
				}
			}
		}
		return null;
	}

	/**
	 * Loader for the user DB source — reads from allyworker_skills table.
	 *
	 * @return list<array{slug: string, name: string, description: string, body: string, enable_prompt: bool, enable_agentic: bool}>
	 */
	public static function load_user_db(): array {
		$rows = Repository::instance()->all();
		$result = [];
		foreach ( $rows as $row ) {
			$slug = Parser::normalize_slug( (string) $row['name'] );
			if ( '' === $slug ) {
				continue;
			}
			$result[] = [
				'slug'           => $slug,
				'name'           => (string) $row['name'],
				'description'    => (string) $row['description'],
				'body'           => (string) ( $row['body'] ?? '' ),
				'enable_prompt'  => (bool) $row['enable_prompt'],
				'enable_agentic' => (bool) $row['enable_agentic'],
			];
		}
		return $result;
	}
}

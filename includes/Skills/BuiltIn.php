<?php
/**
 * Built-in skills source — loads bundled SKILL.md files as a read-only source.
 *
 * Registered with the wpcodex_skill_sources filter at priority 10 so built-ins
 * appear before user skills in the catalog.
 *
 * @package WPCodex
 */

declare( strict_types=1 );

namespace WPCodex\Skills;

/**
 * Class BuiltIn
 */
class BuiltIn {

	public const SOURCE_ID       = 'built-in';
	public const SOURCE_LABEL    = 'Built-in';
	public const SOURCE_PRIORITY = 10;

	/**
	 * Wire the wpcodex_skill_sources filter.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'wpcodex_skill_sources', [ $this, 'add_source' ] );
	}

	/**
	 * Register the built-in source entry in the skills source map.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $sources Existing sources keyed by source ID.
	 * @return array<string, mixed> Sources with the built-in entry added.
	 */
	public function add_source( array $sources ): array {
		$sources[ self::SOURCE_ID ] = [
			'id'       => self::SOURCE_ID,
			'priority' => self::SOURCE_PRIORITY,
			'label'    => self::SOURCE_LABEL,
			'loader'   => [ self::class, 'load' ],
		];
		return $sources;
	}

	/**
	 * Load every .md file from includes/Skills/built-in/.
	 * Memoized — called from multiple spots per request.
	 *
	 * @return list<array{slug: string, name: string, description: string, body: string, enable_prompt: bool, enable_agentic: bool}>
	 */
	public static function load(): array {
		static $cached = null;
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = [];
		$dir    = __DIR__ . '/built-in';
		$files  = is_dir( $dir ) ? ( glob( $dir . '/*.md' ) ?: [] ) : [];
		sort( $files );

		foreach ( $files as $path ) {
			$slug = Parser::normalize_slug( basename( $path, '.md' ) );
			if ( '' === $slug ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw = file_get_contents( $path );
			if ( false === $raw ) {
				continue;
			}
			$parsed = Parser::parse( $raw );
			if ( null !== $parsed['parse_error'] ) {
				continue;
			}
			if ( '' === trim( $parsed['body'] ) ) {
				continue;
			}
			$result[] = [
				'slug'           => $slug,
				'name'           => '' !== $parsed['name'] ? $parsed['name'] : $slug,
				'description'    => $parsed['description'],
				'body'           => $parsed['body'],
				'enable_prompt'  => $parsed['enable_prompt'],
				'enable_agentic' => $parsed['enable_agentic'],
			];
		}

		$cached = $result;
		return $result;
	}
}

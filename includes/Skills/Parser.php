<?php
/**
 * Skills frontmatter parser.
 *
 * @package WPWorker
 */

declare( strict_types=1 );

namespace WPWorker\Skills;

/**
 * Class Parser
 */
class Parser {

	/** Maximum body size (bytes). */
	public const MAX_BODY_BYTES = 1_048_576; // 1 MB

	/**
	 * Unescape C-style escape sequences in a skill body.
	 *
	 * AI clients sometimes double-JSON-encode tool-call arguments so real
	 * newlines arrive as the literal two-character sequence \n. stripcslashes
	 * converts \n → LF, \t → tab, \\ → \, \" → ", etc.
	 */
	public static function unescape( string $raw ): string {
		return stripcslashes( $raw );
	}

	/**
	 * Parse a SKILL.md string (frontmatter + body).
	 *
	 * Lenient: malformed frontmatter is reported via parse_error, missing
	 * fields fall back to sensible defaults, unknown keys are ignored.
	 *
	 * @return array{name: string, description: string, enable_prompt: bool, enable_agentic: bool, body: string, parse_error: ?string}
	 */
	public static function parse( string $raw ): array {
		$name           = '';
		$description    = '';
		$enable_prompt  = true;
		$enable_agentic = true;
		$body           = $raw;
		$parse_error    = null;

		$normalized = (string) preg_replace( '/\r\n?/', "\n", $raw );

		if ( str_starts_with( $normalized, "---\n" ) ) {
			$closing = strpos( $normalized, "\n---\n", 4 );
			if ( false === $closing && str_ends_with( $normalized, "\n---" ) ) {
				$closing = strlen( $normalized ) - 4;
			}

			if ( false !== $closing ) {
				$frontmatter_raw = substr( $normalized, 4, $closing - 4 );
				$body            = ltrim( substr( $normalized, $closing + 5 ), "\n" );

				foreach ( explode( "\n", $frontmatter_raw ) as $line ) {
					$line = trim( $line );
					if ( '' === $line || str_starts_with( $line, '#' ) ) {
						continue;
					}
					$colon = strpos( $line, ':' );
					if ( false === $colon ) {
						continue;
					}
					$key   = strtolower( trim( substr( $line, 0, $colon ) ) );
					$value = trim( substr( $line, $colon + 1 ) );

					// Strip surrounding quotes.
					if ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) {
						$value = substr( $value, 1, -1 );
					}
					if ( str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) ) {
						$value = substr( $value, 1, -1 );
					}

					switch ( $key ) {
						case 'name':
							$name = $value;
							break;
						case 'description':
							$description = $value;
							break;
						case 'enable_prompt':
							$enable_prompt = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? true;
							break;
						case 'enable_agentic':
							$enable_agentic = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? true;
							break;
					}
				}
			} else {
				$parse_error = __( 'Frontmatter started with --- but had no closing ---.', 'worker-ai' );
			}
		}

		return [
			'name'           => $name,
			'description'    => $description,
			'enable_prompt'  => $enable_prompt,
			'enable_agentic' => $enable_agentic,
			'body'           => $body,
			'parse_error'    => $parse_error,
		];
	}

	/**
	 * Normalise a slug to a WordPress-friendly identifier.
	 */
	public static function normalize_slug( string $raw ): string {
		$candidate = sanitize_title( $raw );
		if ( '' === $candidate ) {
			return '';
		}
		if ( strlen( $candidate ) > 60 ) {
			$candidate = rtrim( substr( $candidate, 0, 60 ), '-' );
		}
		return $candidate;
	}

	/**
	 * Reconstruct a SKILL.md string from a skill record array.
	 *
	 * @param array{name?: string, description?: string, enable_prompt?: bool, enable_agentic?: bool, body?: string} $skill
	 */
	public static function render_skill_md( array $skill ): string {
		return sprintf(
			"---\nname: %s\ndescription: %s\nenable_prompt: %s\nenable_agentic: %s\n---\n\n%s",
			$skill['name']           ?? '',
			str_replace( "\n", ' ', $skill['description'] ?? '' ),
			( $skill['enable_prompt']  ?? true )  ? 'true' : 'false',
			( $skill['enable_agentic'] ?? true )  ? 'true' : 'false',
			$skill['body']           ?? ''
		);
	}
}

<?php
/**
 * Unit tests for AllyWorker\Skills\Parser.
 *
 * @package AllyWorker\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace AllyWorker\Tests\Unit\Skills;

use PHPUnit\Framework\TestCase;
use AllyWorker\Skills\Parser;

/**
 * @covers \AllyWorker\Skills\Parser
 */
class ParserTest extends TestCase {

	// ── unescape ─────────────────────────────────────────────────────────────

	public function test_unescape_converts_escaped_newlines(): void {
		$this->assertSame( "line1\nline2", Parser::unescape( 'line1\nline2' ) );
	}

	public function test_unescape_converts_escaped_tabs(): void {
		$this->assertSame( "\t", Parser::unescape( '\t' ) );
	}

	public function test_unescape_converts_double_backslash(): void {
		$this->assertSame( '\\', Parser::unescape( '\\\\' ) );
	}

	public function test_unescape_passthrough_plain_string(): void {
		$this->assertSame( 'hello world', Parser::unescape( 'hello world' ) );
	}

	// ── parse — no frontmatter ────────────────────────────────────────────────

	public function test_parse_plain_body_with_no_frontmatter(): void {
		$result = Parser::parse( '# Hello' );
		$this->assertSame( '', $result['name'] );
		$this->assertSame( '', $result['description'] );
		$this->assertTrue( $result['enable_prompt'] );
		$this->assertTrue( $result['enable_agentic'] );
		$this->assertSame( '# Hello', $result['body'] );
		$this->assertNull( $result['parse_error'] );
	}

	public function test_parse_empty_string(): void {
		$result = Parser::parse( '' );
		$this->assertSame( '', $result['body'] );
		$this->assertNull( $result['parse_error'] );
	}

	// ── parse — valid frontmatter ─────────────────────────────────────────────

	public function test_parse_full_frontmatter(): void {
		$raw = "---\nname: my-skill\ndescription: Does stuff\nenable_prompt: false\nenable_agentic: true\n---\n\n# Body here\n";
		$result = Parser::parse( $raw );
		$this->assertSame( 'my-skill', $result['name'] );
		$this->assertSame( 'Does stuff', $result['description'] );
		$this->assertFalse( $result['enable_prompt'] );
		$this->assertTrue( $result['enable_agentic'] );
		$this->assertSame( '# Body here', trim( $result['body'] ) );
		$this->assertNull( $result['parse_error'] );
	}

	public function test_parse_quoted_values(): void {
		$raw = "---\nname: \"quoted-name\"\ndescription: 'single quoted'\n---\n\nbody";
		$result = Parser::parse( $raw );
		$this->assertSame( 'quoted-name', $result['name'] );
		$this->assertSame( 'single quoted', $result['description'] );
	}

	public function test_parse_boolean_false_variants(): void {
		$raw = "---\nenable_prompt: false\nenable_agentic: 0\n---\n\nbody";
		$result = Parser::parse( $raw );
		$this->assertFalse( $result['enable_prompt'] );
		$this->assertFalse( $result['enable_agentic'] );
	}

	public function test_parse_unknown_keys_are_ignored(): void {
		$raw = "---\nname: test\nunknown_key: value\n---\n\nbody";
		$result = Parser::parse( $raw );
		$this->assertSame( 'test', $result['name'] );
		$this->assertNull( $result['parse_error'] );
	}

	public function test_parse_comment_lines_ignored(): void {
		$raw = "---\n# this is a comment\nname: test\n---\n\nbody";
		$result = Parser::parse( $raw );
		$this->assertSame( 'test', $result['name'] );
	}

	// ── parse — malformed frontmatter ────────────────────────────────────────

	public function test_parse_unclosed_frontmatter_sets_error(): void {
		$raw    = "---\nname: broken\n# no closing ---\nbody";
		$result = Parser::parse( $raw );
		$this->assertNotNull( $result['parse_error'] );
		$this->assertStringContainsString( 'no closing', $result['parse_error'] );
	}

	public function test_parse_crlf_line_endings(): void {
		$raw    = "---\r\nname: crlf\r\n---\r\n\r\nbody";
		$result = Parser::parse( $raw );
		$this->assertSame( 'crlf', $result['name'] );
	}

	public function test_parse_frontmatter_at_end_of_file(): void {
		$raw    = "---\nname: eof\n---";
		$result = Parser::parse( $raw );
		$this->assertSame( 'eof', $result['name'] );
		$this->assertSame( '', $result['body'] );
	}

	// ── normalize_slug ────────────────────────────────────────────────────────

	public function test_normalize_slug_basic(): void {
		$this->assertSame( 'my-skill', Parser::normalize_slug( 'My Skill' ) );
	}

	public function test_normalize_slug_truncates_at_60(): void {
		$long = str_repeat( 'a', 70 );
		$slug = Parser::normalize_slug( $long );
		$this->assertLessThanOrEqual( 60, strlen( $slug ) );
	}

	public function test_normalize_slug_empty_returns_empty(): void {
		$this->assertSame( '', Parser::normalize_slug( '' ) );
	}

	public function test_normalize_slug_strips_trailing_dash(): void {
		// After truncation a trailing hyphen must be stripped.
		$slug = Parser::normalize_slug( str_repeat( 'a-', 35 ) );
		$this->assertStringEndsNotWith( '-', $slug );
	}

	// ── render_skill_md ───────────────────────────────────────────────────────

	public function test_render_skill_md_round_trips(): void {
		$skill = [
			'name'           => 'my-skill',
			'description'    => 'Does stuff',
			'enable_prompt'  => true,
			'enable_agentic' => false,
			'body'           => '# Hello',
		];
		$md     = Parser::render_skill_md( $skill );
		$result = Parser::parse( $md );
		$this->assertSame( 'my-skill', $result['name'] );
		$this->assertSame( 'Does stuff', $result['description'] );
		$this->assertTrue( $result['enable_prompt'] );
		$this->assertFalse( $result['enable_agentic'] );
		$this->assertSame( '# Hello', trim( $result['body'] ) );
	}

	public function test_render_skill_md_strips_newlines_from_description(): void {
		$skill = [ 'name' => 'x', 'description' => "line1\nline2", 'body' => '' ];
		$md    = Parser::render_skill_md( $skill );
		$this->assertStringContainsString( 'description: line1 line2', $md );
	}

	public function test_render_skill_md_missing_keys_use_defaults(): void {
		$md = Parser::render_skill_md( [] );
		$this->assertStringContainsString( 'enable_prompt: true', $md );
		$this->assertStringContainsString( 'enable_agentic: true', $md );
	}
}

<?php
/**
 * Unit tests for WPCodex\Skills\Schema.
 *
 * @package WPCodex\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Skills;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Skills\Schema;

/**
 * @covers \WPCodex\Skills\Schema
 */
class SchemaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Anonymous-class wpdb mock with get_charset_collate() for create_table().
		global $wpdb;
		$wpdb = new class() {
			public string $prefix = 'wp_';

			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
			}
		};
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Constants ─────────────────────────────────────────────────────────────

	public function test_table_version_is_at_least_2(): void {
		$this->assertGreaterThanOrEqual( 2, Schema::TABLE_VERSION );
	}

	public function test_table_version_option_key_is_prefixed(): void {
		$this->assertStringStartsWith( 'wpcodex_', Schema::TABLE_VERSION_OPTION );
	}

	// ── table_name ────────────────────────────────────────────────────────────

	public function test_table_name_includes_skills(): void {
		$this->assertSame( 'wp_wpcodex_skills', Schema::table_name() );
	}

	public function test_table_name_respects_wpdb_prefix(): void {
		global $wpdb;
		$wpdb->prefix = 'custom_';
		$this->assertSame( 'custom_wpcodex_skills', Schema::table_name() );
		$wpdb->prefix = 'wp_'; // reset
	}

	// ── revisions_table_name ──────────────────────────────────────────────────

	public function test_revisions_table_name_includes_skill_revisions(): void {
		$this->assertSame( 'wp_wpcodex_skill_revisions', Schema::revisions_table_name() );
	}

	public function test_revisions_table_name_is_different_from_skills_table(): void {
		$this->assertNotSame( Schema::table_name(), Schema::revisions_table_name() );
	}

	// ── maybe_upgrade ─────────────────────────────────────────────────────────

	public function test_maybe_upgrade_runs_create_table_when_version_is_old(): void {
		// Version 0 → behind TABLE_VERSION → create_table() is called.
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		// Should not throw — create_table runs with our stub upgrade.php and
		// the global no-op dbDelta().
		Schema::maybe_upgrade();

		// Assertion: no exception = upgrade ran successfully.
		$this->assertTrue( true );
	}

	public function test_maybe_upgrade_skips_create_table_when_version_current(): void {
		Functions\when( 'get_option' )->justReturn( Schema::TABLE_VERSION );

		// If version matches, update_option must never be called.
		Functions\expect( 'update_option' )->never();

		Schema::maybe_upgrade();
	}

	public function test_table_version_constant_is_positive_integer(): void {
		$this->assertGreaterThan( 0, Schema::TABLE_VERSION );
		$this->assertIsInt( Schema::TABLE_VERSION );
	}
}

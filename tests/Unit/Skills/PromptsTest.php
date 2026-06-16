<?php
/**
 * Unit tests for WPWorker\Skills\Prompts.
 *
 * register() calls wp_register_ability() which requires a live WordPress
 * environment; that path is covered by integration tests.
 * Here we test the constructor hook wiring and that register() is a no-op
 * when wp_register_ability does not exist.
 *
 * @package WPWorker\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace WPWorker\Tests\Unit\Skills;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPWorker\Skills\Prompts;

/**
 * @covers \WPWorker\Skills\Prompts
 */
class PromptsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Constructor ───────────────────────────────────────────────────────────

	public function test_constructor_registers_wp_abilities_api_init_at_priority_500(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_abilities_api_init', \Mockery::type( 'array' ), 500 );

		new Prompts();
	}

	// ── register() — guard ─────────────────────────────────────────────────────

	public function test_register_does_nothing_when_wp_register_ability_absent(): void {
		Functions\when( 'add_action' )->justReturn( true );

		// wp_register_ability is not defined in the test bootstrap, so
		// Prompts::register() should return early without registering anything.
		// We verify no wp_register_ability call is made.
		Functions\expect( 'wp_register_ability' )->never();

		// Sources::discoverable() returns empty when there are no skills.
		Functions\when( 'apply_filters' )->justReturn( [] );

		( new Prompts() )->register();
	}
}

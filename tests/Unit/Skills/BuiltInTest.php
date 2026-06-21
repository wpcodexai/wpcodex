<?php
/**
 * Unit tests for AllyWorker\Skills\BuiltIn.
 *
 * load() touches the filesystem and Parser, so it is covered by integration tests.
 * Here we test the pure parts: constants and add_source().
 *
 * @package AllyWorker\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace AllyWorker\Tests\Unit\Skills;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AllyWorker\Skills\BuiltIn;

/**
 * @covers \AllyWorker\Skills\BuiltIn
 */
class BuiltInTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Constants ─────────────────────────────────────────────────────────────

	public function test_source_id_is_built_in(): void {
		$this->assertSame( 'built-in', BuiltIn::SOURCE_ID );
	}

	public function test_source_label_is_non_empty(): void {
		$this->assertNotEmpty( BuiltIn::SOURCE_LABEL );
	}

	public function test_source_priority_is_positive(): void {
		$this->assertGreaterThan( 0, BuiltIn::SOURCE_PRIORITY );
	}

	// ── Constructor registers filter ───────────────────────────────────────────

	public function test_constructor_registers_allyworker_skill_sources_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'allyworker_skill_sources', \Mockery::type( 'array' ) );

		new BuiltIn();
	}

	// ── add_source ────────────────────────────────────────────────────────────

	public function test_add_source_injects_built_in_key(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$bi      = new BuiltIn();
		$result  = $bi->add_source( [] );

		$this->assertArrayHasKey( BuiltIn::SOURCE_ID, $result );
	}

	public function test_add_source_sets_correct_id(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$bi     = new BuiltIn();
		$result = $bi->add_source( [] );

		$this->assertSame( BuiltIn::SOURCE_ID, $result[ BuiltIn::SOURCE_ID ]['id'] );
	}

	public function test_add_source_sets_correct_priority(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$bi     = new BuiltIn();
		$result = $bi->add_source( [] );

		$this->assertSame( BuiltIn::SOURCE_PRIORITY, $result[ BuiltIn::SOURCE_ID ]['priority'] );
	}

	public function test_add_source_sets_loader_callable(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$bi     = new BuiltIn();
		$result = $bi->add_source( [] );

		$this->assertIsArray( $result[ BuiltIn::SOURCE_ID ]['loader'] );
		$this->assertSame( BuiltIn::class, $result[ BuiltIn::SOURCE_ID ]['loader'][0] );
		$this->assertSame( 'load', $result[ BuiltIn::SOURCE_ID ]['loader'][1] );
	}

	public function test_add_source_preserves_existing_sources(): void {
		Functions\when( 'add_filter' )->justReturn( true );

		$existing = [ 'other-source' => [ 'id' => 'other-source' ] ];
		$bi       = new BuiltIn();
		$result   = $bi->add_source( $existing );

		$this->assertArrayHasKey( 'other-source', $result );
		$this->assertArrayHasKey( BuiltIn::SOURCE_ID, $result );
	}
}

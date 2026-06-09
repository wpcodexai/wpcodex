<?php
/**
 * Unit tests for WPCodex\Utils\Requirements.
 *
 * @package WPCodex\Tests\Unit\Utils
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Utils;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Utils\Requirements;

/**
 * @covers \WPCodex\Utils\Requirements
 */
class RequirementsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_check_returns_true_when_requirements_met(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.9' );
		Functions\when( 'add_action' )->justReturn( true );

		$result = Requirements::check();

		$this->assertTrue( $result );
	}

	public function test_check_returns_false_when_wp_version_too_low(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.0' );
		Functions\when( 'add_action' )->justReturn( true );

		$result = Requirements::check();

		$this->assertFalse( $result );
	}

	public function test_check_registers_admin_notices_when_requirements_not_met(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.0' );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_notices', \Mockery::type( 'callable' ) );

		Requirements::check();
	}

	public function test_check_does_not_register_admin_notices_when_requirements_met(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.9' );

		Functions\expect( 'add_action' )->never();

		Requirements::check();
	}
}

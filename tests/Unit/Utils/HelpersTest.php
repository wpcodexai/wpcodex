<?php
/**
 * Unit tests for Helpers::ability_permission().
 *
 * @package WPCodex\Tests\Unit\Utils
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Utils;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Utils\Helpers;

/**
 * Class HelpersTest
 */
class HelpersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// ability_permission()
	// -------------------------------------------------------------------------

	public function test_returns_true_when_logged_in_with_manage_options(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = Helpers::ability_permission();

		$this->assertTrue( $result );
	}

	public function test_returns_wp_error_when_not_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$result = Helpers::ability_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_not_authenticated', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_returns_wp_error_when_logged_in_without_manage_options(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$result = Helpers::ability_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wpcodex_insufficient_capability', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_current_user_can_is_not_called_when_not_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		// current_user_can should never be called if the user is not logged in.
		Functions\expect( 'current_user_can' )->never();

		Helpers::ability_permission();
	}

	public function test_returns_wp_error_with_correct_message_for_unauthenticated(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$result = Helpers::ability_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'logged in', $result->get_error_message() );
	}

	public function test_returns_wp_error_with_correct_message_for_insufficient_capability(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$result = Helpers::ability_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'manage_options', $result->get_error_message() );
	}
}

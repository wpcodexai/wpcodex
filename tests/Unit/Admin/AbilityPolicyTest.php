<?php
/**
 * Unit tests for WPCodex\Admin\AbilityPolicy.
 *
 * @package WPCodex\Tests\Unit\Admin
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Admin\AbilityPolicy;

/**
 * @covers \WPCodex\Admin\AbilityPolicy
 */
class AbilityPolicyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_registers_high_priority_hook(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_abilities_api_init', \Mockery::type( 'array' ), 9999 );

		new AbilityPolicy();
	}

	public function test_apply_does_nothing_when_wp_abilities_functions_missing(): void {
		// wp_get_abilities and wp_unregister_ability are not defined — apply() should exit silently.
		Functions\when( 'wp_get_abilities' )->justReturn( [] );
		// If wp_unregister_ability were called it would throw — it is stubbed to throw.
		Functions\expect( 'wp_unregister_ability' )->never();
		Functions\when( 'get_option' )->justReturn( 'yes' );
		Functions\when( 'sanitize_key' )->alias( static fn( string $k ): string => $k );

		// Because we stubbed wp_get_abilities as returning [] there's nothing to process.
		( new AbilityPolicy() )->apply();
	}

	public function test_apply_unregisters_disabled_ability(): void {
		Functions\when( 'wp_get_abilities' )->justReturn( [
			'wpcodex/php-execute' => [ 'label' => 'PHP Execute' ],
		] );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $k ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $k ) ?? $k )
		);
		// Ability is disabled.
		Functions\when( 'get_option' )->justReturn( 'no' );
		Functions\expect( 'wp_unregister_ability' )->once()->with( 'wpcodex/php-execute' );

		( new AbilityPolicy() )->apply();
	}

	public function test_apply_does_not_unregister_enabled_ability(): void {
		Functions\when( 'wp_get_abilities' )->justReturn( [
			'wpcodex/php-execute' => [ 'label' => 'PHP Execute' ],
		] );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $k ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $k ) ?? $k )
		);
		Functions\when( 'get_option' )->justReturn( 'yes' );
		Functions\expect( 'wp_unregister_ability' )->never();

		( new AbilityPolicy() )->apply();
	}

	public function test_apply_skips_non_wpcodex_abilities(): void {
		Functions\when( 'wp_get_abilities' )->justReturn( [
			'mcp-adapter/list-tools' => [ 'label' => 'List Tools' ],
			'core/something'         => [ 'label' => 'Core ability' ],
		] );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $k ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $k ) ?? $k )
		);
		Functions\when( 'get_option' )->justReturn( 'no' ); // Would unregister if processed.
		Functions\expect( 'wp_unregister_ability' )->never();

		( new AbilityPolicy() )->apply();
	}

	public function test_apply_only_unregisters_explicitly_disabled_abilities(): void {
		Functions\when( 'wp_get_abilities' )->justReturn( [
			'wpcodex/enabled-ability'  => [],
			'wpcodex/disabled-ability' => [],
		] );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $k ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $k ) ?? $k )
		);
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $default = false ): mixed {
				// disabled-ability → 'no'; everything else → default 'yes'.
				return str_contains( $key, 'disabled' ) ? 'no' : 'yes';
			}
		);
		Functions\expect( 'wp_unregister_ability' )
			->once()
			->with( 'wpcodex/disabled-ability' );

		( new AbilityPolicy() )->apply();
	}
}

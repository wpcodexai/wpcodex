<?php
/**
 * Unit tests for AllyWorker\Skills\Notices.
 *
 * @package AllyWorker\Tests\Unit\Skills
 */

declare( strict_types=1 );

namespace AllyWorker\Tests\Unit\Skills;

use PHPUnit\Framework\TestCase;
use AllyWorker\Skills\Notices;

/**
 * @covers \AllyWorker\Skills\Notices
 */
class NoticesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset transient store before each test.
		$GLOBALS['_allyworker_transients'] = [];
	}

	public function test_pending_reload_notice_returns_null_when_no_transient(): void {
		$this->assertNull( Notices::pending_reload_notice() );
	}

	public function test_set_pending_reload_notice_stores_transient(): void {
		Notices::set_pending_reload_notice();
		$this->assertNotFalse( get_transient( 'allyworker_transient_skill_reload_notice' ) );
	}

	public function test_pending_reload_notice_returns_array_when_transient_set(): void {
		Notices::set_pending_reload_notice();
		$notice = Notices::pending_reload_notice();
		$this->assertIsArray( $notice );
		$this->assertSame( 'info', $notice['type'] );
		$this->assertNotEmpty( $notice['message'] );
	}

	public function test_pending_reload_notice_consumes_transient(): void {
		Notices::set_pending_reload_notice();
		Notices::pending_reload_notice(); // consume
		$this->assertNull( Notices::pending_reload_notice() ); // now gone
	}

	public function test_calling_set_twice_still_yields_one_notice(): void {
		Notices::set_pending_reload_notice();
		Notices::set_pending_reload_notice();
		$first  = Notices::pending_reload_notice();
		$second = Notices::pending_reload_notice();
		$this->assertIsArray( $first );
		$this->assertNull( $second );
	}
}

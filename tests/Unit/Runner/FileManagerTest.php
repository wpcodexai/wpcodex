<?php
/**
 * Unit tests for FileManager.
 *
 * @package WPCodex\Tests\Unit\Runner
 */

declare( strict_types=1 );

namespace WPCodex\Tests\Unit\Runner;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPCodex\Runner\FileManager;

/**
 * Class FileManagerTest
 *
 * Uses a real temp directory so we can test actual filesystem behaviour
 * without touching the WordPress root.
 */
final class FileManagerTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->tmp_dir = sys_get_temp_dir() . '/wpcodex-fm-test-' . uniqid( '', true ) . '/';
		mkdir( $this->tmp_dir, 0755, true );

		// Stub ABSPATH to our temp dir so path validation passes.
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $this->tmp_dir );
		}

		Functions\when( 'wp_mkdir_p' )->alias( static fn( string $dir ) => @mkdir( $dir, 0755, true ) );
		Functions\when( 'wp_is_writable' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( static fn( $v, int $f = 0 ) => json_encode( $v, $f ) );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'DAY_IN_SECONDS' )->justReturn( 86400 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();

		$this->reset_singleton();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$this->remove_dir( $this->tmp_dir );
		$this->reset_singleton();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// read()
	// -------------------------------------------------------------------------

	public function test_read_returns_file_contents(): void {
		$path = $this->tmp_dir . 'test.txt';
		file_put_contents( $path, 'hello' );

		$result = FileManager::instance()->read( $path );
		$this->assertSame( 'hello', $result );
	}

	public function test_read_throws_when_file_not_found(): void {
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->read( $this->tmp_dir . 'nonexistent.txt' );
	}

	// -------------------------------------------------------------------------
	// write()
	// -------------------------------------------------------------------------

	public function test_write_creates_new_file(): void {
		$path = $this->tmp_dir . 'new.txt';
		FileManager::instance()->write( $path, 'content' );
		$this->assertSame( 'content', file_get_contents( $path ) );
	}

	public function test_write_creates_bak_for_existing_file(): void {
		$path = $this->tmp_dir . 'existing.txt';
		file_put_contents( $path, 'original' );
		FileManager::instance()->write( $path, 'updated' );
		$this->assertFileExists( $path . '.bak' );
		$this->assertSame( 'original', file_get_contents( $path . '.bak' ) );
		$this->assertSame( 'updated', file_get_contents( $path ) );
	}

	public function test_write_returns_success_message(): void {
		$path   = $this->tmp_dir . 'msg.txt';
		$result = FileManager::instance()->write( $path, 'hello' );
		$this->assertStringContainsString( 'msg.txt', $result );
	}

	// -------------------------------------------------------------------------
	// edit()
	// -------------------------------------------------------------------------

	public function test_edit_replaces_unique_string(): void {
		$path = $this->tmp_dir . 'edit.txt';
		file_put_contents( $path, 'foo bar baz' );
		FileManager::instance()->edit( $path, 'bar', 'qux' );
		$this->assertSame( 'foo qux baz', file_get_contents( $path ) );
	}

	public function test_edit_throws_when_search_not_found(): void {
		$path = $this->tmp_dir . 'edit2.txt';
		file_put_contents( $path, 'abc' );
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->edit( $path, 'xyz', 'abc' );
	}

	public function test_edit_throws_when_search_appears_multiple_times(): void {
		$path = $this->tmp_dir . 'edit3.txt';
		file_put_contents( $path, 'foo foo foo' );
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->edit( $path, 'foo', 'bar' );
	}

	// -------------------------------------------------------------------------
	// delete()
	// -------------------------------------------------------------------------

	public function test_delete_removes_file(): void {
		$path = $this->tmp_dir . 'del.txt';
		file_put_contents( $path, 'bye' );
		FileManager::instance()->delete( $path );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_delete_throws_when_file_not_found(): void {
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->delete( $this->tmp_dir . 'ghost.txt' );
	}

	// -------------------------------------------------------------------------
	// list()
	// -------------------------------------------------------------------------

	public function test_list_returns_json_array(): void {
		file_put_contents( $this->tmp_dir . 'a.txt', 'a' );
		file_put_contents( $this->tmp_dir . 'b.txt', 'b' );

		$result = FileManager::instance()->list( $this->tmp_dir );
		$decoded = json_decode( $result, true );

		$this->assertIsArray( $decoded );
		$names = array_column( $decoded, 'name' );
		$this->assertContains( 'a.txt', $names );
		$this->assertContains( 'b.txt', $names );
	}

	public function test_list_throws_when_not_a_directory(): void {
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->list( $this->tmp_dir . 'notadir.txt' );
	}

	// -------------------------------------------------------------------------
	// Path traversal protection
	// -------------------------------------------------------------------------

	public function test_read_throws_on_path_traversal(): void {
		$this->expectException( \InvalidArgumentException::class );
		// /etc/passwd is outside ABSPATH (our tmp dir).
		FileManager::instance()->read( '/etc/passwd' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function reset_singleton(): void {
		$prop = new \ReflectionProperty( FileManager::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full = $dir . $entry;
			is_dir( $full ) ? $this->remove_dir( $full . '/' ) : unlink( $full );
		}
		rmdir( $dir );
	}
}
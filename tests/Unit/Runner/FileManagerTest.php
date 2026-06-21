<?php
/**
 * Unit tests for FileManager.
 *
 * Uses a real temp directory (under sys_get_temp_dir()) so actual filesystem
 * behaviour is tested without mocking. All WordPress functions used by
 * FileManager are defined globally in tests/bootstrap.php — no Brain\Monkey
 * needed here.
 *
 * @package AllyWorker\Tests\Unit\Runner
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test setup/teardown requires direct FS calls; WP_Filesystem is not available in unit test context

namespace AllyWorker\Tests\Unit\Runner;

use PHPUnit\Framework\TestCase;
use AllyWorker\Runner\FileManager;

/**
 * @covers \AllyWorker\Runner\FileManager
 */
class FileManagerTest extends TestCase {

	/** Unique temp directory created per test. */
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();

		// tmp_dir lives inside sys_get_temp_dir(), which resolve_path allows.
		$this->tmp_dir = sys_get_temp_dir() . '/allyworker-fm-test-' . bin2hex( random_bytes( 4 ) ) . '/';
		mkdir( $this->tmp_dir, 0755, true );

		$this->reset_singleton();
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp_dir );
		$this->reset_singleton();
		parent::tearDown();
	}

	// ── read_file ─────────────────────────────────────────────────────────────

	public function test_read_file_returns_content(): void {
		$path = $this->tmp_dir . 'test.txt';
		file_put_contents( $path, 'hello' );

		$result = FileManager::instance()->read_file( $path );

		$this->assertIsArray( $result );
		$this->assertSame( 'hello', $result['content'] );
		$this->assertSame( 'utf-8', $result['encoding'] );
		$this->assertSame( $path, $result['path'] );
	}

	public function test_read_file_reports_correct_size(): void {
		$path = $this->tmp_dir . 'sized.txt';
		file_put_contents( $path, 'abcde' );

		$result = FileManager::instance()->read_file( $path );
		$this->assertSame( 5, $result['size'] );
		$this->assertSame( 5, $result['bytes_read'] );
	}

	public function test_read_file_throws_when_file_not_found(): void {
		$this->expectException( \RuntimeException::class );
		FileManager::instance()->read_file( $this->tmp_dir . 'nonexistent.txt' );
	}

	public function test_read_file_reports_not_truncated_for_small_file(): void {
		$path = $this->tmp_dir . 'small.txt';
		file_put_contents( $path, 'short' );

		$result = FileManager::instance()->read_file( $path );
		$this->assertFalse( $result['truncated'] );
	}

	// ── write_file ────────────────────────────────────────────────────────────

	public function test_write_file_creates_new_file(): void {
		$path = $this->tmp_dir . 'new.txt';

		$result = FileManager::instance()->write_file( $path, 'content' );

		$this->assertSame( 'content', file_get_contents( $path ) );
		$this->assertArrayHasKey( 'path', $result );
		$this->assertTrue( $result['created'] );
		$this->assertSame( 7, $result['bytes_written'] );
	}

	public function test_write_file_overwrites_existing_file(): void {
		$path = $this->tmp_dir . 'existing.txt';
		file_put_contents( $path, 'original' );

		FileManager::instance()->write_file( $path, 'updated' );

		$this->assertSame( 'updated', file_get_contents( $path ) );
	}

	public function test_write_file_creates_bak_for_existing_file(): void {
		$path = $this->tmp_dir . 'existing.txt';
		file_put_contents( $path, 'original' );

		FileManager::instance()->write_file( $path, 'updated' );

		$this->assertFileExists( $path . '.bak' );
		$this->assertSame( 'original', file_get_contents( $path . '.bak' ) );
	}

	public function test_write_file_returns_created_false_for_existing_file(): void {
		$path = $this->tmp_dir . 'existing.txt';
		file_put_contents( $path, 'original' );

		$result = FileManager::instance()->write_file( $path, 'updated' );
		$this->assertFalse( $result['created'] );
	}

	public function test_write_file_appends_when_mode_is_append(): void {
		$path = $this->tmp_dir . 'append.txt';
		file_put_contents( $path, 'first' );

		FileManager::instance()->write_file( $path, '-second', 'utf-8', 'append' );

		$this->assertSame( 'first-second', file_get_contents( $path ) );
	}

	public function test_write_file_decodes_base64_content(): void {
		$path = $this->tmp_dir . 'b64.txt';

		FileManager::instance()->write_file( $path, base64_encode( 'decoded!' ), 'base64' );

		$this->assertSame( 'decoded!', file_get_contents( $path ) );
	}

	public function test_write_file_rejects_symlink(): void {
		$real = $this->tmp_dir . 'real.txt';
		$link = $this->tmp_dir . 'link.txt';
		file_put_contents( $real, 'real' );
		symlink( $real, $link );

		$this->expectException( \InvalidArgumentException::class );
		FileManager::instance()->write_file( $link, 'attack' );
	}

	// ── edit_file ─────────────────────────────────────────────────────────────

	public function test_edit_file_replaces_unique_string(): void {
		$path = $this->tmp_dir . 'edit.txt';
		file_put_contents( $path, 'foo bar baz' );

		$result = FileManager::instance()->edit_file( $path, 'bar', 'qux' );

		$this->assertSame( 'foo qux baz', file_get_contents( $path ) );
		$this->assertSame( 1, $result['replacements'] );
	}

	public function test_edit_file_returns_array_with_path_and_size(): void {
		$path = $this->tmp_dir . 'edit2.txt';
		file_put_contents( $path, 'hello world' );

		$result = FileManager::instance()->edit_file( $path, 'world', 'there' );

		$this->assertArrayHasKey( 'path', $result );
		$this->assertArrayHasKey( 'size', $result );
		$this->assertSame( $path, $result['path'] );
	}

	public function test_edit_file_throws_when_old_string_not_found(): void {
		$path = $this->tmp_dir . 'edit3.txt';
		file_put_contents( $path, 'abc' );

		$this->expectException( \RuntimeException::class );
		FileManager::instance()->edit_file( $path, 'xyz', 'abc' );
	}

	public function test_edit_file_throws_when_old_string_appears_multiple_times(): void {
		$path = $this->tmp_dir . 'edit4.txt';
		file_put_contents( $path, 'foo foo foo' );

		$this->expectException( \RuntimeException::class );
		FileManager::instance()->edit_file( $path, 'foo', 'bar' );
	}

	public function test_edit_file_replace_all_replaces_all_occurrences(): void {
		$path = $this->tmp_dir . 'edit5.txt';
		file_put_contents( $path, 'foo foo foo' );

		$result = FileManager::instance()->edit_file( $path, 'foo', 'bar', true );

		$this->assertSame( 'bar bar bar', file_get_contents( $path ) );
		$this->assertSame( 3, $result['replacements'] );
	}

	// ── delete_path ───────────────────────────────────────────────────────────

	public function test_delete_path_removes_file(): void {
		$path = $this->tmp_dir . 'del.txt';
		file_put_contents( $path, 'bye' );

		$result = FileManager::instance()->delete_path( $path );

		$this->assertFileDoesNotExist( $path );
		$this->assertTrue( $result['deleted'] );
		$this->assertSame( 'file', $result['type'] );
		$this->assertSame( 1, $result['items_deleted'] );
	}

	public function test_delete_path_is_idempotent_for_nonexistent_file(): void {
		// Non-existent path should return deleted=false (not throw).
		$result = FileManager::instance()->delete_path( $this->tmp_dir . 'ghost.txt' );

		$this->assertFalse( $result['deleted'] );
		$this->assertSame( 'unknown', $result['type'] );
	}

	public function test_delete_path_throws_for_directory_without_recursive_flag(): void {
		$dir = $this->tmp_dir . 'subdir/';
		mkdir( $dir, 0755, true );

		$this->expectException( \RuntimeException::class );
		FileManager::instance()->delete_path( $dir );
	}

	public function test_delete_path_removes_directory_recursively(): void {
		$dir = $this->tmp_dir . 'subdir/';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . 'file.txt', 'inside' );

		$result = FileManager::instance()->delete_path( $dir, true );

		$this->assertDirectoryDoesNotExist( $dir );
		$this->assertTrue( $result['deleted'] );
	}

	// ── list_directory ────────────────────────────────────────────────────────

	public function test_list_directory_returns_entries_array(): void {
		file_put_contents( $this->tmp_dir . 'a.txt', 'a' );
		file_put_contents( $this->tmp_dir . 'b.txt', 'b' );

		$result = FileManager::instance()->list_directory( $this->tmp_dir );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'entries', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'truncated', $result );
		$this->assertArrayHasKey( 'path', $result );
	}

	public function test_list_directory_contains_expected_files(): void {
		file_put_contents( $this->tmp_dir . 'a.txt', 'a' );
		file_put_contents( $this->tmp_dir . 'b.txt', 'b' );

		$result = FileManager::instance()->list_directory( $this->tmp_dir );

		$names = array_column( $result['entries'], 'name' );
		$this->assertContains( 'a.txt', $names );
		$this->assertContains( 'b.txt', $names );
	}

	public function test_list_directory_throws_when_not_a_directory(): void {
		$file = $this->tmp_dir . 'notadir.txt';
		file_put_contents( $file, 'content' );

		$this->expectException( \RuntimeException::class );
		FileManager::instance()->list_directory( $file );
	}

	public function test_list_directory_returns_correct_entry_structure(): void {
		file_put_contents( $this->tmp_dir . 'test.txt', 'data' );

		$result = FileManager::instance()->list_directory( $this->tmp_dir );
		$entry  = $result['entries'][0];

		$this->assertArrayHasKey( 'name', $entry );
		$this->assertArrayHasKey( 'path', $entry );
		$this->assertArrayHasKey( 'type', $entry );
		$this->assertArrayHasKey( 'size', $entry );
	}

	// ── resolve_path / path traversal ─────────────────────────────────────────

	public function test_read_file_throws_on_absolute_path_outside_allowed_roots(): void {
		$this->expectException( \InvalidArgumentException::class );
		// /etc/passwd is outside both ABSPATH and sys_get_temp_dir.
		FileManager::instance()->read_file( '/etc/passwd' );
	}

	public function test_write_file_throws_on_path_traversal(): void {
		$this->expectException( \InvalidArgumentException::class );
		FileManager::instance()->write_file( '/etc/evil.txt', 'pwned' );
	}

	// ── Singleton ─────────────────────────────────────────────────────────────

	public function test_instance_returns_same_object(): void {
		$a = FileManager::instance();
		$b = FileManager::instance();
		$this->assertSame( $a, $b );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

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
			is_dir( $full ) ? $this->remove_dir( $full . DIRECTORY_SEPARATOR ) : unlink( $full );
		}
		rmdir( $dir );
	}
}

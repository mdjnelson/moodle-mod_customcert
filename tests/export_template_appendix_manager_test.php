<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for mod_customcert\export\template_appendix_manager.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\export\template_appendix_manager;

/**
 * Tests for export\template_appendix_manager.
 *
 * @group      mod_customcert
 * @covers     \mod_customcert\export\template_appendix_manager
 */
final class export_template_appendix_manager_test extends advanced_testcase {
    /** @var template_appendix_manager */
    private template_appendix_manager $manager;
    /** @var string */
    private string $tempdir;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->manager = new template_appendix_manager();
        $this->tempdir = make_temp_directory('customcert_test/' . uniqid(more_entropy: true));
    }

    /**
     * Test that import with no manifest file succeeds silently (no-files export).
     */
    public function test_import_no_manifest_succeeds(): void {
        // No files.json in tempdir — should not throw.
        $this->manager->import(1, $this->tempdir);
        $this->assertFalse($this->manager->find('nonexistent'));
    }

    /**
     * Test that import with an empty manifest succeeds silently.
     */
    public function test_import_empty_manifest_succeeds(): void {
        $manifest = json_encode(['version' => 1, 'files' => []]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $this->manager->import(1, $this->tempdir);
        $this->assertFalse($this->manager->find('nonexistent'));
    }

    /**
     * Test that import with a missing file entry throws an exception.
     */
    public function test_import_missing_file_throws(): void {
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                'abc123' => [
                    'filename' => 'test.png',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid' => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        // No actual file in files/ subdirectory.
        $this->expectException(\Exception::class);
        $this->manager->import(1, $this->tempdir);
    }

    /**
     * Test that import with a file entry missing a filename throws.
     */
    public function test_import_missing_filename_throws(): void {
        $filesdir = $this->tempdir . '/files';
        check_dir_exists($filesdir);
        file_put_contents("$filesdir/abc123", 'fake content');
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                'abc123' => [
                    'filename' => '',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid' => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $this->expectException(\Exception::class);
        $this->manager->import(1, $this->tempdir);
    }

    /**
     * Test that a successfully imported file can be found by its identifier.
     */
    public function test_import_stores_file_and_find_returns_it(): void {
        $filesdir = $this->tempdir . '/files';
        check_dir_exists($filesdir);
        $contenthash = sha1('fake image content');
        file_put_contents("$filesdir/$contenthash", 'fake image content');
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                $contenthash => [
                    'filename' => 'photo.png',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid' => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $contextid = \context_system::instance()->id;
        $this->manager->import($contextid, $this->tempdir);
        $found = $this->manager->find($contenthash);
        $this->assertNotFalse($found);
        $this->assertSame('photo.png', $found->get_filename());
    }

    /**
     * Test that a same-name file with different content is imported under a collision-free name
     * and the pre-existing file is not deleted.
     */
    public function test_import_collision_uses_hash_suffix_and_preserves_existing(): void {
        $fs = get_file_storage();
        $contextid = \context_system::instance()->id;

        // Create a pre-existing file called photo.png with different content.
        $existing = $fs->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'mod_customcert',
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'photo.png',
        ], 'original content');

        // Import a different file that also wants to be called photo.png.
        $filesdir = $this->tempdir . '/files';
        check_dir_exists($filesdir);
        $contenthash = sha1('different content');
        file_put_contents("$filesdir/$contenthash", 'different content');
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                $contenthash => [
                    'filename' => 'photo.png',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid'   => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $this->manager->import($contextid, $this->tempdir);

        // The pre-existing file must still exist.
        $this->assertTrue(
            $fs->file_exists($contextid, 'mod_customcert', 'image', 0, '/', 'photo.png'),
            'Pre-existing photo.png must not be deleted'
        );

        // The imported file must exist under a collision-free name.
        $found = $this->manager->find($contenthash);
        $this->assertNotFalse($found);
        $this->assertStringContainsString($contenthash, $found->get_filename());
        $this->assertNotSame('photo.png', $found->get_filename());
    }

    /**
     * Test that delete_imported_files removes newly created files.
     */
    public function test_delete_imported_files_removes_created_files(): void {
        $filesdir = $this->tempdir . '/files';
        check_dir_exists($filesdir);
        $contenthash = sha1('another fake content');
        file_put_contents("$filesdir/$contenthash", 'another fake content');
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                $contenthash => [
                    'filename' => 'image2.png',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid' => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $contextid = \context_system::instance()->id;
        $this->manager->import($contextid, $this->tempdir);
        $this->assertNotFalse($this->manager->find($contenthash));
        $this->manager->delete_imported_files();
        $this->assertFalse($this->manager->find($contenthash));
    }

    /**
     * Test that get_file_reference returns empty array when file not found.
     */
    public function test_get_file_reference_returns_empty_when_not_found(): void {
        $result = $this->manager->get_file_reference('doesnotexist');
        $this->assertSame([], $result);
    }

    /**
     * Test that get_file_reference returns data array when file is found.
     */
    public function test_get_file_reference_returns_data_when_found(): void {
        $filesdir = $this->tempdir . '/files';
        check_dir_exists($filesdir);
        $contenthash = sha1('ref test content');
        file_put_contents("$filesdir/$contenthash", 'ref test content');
        $manifest = json_encode([
            'version' => 1,
            'files' => [
                $contenthash => [
                    'filename' => 'ref.png',
                    'mimetype' => 'image/png',
                    'filepath' => '/',
                    'itemid' => 0,
                    'filearea' => 'image',
                    'component' => 'mod_customcert',
                ],
            ],
        ]);
        file_put_contents($this->tempdir . '/files.json', $manifest);
        $contextid = \context_system::instance()->id;
        $this->manager->import($contextid, $this->tempdir);
        $ref = $this->manager->get_file_reference($contenthash);
        $this->assertArrayHasKey('filename', $ref);
        $this->assertSame('ref.png', $ref['filename']);
    }
}

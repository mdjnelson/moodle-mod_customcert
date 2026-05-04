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
 * Integration tests for mod_customcert\export\template_file_manager.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use core\clock;
use mod_customcert\export\element;
use mod_customcert\export\import_exception;
use mod_customcert\export\page;
use mod_customcert\export\template as export_template;
use mod_customcert\export\template_appendix_manager;
use mod_customcert\export\template_file_manager;
use mod_customcert\export\template_import_logger_interface;

/**
 * Tests for export\template_file_manager.
 *
 * @group      mod_customcert
 * @covers     \mod_customcert\export\template_file_manager
 */
final class export_template_file_manager_test extends advanced_testcase {
    /** @var clock */
    private clock $clock;
    /** @var template_file_manager */
    private template_file_manager $filemanager;
    /** @var int */
    private int $templateid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        global $DB;
        $this->clock = $this->createMock(clock::class);
        $this->clock->method('time')->willReturn(1000000);
        $logger = $this->createMock(template_import_logger_interface::class);
        $appendixmgr = new template_appendix_manager();
        $element = new element($this->clock, $logger, $appendixmgr);
        $appendixmgr->set_element($element);
        $page = new page($this->clock, $element);
        $template = new export_template($this->clock, $page);
        $this->filemanager = new template_file_manager($appendixmgr, $template);
        $this->templateid = $DB->insert_record('customcert_templates', [
            'name' => 'Test template',
            'contextid' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
    }

    /**
     * Helper: build a valid ZIP in a temp dir containing a template.json.
     *
     * @param array $templatedata
     * @return string Path to the temp dir containing import.zip
     */
    private function make_import_zip(array $templatedata): string {
        $packer = get_file_packer();
        $tempdir = make_temp_directory('customcert_test_zip/' . uniqid(more_entropy: true));
        $json = json_encode($templatedata, JSON_PRETTY_PRINT);
        file_put_contents("$tempdir/template.json", $json);
        $files = ['template.json' => "$tempdir/template.json"];
        $zippath = "$tempdir/import.zip";
        $packer->archive_to_pathname($files, $zippath);
        return $tempdir;
    }

    /**
     * Test that export produces a ZIP file at the returned path.
     */
    public function test_export_returns_zip_path(): void {
        $zippath = $this->filemanager->export($this->templateid);
        $this->assertFileExists($zippath);
        $this->assertStringEndsWith('.zip', $zippath);
    }

    /**
     * Test that the exported ZIP contains template.json.
     */
    public function test_export_zip_contains_template_json(): void {
        $zippath = $this->filemanager->export($this->templateid);
        $packer = get_file_packer();
        $files = $packer->list_files($zippath);
        $names = array_map(fn($f) => $f->pathname, $files);
        $this->assertContains('template.json', $names);
    }

    /**
     * Test that the exported template.json contains the template name.
     */
    public function test_export_template_json_contains_name(): void {
        $zippath = $this->filemanager->export($this->templateid);
        $packer = get_file_packer();
        $extractdir = make_temp_directory('customcert_test_extract/' . uniqid(more_entropy: true));
        $packer->extract_to_pathname($zippath, $extractdir);
        $json = file_get_contents("$extractdir/template.json");
        $data = json_decode($json, true);
        $this->assertSame('Test template', $data['name']);
    }

    /**
     * Test that import from a valid ZIP inserts a template record.
     */
    public function test_import_inserts_template_record(): void {
        $this->preventResetByRollback();
        global $DB;
        $tempdir = $this->make_import_zip([
            'name' => 'Imported via ZIP',
            'pages' => [],
        ]);
        $before = $DB->count_records('customcert_templates');
        $this->filemanager->import(1, $tempdir);
        $after = $DB->count_records('customcert_templates');
        $this->assertSame($before + 1, $after);
        $record = $DB->get_record('customcert_templates', ['name' => 'Imported via ZIP']);
        $this->assertNotFalse($record);
    }

    /**
     * Test that import with pages inserts page records.
     */
    public function test_import_with_pages_inserts_page_records(): void {
        $this->preventResetByRollback();
        global $DB;
        $tempdir = $this->make_import_zip([
            'name' => 'Template with pages',
            'pages' => [
                [
                    'width' => 210,
                    'height' => 297,
                    'leftmargin' => 10,
                    'rightmargin' => 10,
                    'sequence' => 1,
                    'elements' => [],
                ],
            ],
        ]);
        $this->filemanager->import(1, $tempdir);
        $this->assertSame(1, $DB->count_records('customcert_pages'));
    }

    /**
     * Test that import with a missing template.json throws import_exception.
     */
    public function test_import_missing_template_json_throws(): void {
        $packer = get_file_packer();
        $tempdir = make_temp_directory('customcert_test_zip/' . uniqid(more_entropy: true));
        // ZIP with no template.json.
        file_put_contents("$tempdir/dummy.txt", 'nothing');
        $packer->archive_to_pathname(['dummy.txt' => "$tempdir/dummy.txt"], "$tempdir/import.zip");
        $this->expectException(import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    /**
     * Test that import with invalid JSON in template.json throws import_exception.
     */
    public function test_import_invalid_json_throws(): void {
        $packer = get_file_packer();
        $tempdir = make_temp_directory('customcert_test_zip/' . uniqid(more_entropy: true));
        file_put_contents("$tempdir/template.json", 'not valid json {{{{');
        $packer->archive_to_pathname(['template.json' => "$tempdir/template.json"], "$tempdir/import.zip");
        $this->expectException(import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    // Task 4 – Hostile ZIP import tests (ZipArchive, not Moodle packer).

    /**
     * Helper: create a ZIP using ZipArchive with arbitrary (potentially hostile) entry names.
     *
     * @param array<string,string> $entries Map of entry name => content string.
     * @return string Path to the created ZIP file.
     */
    private function make_hostile_zip(array $entries): string {
        $tempdir = make_temp_directory('customcert_hostile_zip/' . uniqid(more_entropy: true));
        $zippath = "$tempdir/import.zip";
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $tempdir;
    }

    /**
     * ZIP with a path-traversal entry ../template.json must be rejected.
     */
    public function test_import_rejects_dotdot_template_json(): void {
        $tempdir = $this->make_hostile_zip([
            '../template.json' => json_encode(['name' => 'evil', 'pages' => []]),
        ]);
        $this->expectException(\mod_customcert\export\import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    /**
     * ZIP with an absolute-path entry /template.json must be rejected.
     */
    public function test_import_rejects_absolute_path_entry(): void {
        $tempdir = $this->make_hostile_zip([
            '/template.json' => json_encode(['name' => 'evil', 'pages' => []]),
        ]);
        $this->expectException(\mod_customcert\export\import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    /**
     * ZIP with a nested traversal entry files/../../evil must be rejected.
     */
    public function test_import_rejects_nested_traversal_entry(): void {
        $tempdir = $this->make_hostile_zip([
            'template.json'    => json_encode(['name' => 'ok', 'pages' => []]),
            'files/../../evil' => 'payload',
        ]);
        $this->expectException(\mod_customcert\export\import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    /**
     * ZIP with a hash-then-traversal entry files/<validhash>/../evil must be rejected.
     */
    public function test_import_rejects_hash_then_traversal_entry(): void {
        $hash = str_repeat('a', 40); // Valid hex-40 string.
        $tempdir = $this->make_hostile_zip([
            'template.json'          => json_encode(['name' => 'ok', 'pages' => []]),
            "files/$hash/../evil"    => 'payload',
        ]);
        $this->expectException(\mod_customcert\export\import_exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    /**
     * ZIP with invalid (non-JSON) files.json must be rejected.
     */
    public function test_import_rejects_invalid_files_json(): void {
        $tempdir = $this->make_hostile_zip([
            'template.json' => json_encode(['name' => 'ok', 'pages' => []]),
            'files.json'    => 'not json at all }{',
        ]);
        $this->expectException(\Exception::class);
        $this->filemanager->import(1, $tempdir);
    }

    // Task 5 – File-backed export/import round-trip.

    /**
     * Export a template that has a stored file (image element), then import it into
     * a fresh context and assert the file is present in Moodle file storage with the
     * correct contenthash.
     */
    public function test_export_import_round_trip_with_stored_file(): void {
        $this->preventResetByRollback();
        global $DB;

        $contextid = \context_system::instance()->id;

        // Store a real file in Moodle file storage under the template context.
        $fs = get_file_storage();
        $filecontent = 'fake-image-content-' . uniqid();
        $expectedhash = sha1($filecontent);
        $filerecord = [
            'contextid' => $contextid,
            'component' => 'mod_customcert',
            'filearea'  => 'image',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test_image.png',
        ];
        $storedfile = $fs->create_file_from_string($filerecord, $filecontent);
        $this->assertSame($expectedhash, $storedfile->get_contenthash());

        // Add a page and a text element to the template.
        $pageid = $DB->insert_record('customcert_pages', [
            'templateid' => $this->templateid,
            'width'      => 210,
            'height'     => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence'   => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        $DB->insert_record('customcert_elements', [
            'pageid'      => $pageid,
            'element'     => 'text',
            'name'        => 'Text element',
            'data'        => json_encode(['text' => 'Hello']),
            'posx'        => 0,
            'posy'        => 0,
            'refpoint'    => 1,
            'alignment'   => 'L',
            'sequence'    => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);

        // Export.
        $zippath = $this->filemanager->export($this->templateid);
        $this->assertFileExists($zippath);

        // Verify ZIP contains template.json.
        $packer = get_file_packer();
        $filelist = $packer->list_files($zippath);
        $names = array_map(fn($f) => $f->pathname, $filelist);
        $this->assertContains('template.json', $names);

        // Import into a fresh context (use system context id + 1 to avoid collision).
        $importdir = make_temp_directory('customcert_test_import/' . uniqid(more_entropy: true));
        copy($zippath, "$importdir/import.zip");

        $templatesbefore = $DB->count_records('customcert_templates');
        $pagesbefore     = $DB->count_records('customcert_pages');
        $elementsbefore  = $DB->count_records('customcert_elements');

        $this->filemanager->import($contextid, $importdir);

        $this->assertSame($templatesbefore + 1, $DB->count_records('customcert_templates'));
        $this->assertSame($pagesbefore + 1, $DB->count_records('customcert_pages'));
        $this->assertSame($elementsbefore + 1, $DB->count_records('customcert_elements'));

        // Verify the imported template record exists.
        $imported = $DB->get_record(
            'customcert_templates',
            ['name' => 'Test template', 'contextid' => $contextid],
            '*',
            IGNORE_MULTIPLE
        );
        $this->assertNotFalse($imported);
    }

    // Task 6 – Rollback test for partial import failure.

    /**
     * When template import fails after files have been stored, delete_imported_files()
     * must clean up and no partial template/page/element records must remain.
     */
    public function test_import_rolls_back_files_on_template_failure(): void {
        $this->preventResetByRollback();
        global $DB;

        // Build a ZIP with valid files.json + image file but invalid template data (missing name).
        $packer = get_file_packer();
        $tempdir = make_temp_directory('customcert_test_zip/' . uniqid(more_entropy: true));
        // Valid template.json but missing 'name' — will cause import_exception after file import.
        $json = json_encode(['pages' => []]);
        file_put_contents("$tempdir/template.json", $json);
        $packer->archive_to_pathname(['template.json' => "$tempdir/template.json"], "$tempdir/import.zip");

        $contextid = \context_system::instance()->id;
        $templatesbefore = $DB->count_records('customcert_templates');
        $pagesbefore     = $DB->count_records('customcert_pages');
        $elementsbefore  = $DB->count_records('customcert_elements');

        $exception = null;
        try {
            $this->filemanager->import($contextid, $tempdir);
        } catch (\mod_customcert\export\import_exception $e) {
            $exception = $e;
        }

        // Exception must have been thrown.
        $this->assertNotNull($exception, 'import_exception must be thrown on missing template name');

        // No partial DB records must remain.
        $this->assertSame($templatesbefore, $DB->count_records('customcert_templates'));
        $this->assertSame($pagesbefore, $DB->count_records('customcert_pages'));
        $this->assertSame($elementsbefore, $DB->count_records('customcert_elements'));
    }

    // Original round-trip test (no file-backed elements).

    /**
     * Test export → import round-trip preserves template name and page count.
     */
    public function test_export_import_round_trip(): void {
        $this->preventResetByRollback();
        global $DB;
        // Add a page to the template.
        $DB->insert_record('customcert_pages', [
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 10,
            'rightmargin' => 10,
            'sequence' => 1,
            'timecreated' => 1000000,
            'timemodified' => 1000000,
        ]);
        // Export.
        $zippath = $this->filemanager->export($this->templateid);
        // Prepare import dir.
        $importdir = make_temp_directory('customcert_test_import/' . uniqid(more_entropy: true));
        copy($zippath, "$importdir/import.zip");
        // Import into a fresh context.
        $templatesbefore = $DB->count_records('customcert_templates');
        $pagesbefore = $DB->count_records('customcert_pages');
        $this->filemanager->import(1, $importdir);
        $this->assertSame($templatesbefore + 1, $DB->count_records('customcert_templates'));
        $this->assertSame($pagesbefore + 1, $DB->count_records('customcert_pages'));
        $imported = $DB->get_record(
            'customcert_templates',
            ['name' => 'Test template', 'contextid' => 1],
            '*',
            IGNORE_MULTIPLE
        );
        $this->assertNotFalse($imported);
    }
}

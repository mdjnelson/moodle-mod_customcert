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
 * Restore-time visual migration boundary coverage for width 0 (#959).
 *
 * Reuses the genuine v1 elements backup fixture (see restore_v1_fixture_test.php for its
 * provenance), rewriting only the 'Border BACKUP' element's width from 12 to 0.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use backup;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Proves an explicit legacy width of 0 survives a real restore as 0, not as absent.
 *
 * @covers \restore_customcert_activity_structure_step::process_customcert_element
 */
final class restore_visual_migration_boundary_test extends advanced_testcase {
    /**
     * An explicit width of 0 (auto-width) must not be indistinguishable from a column that was
     * never set at all, which row_migrator treats as "no visuals to merge".
     */
    public function test_border_width_zero_survives_restore_as_explicit_zero(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fixture = __DIR__ . '/fixtures/backup-moodle2-elements-v1-20260214-1413.mbz';
        $this->assertFileExists($fixture);

        $backupid = 'custe-widthzero-' . uniqid();
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($fixture, $backuppath);

        // Border's historical save_unique_data() duplicates the shared width field into
        // 'data' (see restore_v1_fixture_test.php), so both must be rewritten together.
        $activityxmls = glob($backuppath . '/activities/customcert_*/customcert.xml');
        $this->assertCount(1, $activityxmls);
        $xml = file_get_contents($activityxmls[0]);
        $stripped = preg_replace(
            '#(<name>Border BACKUP</name>\s*<element>border</element>\s*<data>)12(</data>.*?<width>)12(</width>)#s',
            '${1}0${2}0${3}',
            $xml,
            1,
            $count
        );
        $this->assertEquals(1, $count, 'Expected exactly one Border BACKUP width/data pair to rewrite to 0.');
        file_put_contents($activityxmls[0], $stripped);

        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = restore_dbops::create_new_course('Customcert width zero restore', 'custe-w0', $categoryid);

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $customcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $page = $DB->get_record('customcert_pages', ['templateid' => $customcert->templateid], '*', MUST_EXIST);
        $row = $DB->get_record('customcert_elements', ['pageid' => $page->id, 'name' => 'Border BACKUP'], '*', MUST_EXIST);

        $decoded = json_decode((string)$row->data, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('width', $decoded, 'An explicit width of 0 must not be omitted from the migrated payload.');
        $this->assertSame(0, (int)$decoded['width']);
        $this->assertSame('#FBFDBC', $decoded['colour']);
    }
}

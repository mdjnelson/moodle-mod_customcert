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
 * Restore coverage for a backup referencing an uninstalled customcertelement_* type (#959).
 *
 * Reuses the genuine v1 elements backup fixture (see restore_v1_fixture_test.php for its
 * provenance), rewriting only the 'Text BACKUP' element's <element> type to a value not
 * registered on the restore target, so the rest of the genuine 4.5-era row shape (scalar data
 * plus separate font/fontsize/colour/width columns) is otherwise untouched.
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
use mod_customcert\element\element_bootstrap;
use mod_customcert\element\unknown_element;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Proves a missing third-party element type survives restore intact and degrades gracefully.
 *
 * @covers \restore_customcert_activity_structure_step::process_customcert_element
 * @covers \mod_customcert\service\element_repository::load_by_page_id
 */
final class restore_missing_third_party_element_test extends advanced_testcase {
    /** The fictitious type standing in for an uninstalled third-party plugin. */
    private const MISSING_TYPE = 'missing959notinstalled';

    /**
     * Restore the v1 fixture after rewriting one element's type to an unregistered plugin type.
     *
     * @return array{0: int, 1: int} [newcourseid, pageid of the restored template's only page]
     */
    private function restore_v1_fixture_with_missing_type(): array {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fixture = __DIR__ . '/fixtures/backup-moodle2-elements-v1-20260214-1413.mbz';
        $this->assertFileExists($fixture);

        $backupid = 'custe-missing-' . uniqid();
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($fixture, $backuppath);

        // Only the <element> type is rewritten; the rest of the row stays genuinely legacy-shaped.
        $activityxmls = glob($backuppath . '/activities/customcert_*/customcert.xml');
        $this->assertCount(1, $activityxmls);
        $xml = file_get_contents($activityxmls[0]);
        $stripped = preg_replace(
            '#(<name>Text BACKUP</name>\s*<element>)text(</element>)#',
            '${1}' . self::MISSING_TYPE . '${2}',
            $xml,
            1,
            $count
        );
        $this->assertEquals(1, $count, 'Expected exactly one Text BACKUP element type to rewrite.');
        file_put_contents($activityxmls[0], $stripped);

        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = restore_dbops::create_new_course('Customcert missing type restore', 'custe-missing', $categoryid);

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

        return [$newcourseid, (int)$page->id];
    }

    /**
     * Both the row-survival and placeholder-fallback checks share a single restore, since
     * execute_plan() against the fixture is expensive to run twice.
     */
    public function test_missing_type_survives_restore_and_degrades_to_placeholder(): void {
        global $DB;

        [, $pageid] = $this->restore_v1_fixture_with_missing_type();

        $row = $DB->get_record('customcert_elements', ['pageid' => $pageid, 'name' => 'Text BACKUP'], '*', MUST_EXIST);
        $this->assertSame(self::MISSING_TYPE, $row->element);

        // There is no canonical scalar key for an unrecognised type, so process_customcert_element()
        // falls back to the generic 'value' key -- but nothing is lost.
        $decoded = json_decode((string)$row->data, true);
        $this->assertIsArray($decoded, 'Migrated data must still be valid JSON even for an unknown type.');
        $this->assertSame('HEY, THIS IS SOME TEXT.', $decoded['value']);
        $this->assertSame(15, (int)$decoded['width']);
        $this->assertSame('times', $decoded['font']);
        $this->assertSame(21, (int)$decoded['fontsize']);
        $this->assertSame('#8BFB33', $decoded['colour']);

        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);
        $repository = new element_repository(new element_factory($registry));

        $this->resetDebugging();
        $loaded = $repository->load_by_page_id($pageid);
        $this->assertDebuggingCalled(
            "Unknown or invalid element type '" . self::MISSING_TYPE . "', skipping.",
            DEBUG_DEVELOPER
        );

        $matches = array_values(array_filter($loaded, fn ($e) => $e instanceof unknown_element));
        $this->assertCount(1, $matches, 'Expected exactly one unknown_element placeholder for the missing type.');

        $placeholder = $matches[0];
        $html = $placeholder->render_html();
        $this->assertStringContainsString(self::MISSING_TYPE, $html);
    }
}

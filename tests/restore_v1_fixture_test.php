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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use backup;
use restore_controller;
use restore_dbops;
use stdClass;
use context_course;
use context_module;

defined('MOODLE_INTERNAL') || die();

// Ensure backup/restore libs are available when running the test suite.
global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Integration test that restores the v1 elements backup fixture and ensures
 * element data are migrated to the v2 JSON payload during restore.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class restore_v1_fixture_test extends advanced_testcase {
    /**
     * Verify restoring the v1 fixture produces valid JSON payloads with merged visuals.
     */
    public function test_restore_v1_elements_fixture(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fixture = __DIR__ . '/fixtures/backup-moodle2-elements-v1-20260214-1413.mbz';
        $this->assertFileExists($fixture);

        // Extract the backup to a temp directory.
        $backupid = 'custe-v1-' . uniqid();
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($fixture, $backuppath);

        // Restore into a fresh course.
        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = restore_dbops::create_new_course('Customcert v1 restore', 'custe-v1', $categoryid);

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

        // Confirm the module and elements were restored.
        $customcert = $DB->get_record('customcert', ['course' => $newcourseid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $newcourseid, false, MUST_EXIST);
        $coursecontext = context_course::instance($newcourseid);
        $modcontext = context_module::instance($cm->id);
        $pages = $DB->get_records('customcert_pages', ['templateid' => $customcert->templateid]);
        $this->assertNotEmpty($pages);

        $pageids = array_map(fn(stdClass $page) => (int)$page->id, $pages);
        [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
        $elements = $DB->get_records_select('customcert_elements', "pageid $insql", $params, 'id');
        // The fixture contains 18 elements for the target certificate.
        $this->assertCount(18, $elements);

        // Helper to fetch and decode payload.
        $payload = function (string $name) use ($DB): array {
            $row = $DB->get_record('customcert_elements', ['name' => $name], '*', MUST_EXIST);
            $decoded = json_decode((string)$row->data, true);
            $this->assertIsArray($decoded);
            return $decoded;
        };

        $bgimage = $payload('Background image BACKUP');
        $this->assertSame('lancelin_kitesurfing.jpg', $bgimage['filename']);
        $this->assertSame('image', $bgimage['filearea']);
        $this->assertSame('/', $bgimage['filepath']);
        $this->assertSame($coursecontext->id, (int)$bgimage['contextid']);

        $border = $payload('Border BACKUP');
        $this->assertSame(12, (int)$border['value']);
        $this->assertSame(12, (int)$border['width']);
        $this->assertSame('#FBFDBC', $border['colour']);

        $categoryname = $payload('Category name BACKUP');
        $this->assertArrayNotHasKey('value', $categoryname);
        $this->assertSame(15, (int)$categoryname['width']);
        $this->assertSame('helveticab', $categoryname['font']);
        $this->assertSame(21, (int)$categoryname['fontsize']);
        $this->assertSame('#041400', $categoryname['colour']);

        $codebackup = $payload('Code BACKUP');
        $this->assertArrayNotHasKey('value', $codebackup);
        $this->assertSame(12, (int)$codebackup['width']);
        $this->assertSame('times', $codebackup['font']);
        $this->assertSame(20, (int)$codebackup['fontsize']);
        $this->assertSame('#429600', $codebackup['colour']);

        $coursefield = $payload('Course field BACKUP');
        $this->assertSame('shortname', $coursefield['value']);
        $this->assertSame(20, (int)$coursefield['width']);
        $this->assertSame('times', $coursefield['font']);
        $this->assertSame(21, (int)$coursefield['fontsize']);
        $this->assertSame('#69AF00', $coursefield['colour']);

        $coursename = $payload('Course name BACKUP');
        $this->assertSame(2, (int)$coursename['value']);
        $this->assertSame(15, (int)$coursename['width']);
        $this->assertSame('times', $coursename['font']);
        $this->assertSame(21, (int)$coursename['fontsize']);
        $this->assertSame('#021D73', $coursename['colour']);

        $date = $payload('Date BACKUP');
        $this->assertSame('-6', (string)$date['dateitem']);
        $this->assertSame('1', (string)$date['dateformat']);
        $this->assertSame(21, (int)$date['width']);
        $this->assertSame('times', $date['font']);
        $this->assertSame(21, (int)$date['fontsize']);
        $this->assertSame('#027687', $date['colour']);

        $digitalsignature = $payload('Digital signature BACKUP');
        $this->assertSame('Signature name', $digitalsignature['signaturename']);
        $this->assertSame('signaturepassword', $digitalsignature['signaturepassword']);
        $this->assertSame('Signature location', $digitalsignature['signaturelocation']);
        $this->assertSame('Signature reason', $digitalsignature['signaturereason']);
        $this->assertSame('Signature contact info', $digitalsignature['signaturecontactinfo']);
        $this->assertSame(20, (int)$digitalsignature['width']);
        $this->assertSame(20, (int)$digitalsignature['height']);
        $this->assertSame('lancelin_kitesurfing.jpg', $digitalsignature['filename']);

        $expiry = $payload('Expiry BACKUP');
        $this->assertSame('-12', (string)$expiry['dateitem']);
        $this->assertSame('validfor', (string)$expiry['dateformat']);
        $this->assertSame('award', (string)$expiry['startfrom']);
        $this->assertSame(25, (int)$expiry['width']);
        $this->assertSame('times', $expiry['font']);
        $this->assertSame(12, (int)$expiry['fontsize']);
        $this->assertSame('#0C00CD', $expiry['colour']);

        $grade = $payload('Grade BACKUP');
        $gradeitemref = (string)$grade['gradeitem'];
        $this->assertNotSame('', $gradeitemref);
        $resolvedgradeitemid = null;
        if (str_starts_with($gradeitemref, 'gradeitem:')) {
            $resolvedgradeitemid = (int)substr($gradeitemref, 10);
            $this->assertNotFalse(\grade_item::fetch(['id' => $resolvedgradeitemid]));
        } else {
            $cmid = (int)$gradeitemref;
            $this->assertGreaterThan(0, $cmid);
            $restoredcm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $newcourseid]);
            if ($restoredcm) {
                $restoredmod = $DB->get_record('modules', ['id' => $restoredcm->module], '*', MUST_EXIST);
                $gradeitem = \grade_item::fetch([
                    'itemtype' => 'mod',
                    'itemmodule' => $restoredmod->name,
                    'iteminstance' => $restoredcm->instance,
                    'courseid' => $newcourseid,
                    'itemnumber' => 0,
                ]);
                $this->assertNotFalse($gradeitem);
                $resolvedgradeitemid = (int)$gradeitem->id;
            } else {
                // Fallback: treat the value as a grade_item id when no module mapping exists.
                $gradeitem = \grade_item::fetch(['id' => $cmid]);
                $this->assertNotFalse($gradeitem);
                $resolvedgradeitemid = (int)$gradeitem->id;
            }
        }
        $this->assertSame(2, (int)$grade['gradeformat']);
        $this->assertSame(12, (int)$grade['width']);
        $this->assertSame('times', $grade['font']);
        $this->assertSame(21, (int)$grade['fontsize']);
        $this->assertSame('#1ED700', $grade['colour']);

        $gradeitemname = $payload('Grade item name BACKUP');
        $this->assertSame($gradeitemref, (string)$gradeitemname['value']);
        // Validate the grade item name reference resolves to the same grade item id as the grade element.
        $this->assertGreaterThan(0, $resolvedgradeitemid);
        $this->assertSame(21, (int)$gradeitemname['width']);
        $this->assertSame('helveticab', $gradeitemname['font']);
        $this->assertSame(12, (int)$gradeitemname['fontsize']);
        $this->assertSame('#010650', $gradeitemname['colour']);

        $image = $payload('Image BACKUP');
        $this->assertSame(21, (int)$image['width']);
        $this->assertSame(21, (int)$image['height']);
        $this->assertSame(1, (int)$image['alphachannel']);
        $this->assertSame('hiking_spain.jpg', $image['filename']);

        $qrcode = $payload('QR code BACKUP');
        $this->assertSame(21, (int)$qrcode['width']);
        $this->assertSame(21, (int)$qrcode['height']);

        $studentname = $payload('Student name BACKUP');
        $this->assertArrayNotHasKey('value', $studentname);
        $this->assertSame(21, (int)$studentname['width']);
        $this->assertSame('times', $studentname['font']);
        $this->assertSame(12, (int)$studentname['fontsize']);
        $this->assertSame('#6EFCCA', $studentname['colour']);

        $teachername = $payload('Teacher name BACKUP');
        $this->assertSame(2, (int)$teachername['value']);
        $this->assertSame(21, (int)$teachername['width']);
        $this->assertSame('helveticai', $teachername['font']);
        $this->assertSame(16, (int)$teachername['fontsize']);
        $this->assertSame('#000000', $teachername['colour']);

        $text = $payload('Text BACKUP');
        $this->assertSame('HEY, THIS IS SOME TEXT.', $text['value']);
        $this->assertSame(15, (int)$text['width']);
        $this->assertSame('times', $text['font']);
        $this->assertSame(21, (int)$text['fontsize']);
        $this->assertSame('#8BFB33', $text['colour']);

        $userfield = $payload('User field BACKUP');
        $this->assertSame('idnumber', $userfield['value']);
        $this->assertSame(21, (int)$userfield['width']);
        $this->assertSame('helveticab', $userfield['font']);
        $this->assertSame(20, (int)$userfield['fontsize']);
        $this->assertSame('#39FA23', $userfield['colour']);

        $userpicture = $payload('User picture BACKUP');
        $this->assertSame(15, (int)$userpicture['width']);
        $this->assertSame(15, (int)$userpicture['height']);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
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

namespace mod_customcert;

use advanced_testcase;
use context_module;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use moodle_url;
use stdClass;

/**
 * Tests for the recipients report table.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\report_table
 */
final class report_table_test extends advanced_testcase {
    /**
     * Sorting by the expiry column must not break the recipients query.
     *
     * The expiry date is calculated per user in PHP, so it is not a database column and cannot be sorted on.
     */
    public function test_sorting_by_expiry_column_is_ignored(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('customcert', $customcert->id, $course->id, false, MUST_EXIST);

        $template = $DB->get_record('customcert_templates', ['contextid' => context_module::instance($cm->id)->id]);
        $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$template->id));
        $pageid = template_service::create()->add_page($template);

        $element = new stdClass();
        $element->name = 'Expiry';
        $element->element = 'expiry';
        $element->pageid = $pageid;
        $element->data = json_encode(['dateitem' => '-8', 'dateformat' => 'strftimedate', 'startfrom' => '-1']);
        $element->sequence = 1;
        $element->timecreated = time();
        $DB->insert_record('customcert_elements', $element);

        foreach (['alpha', 'bravo'] as $firstname) {
            $student = $this->getDataGenerator()->create_user(['firstname' => $firstname]);
            $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
            $DB->insert_record('customcert_issues', (object) [
                'userid' => $student->id,
                'customcertid' => $customcert->id,
                'code' => 'CODE' . $firstname,
                'emailed' => 0,
                'timecreated' => time(),
            ]);
        }

        $_GET['tsort'] = 'timeexpires';

        $table = new report_table($customcert->id, $cm);
        $table->define_baseurl(new moodle_url('/mod/customcert/view.php', ['id' => $cm->id]));
        $table->setup();
        $table->query_db(10, false);

        $this->assertCount(2, $table->rawdata);
    }
}

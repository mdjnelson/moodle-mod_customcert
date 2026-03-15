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

/**
 * File contains the unit tests for the webservices.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use context_module;
use core_external\external_api;
use stdClass;
use advanced_testcase;

/**
 * Unit tests for the webservices.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_test extends advanced_testcase {
    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        parent::setUp();
    }

    /**
     * Test the delete_issue web service.
     *
     * @covers \external::delete_issue
     */
    public function test_delete_issue(): void {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a custom certificate in the course.
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Create two users.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Enrol them into the course.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);

        // Issue them both certificates.
        $i1 = certificate::issue_certificate($customcert->id, $student1->id);
        $i2 = certificate::issue_certificate($customcert->id, $student2->id);

        $this->assertEquals(2, $DB->count_records('customcert_issues'));

        $result = external::delete_issue($customcert->id, $i2);

        // We need to execute the return values cleaning process to simulate the web service server.
        external_api::clean_returnvalue(external::delete_issue_returns(), $result);

        $issues = $DB->get_records('customcert_issues');
        $this->assertCount(1, $issues);

        $issue = reset($issues);
        $this->assertEquals($student1->id, $issue->userid);
    }

    /**
     * Test the delete_issue web service.
     *
     * @covers \external::delete_issue
     */
    public function test_delete_issue_no_login(): void {
        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a custom certificate in the course.
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Create two users.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Enrol them into the course.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);

        // Issue them both certificates.
        $i1 = certificate::issue_certificate($customcert->id, $student1->id);
        $i2 = certificate::issue_certificate($customcert->id, $student2->id);

        $this->assertEquals(2, $DB->count_records('customcert_issues'));

        // Try and delete without logging in.
        $this->expectException('require_login_exception');
        external::delete_issue($customcert->id, $i2);
    }

    /**
     * Test the delete_issue web service.
     *
     * @covers \external::delete_issue
     */
    public function test_delete_issue_no_capability(): void {
        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a custom certificate in the course.
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Create two users.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->setUser($student1);

        // Enrol them into the course.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);

        // Issue them both certificates.
        $i1 = certificate::issue_certificate($customcert->id, $student1->id);
        $i2 = certificate::issue_certificate($customcert->id, $student2->id);

        $this->assertEquals(2, $DB->count_records('customcert_issues'));

        // Try and delete without the required capability.
        $this->expectException('required_capability_exception');
        external::delete_issue($customcert->id, $i2);
    }

    /**
     * Test list_issues basic behaviour.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_basic(): void {
        $this->setAdminUser();

        // Create course + certificate.
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        // Issue certificate.
        $issueid = certificate::issue_certificate($customcert->id, $student->id);

        // Call the external function.
        $result = external::list_issues(null, null, null, false, 100, 0);
        $result = external_api::clean_returnvalue(external::list_issues_returns(), $result);

        $this->assertCount(1, $result);
        $this->assertEquals($issueid, $result[0]['issue']['id']);
        $this->assertEquals($student->id, $result[0]['user']['id']);
        $this->assertFalse($result[0]['pdf']['haspdf']);
    }

    /**
     * Test list_issues filtered by user.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_filter_by_user(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Create two users.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Enrol both.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id);

        // Issue certificates.
        $i1 = certificate::issue_certificate($customcert->id, $student1->id);
        $i2 = certificate::issue_certificate($customcert->id, $student2->id);

        // Filter by student1.
        $result = external::list_issues(null, $student1->id, null, false, 100, 0);
        $result = external_api::clean_returnvalue(external::list_issues_returns(), $result);

        $this->assertCount(1, $result);
        $this->assertEquals($i1, $result[0]['issue']['id']);
    }

    /**
     * Test list_issues pagination.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_pagination(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        // Issue 3 certificates.
        $ids = [];
        $ids[] = certificate::issue_certificate($customcert->id, $student->id);
        $ids[] = certificate::issue_certificate($customcert->id, $student->id);
        $ids[] = certificate::issue_certificate($customcert->id, $student->id);

        // Get first two with limit=2.
        $result1 = external::list_issues(null, null, null, false, 2, 0);
        $result1 = external_api::clean_returnvalue(external::list_issues_returns(), $result1);

        $this->assertCount(2, $result1);

        // Get final record.
        $result2 = external::list_issues(null, null, null, false, 2, 2);
        $result2 = external_api::clean_returnvalue(external::list_issues_returns(), $result2);

        $this->assertCount(1, $result2);
    }

    /**
     * Test that includepdf works.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_include_pdf(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $issueid = certificate::issue_certificate($customcert->id, $student->id);

        $result = external::list_issues(null, null, null, true, 100, 0);
        $result = external_api::clean_returnvalue(external::list_issues_returns(), $result);

        $this->assertCount(1, $result);
        $this->assertEquals($issueid, $result[0]['issue']['id']);
        $this->assertTrue($result[0]['pdf']['haspdf']);
        $this->assertNotEmpty($result[0]['pdf']['content']);
    }

    /**
     * Test list_issues without logging in.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_no_login(): void {
        // Expect require_login_exception *before* capability check.
        $this->expectException('require_login_exception');

        external::list_issues(null, null, null, false, 100, 0);
    }

    /**
     * Test list_issues without capability.
     *
     * @covers \mod_customcert\external::list_issues
     */
    public function test_list_issues_no_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        // Student without system capability.
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $this->expectException('required_capability_exception');

        external::list_issues();
    }

    /**
     * Helper to create a customcert with a template, page, and element.
     *
     * @param stdClass $course
     * @return array [$customcert, $template, $pageid, $elementid]
     */
    private function create_cert_with_element(stdClass $course): array {
        global $DB;

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templaterecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $template = new template($templaterecord);

        $pageid = $template->add_page();

        $element = new stdClass();
        $element->pageid = $pageid;
        $element->name = 'Test element';
        $element->element = 'text';
        $element->data = 'Sample text';
        $element->font = 'freesans';
        $element->fontsize = 12;
        $element->colour = '#000000';
        $element->posx = 0;
        $element->posy = 0;
        $element->width = 0;
        $element->refpoint = 0;
        $element->sequence = 1;
        $element->timecreated = time();
        $element->timemodified = time();
        $elementid = $DB->insert_record('customcert_elements', $element);

        return [$customcert, $template, $pageid, $elementid];
    }

    /**
     * Test that save_element rejects an element belonging to a different template.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_cross_template_access_denied(): void {
        $this->setAdminUser();

        // Course A with its own certificate and element.
        $coursea = $this->getDataGenerator()->create_course();
        [$certa] = $this->create_cert_with_element($coursea);

        // Course B with its own certificate and element.
        $courseb = $this->getDataGenerator()->create_course();
        [, , , $elementidb] = $this->create_cert_with_element($courseb);

        // Try to save element from Course B using Course A's templateid.
        $this->expectException(\moodle_exception::class);
        external::save_element($certa->templateid, $elementidb, []);
    }

    /**
     * Test that save_element succeeds when element belongs to the given template.
     *
     * @covers \mod_customcert\external::save_element
     */
    public function test_save_element_same_template_allowed(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [$cert, , , $elementid] = $this->create_cert_with_element($course);

        // Should not throw — element belongs to the template.
        $result = external::save_element(
            $cert->templateid,
            $elementid,
            [
                ['name' => 'name', 'value' => 'Updated'],
                ['name' => 'text', 'value' => 'Updated text'],
            ]
        );
        $this->assertTrue($result);
    }

    /**
     * Test that get_element_html rejects an element belonging to a different template.
     *
     * @covers \mod_customcert\external::get_element_html
     */
    public function test_get_element_html_cross_template_access_denied(): void {
        $this->setAdminUser();

        // Course A.
        $coursea = $this->getDataGenerator()->create_course();
        [$certa] = $this->create_cert_with_element($coursea);

        // Course B.
        $courseb = $this->getDataGenerator()->create_course();
        [, , , $elementidb] = $this->create_cert_with_element($courseb);

        // Try to get HTML for element from Course B using Course A's templateid.
        $this->expectException(\moodle_exception::class);
        external::get_element_html($certa->templateid, $elementidb);
    }

    /**
     * Test that get_element_html succeeds when element belongs to the given template.
     *
     * @covers \mod_customcert\external::get_element_html
     */
    public function test_get_element_html_same_template_allowed(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [$cert, , , $elementid] = $this->create_cert_with_element($course);

        // Should not throw — element belongs to the template.
        $result = external::get_element_html($cert->templateid, $elementid);
        $this->assertIsString($result);
    }
}

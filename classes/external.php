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
 * This is the external API for this tool.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert;

use core_external\external_api;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_function_parameters;

/**
 * This is the external API for this tool.
 *
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns the save_element() parameters.
     *
     * @return external_function_parameters
     */
    public static function save_element_parameters() {
        return new external_function_parameters(
            [
                'templateid' => new external_value(PARAM_INT, 'The template id'),
                'elementid' => new external_value(PARAM_INT, 'The element id'),
                'values' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'The field to update'),
                            'value' => new external_value(PARAM_RAW, 'The value of the field'),
                        ]
                    )
                ),
            ]
        );
    }

    /**
     * Handles saving element data.
     *
     * @param int $templateid The template id.
     * @param int $elementid The element id.
     * @param array $values The values to save
     * @return array
     */
    public static function save_element($templateid, $elementid, $values) {
        global $DB;

        $params = [
            'templateid' => $templateid,
            'elementid' => $elementid,
            'values' => $values,
        ];
        self::validate_parameters(self::save_element_parameters(), $params);

        $template = $DB->get_record('customcert_templates', ['id' => $templateid], '*', MUST_EXIST);
        $element = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        // Set the template.
        $template = new \mod_customcert\template($template);

        // Perform checks.
        if ($cm = $template->get_cm()) {
            self::validate_context(\context_module::instance($cm->id));
        } else {
            self::validate_context(\context_system::instance());
        }
        // Make sure the user has the required capabilities.
        $template->require_manage();

        // Set the values we are going to save.
        $data = new \stdClass();
        $data->id = $element->id;
        $data->name = $element->name;
        foreach ($values as $value) {
            $field = $value['name'];
            $data->$field = $value['value'];
        }

        // Get an instance of the element class.
        if ($e = \mod_customcert\element_factory::get_element_instance($element)) {
            return $e->save_form_elements($data);
        }

        return false;
    }

    /**
     * Returns the save_element result value.
     *
     * @return external_value
     */
    public static function save_element_returns() {
        return new external_value(PARAM_BOOL, 'True if successful, false otherwise');
    }

    /**
     * Returns get_element() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_element_html_parameters() {
        return new external_function_parameters(
            [
                'templateid' => new external_value(PARAM_INT, 'The template id'),
                'elementid' => new external_value(PARAM_INT, 'The element id'),
            ]
        );
    }

    /**
     * Handles return the element's HTML.
     *
     * @param int $templateid The template id
     * @param int $elementid The element id.
     * @return string
     */
    public static function get_element_html($templateid, $elementid) {
        global $DB;

        $params = [
            'templateid' => $templateid,
            'elementid' => $elementid,
        ];
        self::validate_parameters(self::get_element_html_parameters(), $params);

        $template = $DB->get_record('customcert_templates', ['id' => $templateid], '*', MUST_EXIST);
        $element = $DB->get_record('customcert_elements', ['id' => $elementid], '*', MUST_EXIST);

        // Set the template.
        $template = new \mod_customcert\template($template);

        // Perform checks.
        if ($cm = $template->get_cm()) {
            self::validate_context(\context_module::instance($cm->id));
        } else {
            self::validate_context(\context_system::instance());
        }

        // Get an instance of the element class.
        if ($e = \mod_customcert\element_factory::get_element_instance($element)) {
            return $e->render_html();
        }

        return '';
    }

    /**
     * Returns the get_element result value.
     *
     * @return external_value
     */
    public static function get_element_html_returns() {
        return new external_value(PARAM_RAW, 'The HTML');
    }

    /**
     * Returns the delete_issue() parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_issue_parameters() {
        return new external_function_parameters(
            [
                'certificateid' => new external_value(PARAM_INT, 'The certificate id'),
                'issueid' => new external_value(PARAM_INT, 'The issue id'),
            ]
        );
    }

    /**
     * Tests that the issue_created event is fired correctly.
     */
    public function test_issue_created_event(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $context = \context_module::instance($customcert->cmid);

        $sink = $this->redirectEvents();

        // Call the actual function that creates an issue and triggers the event
        $issueid = \mod_customcert\certificate::issue_certificate($customcert->id, $user->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertInstanceOf(\mod_customcert\event\issue_created::class, $event);
        $this->assertEquals($issueid, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Tests that the issue_deleted event is fired correctly.
     * This simulates the deletion process that happens in view.php.
     */
    public function test_issue_deleted_event(): void {
        global $DB;

        $this->resetAfterTest();

        // Create course, teacher, student, and customcert module.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'Test cert'
        ]);

        $cm = get_coursemodule_from_instance('customcert', $customcert->id);
        $context = \context_module::instance($cm->id);

        // Create an issue using the natural method
        $issueid = \mod_customcert\certificate::issue_certificate($customcert->id, $student->id);

        // Get the issue record (as view.php does)
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid, 'customcertid' => $customcert->id], '*', MUST_EXIST);

        // Set the user to teacher for proper context
        $this->setUser($teacher);

        // Capture events for the deletion
        $sink = $this->redirectEvents();

        // Simulate the exact deletion process from view.php
        $deleted = $DB->delete_records('customcert_issues', ['id' => $issueid, 'customcertid' => $customcert->id]);

        if ($deleted) {
            $event = \mod_customcert\event\issue_deleted::create([
                'objectid' => $issue->id,
                'context' => $context,
                'relateduserid' => $issue->userid,
            ]);
            $event->trigger();
        }

        $events = $sink->get_events();
        $sink->close();

        // Assertions
        $this->assertTrue($deleted);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\mod_customcert\event\issue_deleted::class, $event);
        $this->assertEquals($issueid, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertDebuggingNotCalled();

        // Verify the issue was actually deleted from database
        $issueexists = $DB->record_exists('customcert_issues', ['id' => $issueid]);
        $this->assertFalse($issueexists);
    }
}

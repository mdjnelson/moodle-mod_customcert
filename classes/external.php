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
     * Handles deleting a customcert issue.
     *
     * @param int $certificateid The certificate id.
     * @param int $issueid The issue id.
     * @return bool
     */
    public static function delete_issue($certificateid, $issueid) {
        global $DB;

        $params = [
            'certificateid' => $certificateid,
            'issueid' => $issueid,
        ];
        self::validate_parameters(self::delete_issue_parameters(), $params);

        $certificate = $DB->get_record('customcert', ['id' => $certificateid], '*', MUST_EXIST);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid, 'customcertid' => $certificateid], '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('customcert', $certificate->id, 0, false, MUST_EXIST);

        // Make sure the user has the required capabilities.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/customcert:manage', $context);

        // Delete the issue.
        $deleted = $DB->delete_records('customcert_issues', ['id' => $issue->id]);

        // Trigger event if deletion succeeded.
        if ($deleted) {
            $event = \mod_customcert\event\issue_deleted::create([
                'objectid' => $issue->id,
                'context' => $context,
                'relateduserid' => $issue->userid,
            ]);
            $event->trigger();
        }

        return $deleted;
    }

    /**
     * Returns the delete_issue result value.
     *
     * @return external_value
     */
    public static function delete_issue_returns() {
        return new external_value(PARAM_BOOL, 'True if successful, false otherwise');
    }

    /**
     * Returns the get_or_create_certificate parameters.
     *
     * @return external_function_parameters
     */
    public static function get_or_create_certificate_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'The course module id'),
            ]
        );
    }

    /**
     * Get or create a certificate for the current user.
     *
     * @param int $cmid The course module id
     * @return array
     */
    public static function get_or_create_certificate($cmid) {
        global $DB, $USER, $CFG;

        $params = [
            'cmid' => $cmid,
        ];
        self::validate_parameters(self::get_or_create_certificate_parameters(), $params);

        // Get the course module, course, and customcert record.
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }
        $course = $DB->get_record('course', ['id' => $cm->course]);
        if (!$course) {
            throw new \moodle_exception('invalidcourseid', 'error');
        }
        $customcert = $DB->get_record('customcert', ['id' => $cm->instance]);
        if (!$customcert) {
            throw new \moodle_exception('invalidrecord', 'mod_customcert', '', ['id' => $cm->instance]);
        }

        // Ensure the user is allowed to view this page.
        self::validate_context(\context_module::instance($cm->id));
        require_capability('mod/customcert:view', \context_module::instance($cm->id));
        $canreceive = has_capability('mod/customcert:receiveissue', \context_module::instance($cm->id));

        // Check if user can receive certificate.
        if (!$canreceive) {
            throw new \moodle_exception('nopermissiontogetcertificate', 'customcert');
        }

        // Check if the user can view the certificate based on time spent in course.
        if ($customcert->requiredtime) {
            if (\mod_customcert\certificate::get_course_time($course->id) < ($customcert->requiredtime * 60)) {
                throw new \moodle_exception('requiredtimenotmet', 'customcert', '', $customcert->requiredtime);
            }
        }

        // Check if an issue already exists for this user and certificate.
        $issue = $DB->get_record('customcert_issues', ['userid' => $USER->id, 'customcertid' => $customcert->id], '*', IGNORE_MISSING);
        
        // If no issue exists, create one.
        if (!$issue) {
            $issueid = \mod_customcert\certificate::issue_certificate($customcert->id, $USER->id);
            $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', IGNORE_MISSING);
            if (!$issue) {
                throw new \moodle_exception('no new issue', 'error');
            }
        }

        // Generate the download URL. TODO: replace with wsfunction https://github.com/mdjnelson/moodle-mod_customcert/pull/681
        $downloadurl = $CFG->wwwroot . '/mod/customcert/view.php?id=' . $cm->id . '&downloadown=1';

        // Return the issue data and download URL.
        return [
            'issueid' => $issue->id,
            'code' => $issue->code,
            'timecreated' => $issue->timecreated,
            'downloadurl' => $downloadurl,
        ];
    }

    /**
     * Returns the get_or_create_certificate result value.
     *
     * @return external_single_structure
     */
    public static function get_or_create_certificate_returns() {
        return new external_single_structure([
            'issueid' => new external_value(PARAM_INT, 'issue id'),
            'code' => new external_value(PARAM_TEXT, 'certificate code'),
            'timecreated' => new external_value(PARAM_INT, 'time created'),
            'downloadurl' => new external_value(PARAM_URL, 'download URL'),
        ]);
    }
}

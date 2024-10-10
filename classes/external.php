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
     * Returns list_issues parameters.
     *
     * @return external_function_parameters
     */
    public static function list_issues_parameters() {
        return new external_function_parameters(
            [
                'timecreatedfrom' => new external_value(
                    PARAM_INT,
                    'Timestamp. Returns items created after this date (included).',
                    VALUE_OPTIONAL
                ),
                'userid' => new external_value(
                    PARAM_INT,
                    'User id. Returns items for this user.',
                    VALUE_OPTIONAL
                ),
                'customcertid' => new external_value(
                    PARAM_INT,
                    'Customcert id. Returns items for this customcert.',
                    VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * Returns array of issued certificates.
     *
     * @param ?int $timecreatedfrom Timestamp. Returns items created after this date (included).
     * @param ?int $userid User id. Returns items for this user.
     * @param ?int $customcertid Customcert id. Returns items for this customcert.
     * @return array
     */
    public static function list_issues($timecreatedfrom = null, $userid = null, $customcertid = null) {
        global $DB;

        $params = [
            'timecreatedfrom' => $timecreatedfrom,
            'userid' => $userid,
            'customcertid' => $customcertid,
        ];
        self::validate_parameters(self::list_issues_parameters(), $params);

        $context = \context_system::instance();
        require_capability('mod/customcert:viewallcertificates', $context);

        $output = [];

        list($namefields, $nameparams) = \core_user\fields::get_sql_fullname();
        $sql = "SELECT ci.*,
                       $namefields as fullname, u.username, u.email,
                       ct.id as templateid, ct.name as templatename, ct.contextid
                  FROM {customcert_issues} ci
                  JOIN {user} u
                    ON ci.userid = u.id
                  JOIN {customcert} c
                    ON ci.customcertid = c.id
                  JOIN {customcert_templates} ct
                    ON c.templateid = ct.id";
        $conditions = [];
        if ($timecreatedfrom) {
            $conditions[] = "ci.timecreated >= :timecreatedfrom";
            $nameparams['timecreatedfrom'] = $timecreatedfrom;
        }
        if ($userid) {
            $conditions[] = "ci.userid = :userid";
            $nameparams['userid'] = $userid;
        }
        if ($customcertid) {
            $conditions[] = "ci.customcertid = :customcertid";
            $nameparams['customcertid'] = $customcertid;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        if ($issues = $DB->get_records_sql($sql, $nameparams)) {

            foreach ($issues as $issue) {

                // Generate PDF.

                $template = new \stdClass();
                $template->id = $issue->templateid;
                $template->name = $issue->templatename;
                $template->contextid = $issue->contextid;
                $template = new \mod_customcert\template($template);

                $ctname = str_replace(' ', '_', mb_strtolower($template->get_name()));
                $pdfname = $ctname . '_' . 'certificate.pdf';
                $filecontents = $template->generate_pdf(false, $issue->userid, true);

                $output[] = [
                    'issue' => [
                        'id' => $issue->id,
                        'customcertid' => $issue->customcertid,
                        'code' => $issue->code,
                        'emailed' => $issue->emailed,
                        'timecreated' => $issue->timecreated,
                    ],
                    'user' => [
                        'id' => $issue->userid,
                        'fullname' => $issue->fullname,
                        'username' => $issue->username,
                        'email' => $issue->email,
                    ],
                    'template' => [
                        'id' => $issue->templateid,
                        'name' => $issue->templatename,
                        'contextid' => $issue->contextid,
                    ],
                    'pdf' => [
                        'name' => $pdfname,
                        'content' => base64_encode($filecontents),
                    ],
                ];
            }

        }

        return $output;
    }

    /**
     * Returns the list_issues result value.
     *
     * @return external_multiple_structure
     */
    public static function list_issues_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'issue' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'issue id'),
                    'customcertid' => new external_value(PARAM_INT, 'customcert id'),
                    'code' => new external_value(PARAM_TEXT, 'code'),
                    'emailed' => new external_value(PARAM_BOOL, 'emailed'),
                    'timecreated' => new external_value(PARAM_INT, 'time created'),
                ]),
                'user' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'id of user'),
                    'fullname' => new external_value(PARAM_TEXT, 'fullname'),
                    'username' => new external_value(PARAM_TEXT, 'username'),
                    'email' => new external_value(PARAM_TEXT, 'email'),
                ]),
                'template' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'template id'),
                    'name' => new external_value(PARAM_TEXT, 'template name'),
                    'contextid' => new external_value(PARAM_INT, 'context id'),
                ]),
                'pdf' => new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'name'),
                    'content' => new external_value(PARAM_TEXT, 'base64 content'),
                ]),
            ])
        );
    }
}

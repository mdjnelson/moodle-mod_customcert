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
 * Assignment feedback element for mod_customcert.
 *
 * Displays the grader's text feedback from an assignment submission
 * on a generated certificate.
 *
 * @package    customcertelement_assignfeedback
 * @copyright  2026 Joe Rebbeck
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element assignfeedback class.
 */
class element extends \mod_customcert\element {

    public function render_form_elements($mform): void {
        global $COURSE;
        $assignments = self::get_course_assignments($COURSE->id);
        $mform->addElement('select', 'assignid',
            get_string('assignment', 'customcertelement_assignfeedback'), $assignments);
        $mform->addHelpButton('assignid', 'assignment', 'customcertelement_assignfeedback');
        parent::render_form_elements($mform);
    }

    public function validate_form_elements($data, $files): array {
        $errors = parent::validate_form_elements($data, $files);
        if (empty($data['assignid'])) {
            $errors['assignid'] = get_string('noassignmentselected', 'customcertelement_assignfeedback');
        }
        return $errors;
    }

    public function save_form_elements($data): void {
        $this->set_data((string) $data->assignid);
        parent::save_form_elements($data);
    }

    public function set_form_elements_data($mform): void {
        $mform->setDefault('assignid', $this->get_data());
        parent::set_form_elements_data($mform);
    }

    public function preview_text(): string {
        return get_string('previewtext', 'customcertelement_assignfeedback');
    }

    public function render($pdf, $preview, $user): void {
        $feedbacktext = $preview ? $this->preview_text() : $this->get_feedback_for_user($user);
        \mod_customcert\element_helper::render_content($pdf, $this, $feedbacktext);
    }

    protected function get_feedback_for_user(\stdClass $user): string {
        global $DB;
        $assignid = (int) $this->get_data();
        if (!$assignid || !$DB->record_exists('assign', ['id' => $assignid])) {
            return '';
        }
        $submission = $DB->get_record_sql(
            "SELECT s.id FROM {assign_submission} s
              WHERE s.assignment = :assignid AND s.userid = :userid AND s.status = 'submitted'
           ORDER BY s.timemodified DESC",
            ['assignid' => $assignid, 'userid' => $user->id],
            IGNORE_MULTIPLE
        );
        if (!$submission) {
            return get_string('nofeedbackavailable', 'customcertelement_assignfeedback');
        }
        $feedbackrecord = $DB->get_record('assignfeedback_comments', [
            'assignment' => $assignid,
            'grade'      => $this->get_grade_id($assignid, $user->id),
        ]);
        if (!$feedbackrecord || empty($feedbackrecord->commenttext)) {
            return get_string('nofeedbackavailable', 'customcertelement_assignfeedback');
        }
        return strip_tags($feedbackrecord->commenttext);
    }

    protected function get_grade_id(int $assignid, int $userid) {
        global $DB;
        $grade = $DB->get_record('assign_grades',
            ['assignment' => $assignid, 'userid' => $userid], 'id', IGNORE_MISSING);
        return $grade ? $grade->id : false;
    }

    protected static function get_course_assignments(int $courseid): array {
        global $DB;
        $records = $DB->get_records('assign', ['course' => $courseid], 'name ASC', 'id, name');
        $options = ['' => get_string('chooseassignment', 'customcertelement_assignfeedback')];
        foreach ($records as $record) {
            $options[$record->id] = format_string($record->name);
        }
        return $options;
    }
}

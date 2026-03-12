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
 * on a generated certificate PDF.
 *
 * @package    customcertelement_assignfeedback
 * @copyright  2026 Joe Rebbeck <joerebbeck@hotmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element assignfeedback class.
 *
 * Renders the text feedback a grader left on a student's assignment
 * submission directly onto the certificate PDF.
 *
 * @package    customcertelement_assignfeedback
 * @copyright  2026 Joe Rebbeck <joerebbeck@hotmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * Render the element settings form fields.
     *
     * Adds a select dropdown so the certificate designer can choose which
     * assignment's feedback to display on the certificate.
     *
     * @param \MoodleQuickForm $mform The form object to add fields to.
     * @return void
     */
    public function render_form_elements($mform): void {
        global $COURSE;

        $assignments = self::get_course_assignments($COURSE->id);

        $mform->addElement(
            'select',
            'assignid',
            get_string('assignment', 'customcertelement_assignfeedback'),
            $assignments
        );
        $mform->addHelpButton('assignid', 'assignment', 'customcertelement_assignfeedback');

        parent::render_form_elements($mform);
    }

    /**
     * Validate the data submitted via the element settings form.
     *
     * @param array $data  Associative array of raw submitted form data.
     * @param array $files Array of uploaded files (not used by this element).
     * @return array       Associative array of field name => validation error string.
     */
    public function validate_form_elements($data, $files): array {
        $errors = parent::validate_form_elements($data, $files);

        if (empty($data['assignid'])) {
            $errors['assignid'] = get_string('noassignmentselected', 'customcertelement_assignfeedback');
        }

        return $errors;
    }

    /**
     * Save the chosen assignment ID into the element's persistent data field.
     *
     * The assignment ID is cast to string because the base element stores
     * all custom data as a single text value.
     *
     * @param \stdClass $data Form data returned by {@see MoodleQuickForm::get_data()}.
     * @return void
     */
    public function save_form_elements($data): void {
        $this->set_data((string) $data->assignid);
        parent::save_form_elements($data);
    }

    /**
     * Populate the form with the saved assignment ID when editing an existing element.
     *
     * @param \MoodleQuickForm $mform The form whose defaults are to be set.
     * @return void
     */
    public function set_form_elements_data($mform): void {
        $mform->setDefault('assignid', $this->get_data());
        parent::set_form_elements_data($mform);
    }

    /**
     * Return a representative preview string for the certificate editor.
     *
     * This is shown instead of real feedback when the certificate is being
     * previewed without a specific student context.
     *
     * @return string A localised sample feedback string.
     */
    public function preview_text(): string {
        return get_string('previewtext', 'customcertelement_assignfeedback');
    }

    /**
     * Render the element onto the PDF.
     *
     * In preview mode a static sample string is used. In normal mode the
     * real feedback text for the given user is fetched and written to the PDF.
     *
     * @param \pdf      $pdf     The Moodle PDF object currently being rendered.
     * @param bool      $preview True when rendering a preview (no real student context).
     * @param \stdClass $user    The student whose certificate is being generated.
     * @return void
     */
    public function render($pdf, $preview, $user): void {
        if ($preview) {
            $feedbacktext = $this->preview_text();
        } else {
            $feedbacktext = $this->get_feedback_for_user($user);
        }

        \mod_customcert\element_helper::render_content($pdf, $this, $feedbacktext);
    }

    /**
     * Retrieve the grader's plain-text feedback for a given user.
     *
     * The lookup is driven entirely by the assign_grades record rather than
     * the submission status, ensuring we always retrieve the feedback that
     * corresponds to the actual graded attempt rather than a submission that
     * may be in an intermediate state.
     *
     * Steps:
     *  1. Confirm the stored assignment ID is valid.
     *  2. Fetch the assign_grades row for this user - if none exists the
     *     assignment has not been graded yet.
     *  3. Fetch the matching assignfeedback_comments row keyed on that
     *     grade record ID.
     *  4. Strip HTML and return the plain text.
     *
     * @param \stdClass $user The student whose feedback is required.
     * @return string The plain-text feedback, or a localised "not available" string.
     */
    protected function get_feedback_for_user(\stdClass $user): string {
        global $DB;

        $assignid = (int) $this->get_data();

        if (!$assignid) {
            return '';
        }

        // Confirm the assignment still exists (it may have been deleted after
        // the element was configured).
        if (!$DB->record_exists('assign', ['id' => $assignid])) {
            return '';
        }

        // Drive the lookup from the grade record, not the submission status.
        // assign_grades is the authoritative record that the grader has acted
        // on and is more reliable than filtering submissions by status.
        $grade = $DB->get_record(
            'assign_grades',
            ['assignment' => $assignid, 'userid' => $user->id],
            'id',
            IGNORE_MISSING
        );

        if (!$grade) {
            // No grade record means the assignment has not been graded yet.
            return get_string('nofeedbackavailable', 'customcertelement_assignfeedback');
        }

        // Fetch the feedback comments that belong to this specific grade record.
        $feedbackrecord = $DB->get_record(
            'assignfeedback_comments',
            ['assignment' => $assignid, 'grade' => $grade->id],
            '*',
            IGNORE_MISSING
        );

        if (!$feedbackrecord || empty($feedbackrecord->commenttext)) {
            return get_string('nofeedbackavailable', 'customcertelement_assignfeedback');
        }

        // Strip HTML so the text renders cleanly as plain text inside the PDF.
        return strip_tags($feedbackrecord->commenttext);
    }

    /**
     * Return an array of assignments in a course, suitable for a select element.
     *
     * Returns assignments ordered alphabetically by name, with a blank
     * "choose" option prepended.
     *
     * @param int $courseid The ID of the course to query.
     * @return array Associative array of assignment ID => display name.
     */
    protected static function get_course_assignments(int $courseid): array {
        global $DB;

        $records = $DB->get_records(
            'assign',
            ['course' => $courseid],
            'name ASC',
            'id, name'
        );

        $options = ['' => get_string('chooseassignment', 'customcertelement_assignfeedback')];
        foreach ($records as $record) {
            $options[$record->id] = format_string($record->name);
        }

        return $options;
    }
}

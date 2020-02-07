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
 * This file contains the customcert element assignmentanswer's core interaction API.
 *
 * @package    customcertelement_assignmentanswer
 * @copyright  2019 Yorick Reum <yorick.reum@uni-wuerzburg.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignmentanswer;

use context;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element assignmentanswer's core interaction API.
 *
 * @package    customcertelement_assignmentanswer
 * @copyright  2019 Yorick Reum <yorick.reum@uni-wuerzburg.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element
{

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        $mform->addElement('select', 'assignmentanswer', get_string('assignmentanswer', 'customcertelement_assignmentanswer'),
            \mod_customcert\element_helper::get_grade_items($COURSE));
        $mform->addHelpButton('assignmentanswer', 'assignmentanswer', 'customcertelement_assignmentanswer');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        if (!empty($data->assignmentanswer)) {
            return $data->assignmentanswer;
        }

        return '';
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // Check that the assignment answer is not empty.
        if (!empty($this->get_data())) {
            \mod_customcert\element_helper::render_content($pdf, $this, $this->get_assignment_answer($user));
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        global $USER;
        // Check that the assignment answer is not empty.
        if (!empty($this->get_data())) {
            return \mod_customcert\element_helper::render_html_content($this, $this->get_assignment_answer($USER));
        }

        return '';
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data())) {
            $element = $mform->getElement('assignmentanswer');
            $element->setValue($this->get_data());
        }
        parent::definition_after_data($mform);
    }

    /**
     * Helper function that returns the category name.
     *
     * @param stdClass $user A {@link $USER} object to get full name of.
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_assignment_answer($user): string {
        global $DB;

        // Get the course module information (the assignment activity).
        $cm = $DB->get_record('course_modules', array('id' => $this->get_data()), '*', MUST_EXIST);

        // Get the assignment submission id.
        $submissionid = $DB->get_field('assign_submission', 'id',
            array('assignment' => $cm->instance,
                'userid' => $user->id,
                'status' => 'submitted')
        );

        // Get the assignment answer, fallback to "not answered" string.
        $answer = $DB->get_field('assignsubmission_onlinetext', 'onlinetext',
            array('submission' => $submissionid));
        if (!$submissionid || !$answer) {
            $answer = get_string('noassignmentanswer', 'customcertelement_assignmentanswer');
        }

        return format_string($answer, true, ['context' => \mod_customcert\element_helper::get_context($this->get_id())]);
    }
}

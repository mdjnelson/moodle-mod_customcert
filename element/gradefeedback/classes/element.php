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
 * This file contains the customcert element gradefeedback's core interaction API.
 *
 * @package    customcertelement_gradefeedback
 * @copyright  2019 ISYC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_gradefeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element gradefeedback's core interaction API.
 *
 * @package    customcertelement_gradefeedback
 * @copyright  2019 ISYC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        $mform->addElement('select', 'gradefeedback', get_string('gradefeedback', 'customcertelement_gradefeedback'),
            \mod_customcert\element_helper::get_grade_items($COURSE));
        $mform->addHelpButton('gradefeedback', 'gradefeedback', 'customcertelement_gradefeedback');

        $mform->addElement('text', 'gradefeedbacklength',
            get_string('gradefeedbacklength', 'customcertelement_gradefeedback'), array('size' => 10));
        $mform->setType('gradefeedbacklength', PARAM_INT);
        $mform->addHelpButton('gradefeedbacklength', 'gradefeedbacklength', 'customcertelement_gradefeedback');

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
        $arrtostore = array(
            'gradefeedback' => $data->gradefeedback,
            'gradefeedbacklength' => $data->gradefeedbacklength
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // Check that the grade item is not empty.
        if (!empty($this->get_data())) {
            \mod_customcert\element_helper::render_content($pdf, $this, $this->get_grade_feedback($user));
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
        // Check that the grade item is not empty.
        if (!empty($this->get_data())) {
            return \mod_customcert\element_helper::render_html_content($this, $this->get_grade_feedback());
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
            $itemid = json_decode($this->get_data());

            $element = $mform->getElement('gradefeedback');
            $element->setValue($itemid->gradefeedback);

            $element = $mform->getElement('gradefeedbacklength');
            $element->setValue($itemid->gradefeedbacklength);
        }
        parent::definition_after_data($mform);
    }

    /**
     * Helper function that returns the category name.
     *
     * @return string
     */
    protected function get_grade_feedback($user = null) : string {
        global $DB;

        $itemid = json_decode($this->get_data());
        $gradeitemlength = $itemid->gradefeedbacklength;

        if (strpos($itemid->gradefeedback, 'gradeitem:') === false) {
            $cm = $DB->get_record('course_modules', array('id' => $itemid->gradefeedback), '*', MUST_EXIST);
            $module = $DB->get_record('modules', array('id' => $cm->module), '*', MUST_EXIST);
            $itemname = $DB->get_field($module->name, 'name', array('id' => $cm->instance), MUST_EXIST);
            $gradeitem = $DB->get_record('grade_items',
                array('iteminstance' => $cm->instance, 'courseid' => $cm->course, 'itemmodule' => $module->name), '*', MUST_EXIST);
        } else {
            $gradeitemid = str_replace ('gradeitem:' , '', $itemid->gradefeedback);
            $gradeitem = $DB->get_record('grade_items', array('id' => $gradeitemid), '*', MUST_EXIST);
            $itemname = $gradeitem->itemname;
        }

        if ($user) {
            $grade = $DB->get_record('grade_grades', array('itemid' => $gradeitem->id, 'userid' => $user->id), '*');
            if (!empty($grade)) {
                $gradefeedback = $grade->feedback;
            } else {
                $gradefeedback = '';
            }
        } else {
            $gradefeedback = 'Feedback item: ' . $itemname;
        }

        if ($gradefeedback <> '' AND $gradeitemlength > 0 AND strlen($gradefeedback) >= $gradeitemlength) {
            $gradefeedback = substr($gradefeedback, 0, $gradeitemlength) . '...';
        }

        return format_string($gradefeedback, true, ['context' => \mod_customcert\element_helper::get_context($this->get_id())] );
    }
}

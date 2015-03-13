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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/customcert/element/element.class.php');
require_once($CFG->dirroot . '/mod/customcert/element/grade/lib.php');

/**
 * The customcert element gradeitemname's core interaction API.
 *
 * @package    customcertelement_gradeitemname
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customcert_element_gradeitemname extends customcert_element_base {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        $mform->addElement('select', 'gradeitem', get_string('gradeitem', 'customcertelement_gradeitemname'),
            customcert_element_grade::get_grade_items());
        $mform->addHelpButton('gradeitem', 'gradeitem', 'customcertelement_gradeitemname');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        if (!empty($data->gradeitem)) {
            return $data->gradeitem;
        }

        return '';
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     */
    public function render($pdf, $preview) {
        global $DB;

        // Check that the grade item is not empty.
        if (!empty($this->element->data)) {
            // Get the course module information.
            $cm = $DB->get_record('course_modules', array('id' => $this->element->data), '*', MUST_EXIST);
            $module = $DB->get_record('modules', array('id' => $cm->module), '*', MUST_EXIST);

            // Get the name of the item.
            $itemname = $DB->get_field($module->name, 'name', array('id' => $cm->instance), MUST_EXIST);

            parent::render_content($pdf, $itemname);
        }
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        $this->element->gradeitem = $this->element->data;
        parent::definition_after_data($mform);
    }
}

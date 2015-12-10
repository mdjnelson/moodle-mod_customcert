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

/**
 * The customcert element teachername's core interaction API.
 *
 * @package    customcertelement_teachername
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customcert_element_teachername extends customcert_element_base {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        $mform->addElement('select', 'teacher', get_string('teacher', 'customcertelement_teachername'),
            $this->get_list_of_teachers());
        $mform->addHelpButton('teacher', 'teacher', 'customcertelement_teachername');

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
        return $data->teacher;
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     */
    public function render($pdf, $preview) {
        global $DB;

        $teacher = $DB->get_record('user', array('id' => $this->element->data));
        $teachername = fullname($teacher);

        parent::render_content($pdf, $teachername);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     */
    public function render_html() {
        global $DB;

        $teacher = $DB->get_record('user', array('id' => $this->element->data));
        $teachername = fullname($teacher);

        return parent::render_html_content($teachername);
    }

    /**
     * Helper function to return the teachers for this course.
     *
     * @return array the list of teachers
     */
    private function get_list_of_teachers() {
        // When editing this element the cmid will be present in the URL.
        $cmid = required_param('cmid', PARAM_INT);

        // The list of teachers to return.
        $teachers = array();

        // Now return all users who can manage the customcert in this context.
        if ($users = get_users_by_capability(context_module::instance($cmid), 'mod/customcert:manage')) {
            foreach ($users as $user) {
                $teachers[$user->id] = fullname($user);
            }
        }

        return $teachers;
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->element->data)) {
            $this->element->teacher = $this->element->data;
        }
        parent::definition_after_data($mform);
    }
}

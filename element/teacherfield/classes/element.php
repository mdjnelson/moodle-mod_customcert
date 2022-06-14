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
 * This file contains the customcert element teacherfield's core interaction API.
 *
 * @package    customcertelement_teacherfield
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_teacherfield;

use core_user\fields;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element teacherfield's core interaction API.
 *
 * @package    customcertelement_teacherfield
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
    	  //Get the teachers list
    	  $mform->addElement('select', 'teacher', get_string('teacher', 'customcertelement_teacherfield'),
        		$this->get_list_of_teachers());
        $mform->addHelpButton('teacher', 'teacher', 'customcertelement_teacherfield');
        
        // Get the user profile fields.
        $teacherfields = array(
            'firstname' => fields::get_display_name('firstname'),
            'lastname' => fields::get_display_name('lastname'),
            'username' => fields::get_display_name('username'),
            'email' => fields::get_display_name('email'),
            'city' => fields::get_display_name('city'),
            'country' => fields::get_display_name('country'),
            'url' => fields::get_display_name('url'),
            'skype' => fields::get_display_name('skype'),
            'idnumber' => fields::get_display_name('idnumber'),
            'institution' => fields::get_display_name('institution'),
            'department' => fields::get_display_name('department'),
            'phone1' => fields::get_display_name('phone1'),
            'phone2' => fields::get_display_name('phone2'),
            'address' => fields::get_display_name('address')
        );
        // Get the user custom fields.
        $arrcustomfields = \availability_profile\condition::get_custom_profile_fields();
        $customfields = array();
        foreach ($arrcustomfields as $key => $customfield) {
            $customfields[$customfield->id] = $customfield->name;
        }
        // Combine the two.
        $fields = $teacherfields + $customfields;
        \core_collator::asort($fields);

        // Create the select box where the user field is selected.
        $mform->addElement('select', 'teacherfield', get_string('teacherfield', 'customcertelement_teacherfield'), $fields);
        $mform->setType('teacherfield', PARAM_ALPHANUM);
        $mform->addHelpButton('teacherfield', 'teacherfield', 'customcertelement_teacherfield');

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
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'teacher' => $data->teacher,
            'teacherfield' => $data->teacherfield
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
        
        // Decode the information stored in the database.
        $teacherinfo = json_decode($this->get_data());
        $teacher = $teacherinfo->teacher;
        $teacherfield = $teacherinfo->teacherfield;
        
              
        //global $DB;

        //$teacherobj = $DB->get_record('user', array('id' => $teacherinfo->teacher));
        //$teachername = fullname($teacher);
        				

        \mod_customcert\element_helper::render_content($pdf, $this, $this->get_user_field_value($user, $preview));
        //\mod_customcert\element_helper::render_content($pdf, $this, $teacherfield);
        
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     */
    public function render_html() {
        global $USER;

        return \mod_customcert\element_helper::render_html_content($this, $this->get_user_field_value($USER, true));
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
    	  if (!empty($this->get_data())) {
            $teacherinfo = json_decode($this->get_data());

            $element = $mform->getElement('teacher');
            $element->setValue($teacherinfo->teacher);

            $element = $mform->getElement('teacherfield');
            $element->setValue($teacherinfo->teacherfield);
        }

        parent::definition_after_data($mform);
    }
   
    /**
     * Helper function to return the teachers for this course.
     *
     * @return array the list of teachers
     */
    protected function get_list_of_teachers() {
        global $PAGE;

        // Return early if we are in a site template.
        if ($PAGE->context->id == \context_system::instance()->id) {
            return [];
        }

        // The list of teachers to return.
        $teachers = array();

        // Now return all users who can manage the customcert in this context.
        if ($users = get_enrolled_users($PAGE->context, 'mod/customcert:manage')) {
            foreach ($users as $user) {
                $teachers[$user->id] = fullname($user);
            }
        }

        return $teachers;
    }
    
    /**
     * Helper function that returns the text.
     *
     * @param \stdClass $user the user we are rendering this for
     * @param bool $preview Is this a preview?
     * @return string
     */
    protected function get_user_field_value(\stdClass $user, bool $preview) : string {
        global $CFG, $DB;

        // Decode the information stored in the database.
        $teacherinfo = json_decode($this->get_data());
        $teacher = $teacherinfo->teacher;
        // The user field to display.
        $field = $teacherinfo->teacherfield;
        
        // The value to display - we always want to show a value here so it can be repositioned.
        if ($preview) {
            $value = $field;
        } else {
            $value = '';
        }
        if (is_number($field)) { // Must be a custom user profile field.
            if ($field = $DB->get_record('user_info_field', array('id' => $field))) {
                // Found the field name, let's update the value to display.
                $value = $field->name;
                $file = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
                if (file_exists($file)) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    require_once($file);
                    $class = "profile_field_{$field->datatype}";
                    $field = new $class($field->id, $teacher);
                    $value = $field->display_data();
                }
            }
        } else if (!empty($user->$field)) { // Field in the user table.
            $value = $user->$field;
        }

        $context = \mod_customcert\element_helper::get_context($this->get_id());
        return format_string($value, true, ['context' => $context]);
    }
}

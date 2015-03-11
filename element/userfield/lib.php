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
 * The customcert element userfield's core interaction API.
 *
 * @package    customcertelement_userfield
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customcert_element_userfield extends customcert_element_base {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        // Get the user profile fields.
        $userfields = array(
            'firstname' => get_user_field_name('firstname'),
            'lastname' => get_user_field_name('lastname'),
            'email' => get_user_field_name('email'),
            'city' => get_user_field_name('city'),
            'country' => get_user_field_name('country'),
            'url' => get_user_field_name('url'),
            'icq' => get_user_field_name('icq'),
            'skype' => get_user_field_name('skype'),
            'aim' => get_user_field_name('aim'),
            'yahoo' => get_user_field_name('yahoo'),
            'msn' => get_user_field_name('msn'),
            'idnumber' => get_user_field_name('idnumber'),
            'institution' => get_user_field_name('institution'),
            'department' => get_user_field_name('department'),
            'phone1' => get_user_field_name('phone1'),
            'phone2' => get_user_field_name('phone2'),
            'address' => get_user_field_name('address')
        );
        // Get the user custom fields.
        $arrcustomfields = \availability_profile\condition::get_custom_profile_fields();
        $customfields = array();
        foreach ($arrcustomfields as $key => $customfield) {
            $customfields[$customfield->id] = $key;
        }
        // Combine the two.
        $fields = $userfields + $customfields;
        customcert_perform_asort($fields);

        // Create the select box where the user field is selected.
        $mform->addElement('select', 'userfield', get_string('userfield', 'customcertelement_userfield'), $fields);
        $mform->setType('userfield', PARAM_ALPHANUM);
        $mform->addHelpButton('userfield', 'userfield', 'customcertelement_userfield');

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
        return $data->userfield;
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     */
    public function render($pdf, $preview) {
        global $DB, $USER;

        // The user field to display.
        $field = $this->element->data;
        // The value to display on the PDF.
        $value = '';
        if (is_number($field)) { // Must be a custom user profile field.
            if ($field = $DB->get_record('user_info_field', array('id' => $field))) {
                $value = $USER->profile[$field->shortname];
            }
        } else if (!empty($USER->$field)) { // Field in the user table.
            $value = $USER->$field;
        }

        parent::render_content($pdf, $value);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        $this->element->userfield = $this->element->data;
        parent::definition_after_data($mform);
    }
}

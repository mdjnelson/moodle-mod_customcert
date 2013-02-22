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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.


/**
 * The base class for the customcert elements.
 *
 * @package    mod
 * @subpackage customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class customcert_element_base {

    /**
     * The data for the element we are adding.
     */
    public $element;

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    function __construct($element) {
        $this->element = new stdClass();
        $this->element = $element;
    }

    /**
     * This function is responsible for adding the element for the first time
     * to the database when no data has yet been specified, default values set.
     * Can be overriden if more functionality is needed.
     *
     * @param string $element the name of the element
     * @param int $pageid the page id we are saving it to
     */
    public static function add_element($element, $pageid) {
        global $DB;

        $data = new stdClass();
        $data->pageid = $pageid;
        $data->element = $element;
        $data->font = 'Times-Roman';
        $data->size = '12';
        $data->colour = 'FFFFFF';
        $data->posx = '250';
        $data->posy = '250';
        $data->timecreated = time();

        $DB->insert_record('customcert_elements', $data);
    }

    /**
     * This function renders the form elements when adding a customcert element.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $mform the edit_form instance.
     * @return array the form elements
     */
    public function render_form_elements($mform) {
        // Keep track of the number of times these elements have been
        // added, so we only add the help icon once.
        static $numtimesadded = 0;

        // The identifier.
        $id = $this->element->id;

        // The common group of elements.
        $group = array();
        $group[] = $mform->createElement('select', 'font_' . $id, '', customcert_get_fonts());
        $group[] = $mform->createElement('select', 'size_' . $id, '', customcert_get_font_sizes());
        $group[] = $mform->createELement('text', 'colour_' . $id, '', array('size' => 10, 'maxlength' => 6));
        $group[] = $mform->createElement('text', 'posx_' . $id, '', array('size' => 10));
        $group[] = $mform->createElement('text', 'posy_' . $id, '', array('size' => 10));

        // Add this group.
        $mform->addElement('group', 'elementfieldgroup_' . $id, get_string('pluginname', 'customcertelement_' . $this->element->element),
            $group, array(' ' . get_string('fontsize', 'customcert') . ' ', ' ' . get_string('colour', 'customcert') . ' ',
                          ' ' . get_string('posx', 'customcert') . ' ', ' ' . get_string('posy', 'customcert') . ' '), false);

        $this->set_form_element_types($mform);

        if ($numtimesadded == 0) {
            $mform->addHelpButton('elementfieldgroup_' . $id, 'commonformelements', 'customcert');
        }

        $numtimesadded++;
	}

    /**
     * Performs validation on the element values.
     * Can be overridden if more functionality is needed.
     *
     * @param array $data the submitted data
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = array();

        // The identifier.
        $id = $this->element->id;

        // Get the group name.
        $group = 'elementfieldgroup_' . $id;

        // Get the colour.
        $colour = 'colour_' . $id;
        $colour = $data[$colour];
        $colour = ltrim($colour, "#");
        // Check if colour is not a valid hexadecimal value.
        if(!preg_match("/[0-9A-F]{6}/i", $colour)) {
            $errors[$group] = get_string('invalidcolour', 'customcert');
        }

        // Get position X.
        $posx = 'posx_' . $id;
        $posx = $data[$posx];
        // Check if posx is not numeric or less than 0.
        if ((!is_numeric($posx)) || ($posx < 0)) {
            if (!empty($errors[$group])) {
                $errors[$group] .= "<br />";
                $errors[$group] .= get_string('invalidposition', 'customcert', 'X');
            } else {
                $errors[$group] = get_string('invalidposition', 'customcert', 'X');
            }
        }

        // Get position Y.
        $posy = 'posy_' . $id;
        $posy = $data[$posy];
        // Check if posy is not numeric or less than 0.
        if ((!is_numeric($posy)) || ($posy < 0)) {
            if (!empty($errors[$group])) {
                $errors[$group] .= "<br />";
                $errors[$group] .= get_string('invalidposition', 'customcert', 'Y');
            } else {
                $errors[$group] = get_string('invalidposition', 'customcert', 'Y');
            }
        }

        return $errors;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overriden if more functionality is needed.
     *
     * @param stdClass $data the form data.
     */
    public function save_form_elements($data) {
        global $DB;

        // The identifier.
        $id = $this->element->id;

        // Get the name of the fields we want from the form.
        $datainfo = $this->save_unique_data($data);
        $font = 'font_' . $id;
        $size = 'size_' . $id;
        $colour = 'colour_' . $id;
        $posx = 'posx_' . $id;
        $posy = 'posy_' . $id;

        // Get the data from the form.
        $element = new stdClass();
        $element->id = $id;
        $element->data = $datainfo;
        $element->font = (!empty($data->$font)) ? $data->$font : null;
        $element->size = (!empty($data->$size)) ? $data->$size : null;
        $element->colour = (!empty($data->$colour)) ? ltrim($data->$colour, "#") : null;
        $element->posx = (!empty($data->$posx)) ? $data->$posx : null;
        $element->posy = (!empty($data->$posy)) ? $data->$posy : null;

        // Ok, now update record in the database.
        $DB->update_record('customcert_elements', $element);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert column.
     * Can be overriden if more functionality is needed.
     *
     * @param stdClass $data the form data.
     */
    public function save_unique_data($data) {
        return null;
    }

    /**
     * Handles displaying the element on the pdf.
     * Must be overriden.
     *
     * @param stdClass the pdf object, see lib/pdflib.php
     */
    public function display($pdf) {
        // Must be overriden.
        return false;
    }

    /**
     * Handles deleting any data this element may have introduced.
     * Can be overriden if more functionality is needed.
     *
     * @return bool success return true if deletion success, false otherwise
     */
    public function delete_element() {
        global $DB;

        return $DB->delete_records('customcert_elements', array('id' => $this->element->id));
    }


    /**
     * Helper function that sets the types, defaults and rules for the common elements.
     *
     * @param stdClass $mform the edit_form instance.
     * @return array the form elements
     */
    public function set_form_element_types($mform) {

        // The identifier.
        $id = $this->element->id;

        // Set the types of these elements.
        $mform->setType('font_' . $id, PARAM_TEXT);
        $mform->setType('size_' . $id, PARAM_INT);
        $mform->setType('colour_' . $id, PARAM_RAW); // Need to validate this is a hexadecimal value.
        $mform->setType('posx_' . $id, PARAM_INT);
        $mform->setType('posy_' . $id, PARAM_INT);

        // Add some rules.
        $grouprule = array();
        $grouprule['colour_' . $id][] = array(null, 'required', null, 'client');
        $grouprule['posx_' . $id][] = array(null, 'required', null, 'client');
        $grouprule['posy_' . $id][] = array(null, 'required', null, 'client');
        $mform->addGroupRule('elementfieldgroup_' . $id, $grouprule);

        // Set the values of these elements.
        $mform->setDefault('font_' . $id, $this->element->font);
        $mform->setDefault('size_' . $id, $this->element->size);
        $mform->setDefault('colour_' . $id, $this->element->colour);
        $mform->setDefault('posx_' . $id, $this->element->posx);
        $mform->setDefault('posy_' . $id, $this->element->posy);
    }
}
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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

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
        $data->colour = '#FFFFFF';
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
     */
    public function render_form_elements($mform) {
        // The identifier.
        $id = $this->element->id;

        // Commonly used string.
        $strrequired = get_string('required');

        // The common elements.
        $mform->addElement('select', 'font_' . $id, get_string('font', 'customcert'), customcert_get_fonts());
        $mform->addElement('select', 'size_' . $id, get_string('fontsize', 'customcert'), customcert_get_font_sizes());
        $mform->addElement('customcert_colourpicker', 'colour_' . $id, get_string('fontcolour', 'customcert'));
        $mform->addElement('text', 'posx_' . $id, get_string('posx', 'customcert'), array('size' => 10));
        $mform->addElement('text', 'posy_' . $id, get_string('posy', 'customcert'), array('size' => 10));

        // Set the types of these elements.
        $mform->setType('font_' . $id, PARAM_TEXT);
        $mform->setType('size_' . $id, PARAM_INT);
        $mform->setType('colour_' . $id, PARAM_RAW); // Need to validate that this is a valid colour.
        $mform->setType('posx_' . $id, PARAM_INT);
        $mform->setType('posy_' . $id, PARAM_INT);

        // Add some rules.
        $mform->addRule('colour_' . $id, $strrequired, 'required', null, 'client');
        $mform->addRule('posx_' . $id, $strrequired, 'required', null, 'client');
        $mform->addRule('posy_' . $id, $strrequired, 'required', null, 'client');

        // Set the values of these elements.
        $mform->setDefault('font_' . $id, $this->element->font);
        $mform->setDefault('size_' . $id, $this->element->size);
        $mform->setDefault('colour_' . $id, $this->element->colour);
        $mform->setDefault('posx_' . $id, $this->element->posx);
        $mform->setDefault('posy_' . $id, $this->element->posy);

        // Add help buttons.
        $mform->addHelpButton('font_' . $id, 'font', 'customcert');
        $mform->addHelpButton('size_' . $id, 'fontsize', 'customcert');
        $mform->addHelpButton('colour_' . $id, 'fontcolour', 'customcert');
        $mform->addHelpButton('posx_' . $id, 'posx', 'customcert');
        $mform->addHelpButton('posy_' . $id, 'posy', 'customcert');
	}

    /**
     * Performs validation on the element values.
     * Can be overridden if more functionality is needed.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = array();

        // The identifier.
        $id = $this->element->id;

        // Validate the colour.
        $colour = 'colour_' . $id;
        $colourdata = $data[$colour];
        if (!$this->validate_colour($colourdata)) {
            $errors[$colour] = get_string('invalidcolour', 'customcert');
        }

        // Get position X.
        $posx = 'posx_' . $id;
        $posxdata = $data[$posx];
        // Check if posx is not numeric or less than 0.
        if ((!is_numeric($posxdata)) || ($posxdata < 0)) {
            $errors[$posx] = get_string('invalidposition', 'customcert', 'X');
        }

        // Get position Y.
        $posy = 'posy_' . $id;
        $posydata = $data[$posy];
        // Check if posy is not numeric or less than 0.
        if ((!is_numeric($posydata)) || ($posydata < 0)) {
            $errors[$posy] = get_string('invalidposition', 'customcert', 'Y');
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
        $element->colour = (!empty($data->$colour)) ? $data->$colour : null;
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
     * Handles rendering the element on the pdf.
     * Must be overriden.
     *
     * @param stdClass $pdf the pdf object
     * @param int $userid
     */
    public function render($pdf, $userid) {
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
     * Validates the colour selected.
     *
     * @param string $data
     * @return string|false
     */
    protected function validate_colour($colour) {
        /**
         * List of valid HTML colour names
         *
         * @var array
         */
         $colournames = array(
            'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure',
            'beige', 'bisque', 'black', 'blanchedalmond', 'blue',
            'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
            'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson',
            'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray',
            'darkgrey', 'darkgreen', 'darkkhaki', 'darkmagenta',
            'darkolivegreen', 'darkorange', 'darkorchid', 'darkred',
            'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray',
            'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
            'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick',
            'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
            'ghostwhite', 'gold', 'goldenrod', 'gray', 'grey', 'green',
            'greenyellow', 'honeydew', 'hotpink', 'indianred', 'indigo',
            'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen',
            'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
            'lightgoldenrodyellow', 'lightgray', 'lightgrey', 'lightgreen',
            'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue',
            'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow',
            'lime', 'limegreen', 'linen', 'magenta', 'maroon',
            'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
            'mediumseagreen', 'mediumslateblue', 'mediumspringgreen',
            'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream',
            'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive',
            'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod',
            'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip',
            'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'red',
            'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
            'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue',
            'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan',
            'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
            'whitesmoke', 'yellow', 'yellowgreen'
        );

        if (preg_match('/^#?([[:xdigit:]]{3}){1,2}$/', $colour)) {
            return true;
        } else if (in_array(strtolower($colour), $colournames)) {
            return true;
        } else if (preg_match('/rgb\(\d{0,3}%?\, ?\d{0,3}%?, ?\d{0,3}%?\)/i', $colour)) {
            return true;
        } else if (preg_match('/rgba\(\d{0,3}%?\, ?\d{0,3}%?, ?\d{0,3}%?\, ?\d(\.\d)?\)/i', $colour)) {
            return true;
        } else if (preg_match('/hsl\(\d{0,3}\, ?\d{0,3}%, ?\d{0,3}%\)/i', $colour)) {
            return true;
        } else if (preg_match('/hsla\(\d{0,3}\, ?\d{0,3}%,\d{0,3}%\, ?\d(\.\d)?\)/i', $colour)) {
            return true;
        } else if (($colour == 'transparent') || ($colour == 'currentColor') || ($colour == 'inherit')) {
            return true;
        } else {
            return false;
        }
    }
}

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

        // Set the time as a variable.
        $time = time();

        $data = new stdClass();
        $data->pageid = $pageid;
        $data->element = $element;
        $data->font = 'Times-Roman';
        $data->size = '12';
        $data->colour = '#000000';
        $data->posx = '250';
        $data->posy = '250';
        $data->sequence = customcert_element_base::get_element_sequence($pageid);
        $data->timecreated = $time;
        $data->timemodified = $time;

        $DB->insert_record('customcert_elements', $data);
    }

    /**
     * Returns the sequence on a specified customcert page for a
     * newly created element.
     *
     * @param int $pageid the id of the page we are adding this element to
     * @return int the element number
     */
    public static function get_element_sequence($pageid) {
        global $DB;

        // Set the sequence of the element we are creating.
        $sequence = 1;
        // Check if there already elements that exist, if so, overwrite value.
        $sql = "SELECT MAX(sequence) as maxsequence
                FROM {customcert_elements}
                WHERE pageid = :id";
        // Get the current max sequence on this page and add 1 to get the new sequence.
        if ($maxseq = $DB->get_record_sql($sql, array('id' => $pageid))) {
            $sequence = $maxseq->maxsequence + 1;
        }

        return $sequence;
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
        // Check if posx is not set, or not numeric or less than 0.
        if ((!isset($data[$posx])) || (!is_numeric($data[$posx])) || ($data[$posx] <= 0)) {
            $errors[$posx] = get_string('invalidposition', 'customcert', 'X');
        }

        // Get position Y.
        $posy = 'posy_' . $id;
        // Check if posy is not set, or not numeric or less than 0.
        if ((!isset($data[$posy])) || (!is_numeric($data[$posy])) || ($data[$posy] <= 0)) {
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
        $element->timemodified = time();

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
     * Common behaviour for rendering specified content on the pdf.
     *
     * @param stdClass $pdf the pdf object
     * @param stdClass $content the content to render
     */
    public function render_content($pdf, $content) {
        $pdf->setFont($this->element->font, '', $this->element->size);
        $fontcolour = TCPDF_COLORS::convertHTMLColorToDec($this->element->colour, $fontcolour);
        $pdf->SetTextColor($fontcolour['R'], $fontcolour['G'], $fontcolour['B']);
        $pdf->writeHTMLCell(0, 0, $this->element->posx, $this->element->posy, $content);
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
        }

        return false;
    }
}

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

require_once($CFG->dirroot . '/mod/customcert/includes/tcpdf_colors.php');

/**
 * The base class for the customcert elements.
 *
 * @package    mod_customcert
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
     * Can be overridden if more functionality is needed.
     *
     * @param string $element the name of the element
     * @param int $pageid the page id we are saving it to
     */
    public static function add_element($element, $pageid) {
        global $DB;

        $data = customcert_element_base::get_required_attributes($element, $pageid);
        $data->font = 'times';
        $data->size = '12';
        $data->colour = '#000000';
        $data->posx = '0';
        $data->posy = '0';

        $DB->insert_record('customcert_elements', $data);
    }

    /**
     * This function renders the form elements when adding a customcert element.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // Render the common elements.
        $this->render_form_elements_font($mform);
        $this->render_form_elements_colour($mform);
        $this->render_form_elements_position($mform);
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
        // Array to return the errors.
        $errors = array();

        // Common validation methods.
        $errors += $this->validate_form_elements_colour($data);
        $errors += $this->validate_form_elements_position($data);

        return $errors;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data.
     */
    public function save_form_elements($data) {
        global $DB;

        // Get the name of the fields we want from the form.
        $font = 'font_' . $this->element->id;
        $size = 'size_' . $this->element->id;
        $colour = 'colour_' . $this->element->id;
        $posx = 'posx_' . $this->element->id;
        $posy = 'posy_' . $this->element->id;

        // Get the data from the form.
        $element = new stdClass();
        $element->id = $this->element->id;
        $element->data = $this->save_unique_data($data);
        $element->font = (isset($data->$font)) ? $data->$font : null;
        $element->size = (isset($data->$size)) ? $data->$size : null;
        $element->colour = (isset($data->$colour)) ? $data->$colour : null;
        $element->posx = (isset($data->$posx)) ? $data->$posx : null;
        $element->posy = (isset($data->$posy)) ? $data->$posy : null;
        $element->timemodified = time();

        // Ok, now update record in the database.
        $DB->update_record('customcert_elements', $element);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert column.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data.
     * @return string the unique data to save
     */
    public function save_unique_data($data) {
        return '';
    }

    /**
     * This will handle how individual elements save their data
     * to a template to be loaded later.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return bool returns true if the data was saved to the template, false otherwise
     */
    public function save_data_to_template($data) {
        return true;
    }

    /**
     * This will handle how individual elements load their data
     * from a template to an existing customcert.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return bool returns true if the data was loaded from the template, false otherwise
     */
    public function load_data_from_template($data) {
        return true;
    }

    /**
     * Handles rendering the element on the pdf.
     * Must be overridden.
     *
     * @param stdClass $pdf the pdf object
     */
    public function render($pdf) {
        // Must be overridden.
    }

    /**
     * Common behaviour for rendering specified content on the pdf.
     *
     * @param stdClass $pdf the pdf object
     * @param string $content the content to render
     */
    public function render_content($pdf, $content) {
        $this->set_font($pdf);
        $fontcolour = TCPDF_COLORS::convertHTMLColorToDec($this->element->colour, $fontcolour);
        $pdf->SetTextColor($fontcolour['R'], $fontcolour['G'], $fontcolour['B']);
        $pdf->writeHTMLCell(0, 0, $this->element->posx, $this->element->posy, $content);
    }

    /**
     * Handles deleting any data this element may have introduced.
     * Can be overridden if more functionality is needed.
     *
     * @return bool success return true if deletion success, false otherwise
     */
    public function delete_element() {
        global $DB;

        return $DB->delete_records('customcert_elements', array('id' => $this->element->id));
    }

    /**
     * Helper function responsible for setting the default values for the required variables
     * for an element. This should be set the same way regardless of elements.
     *
     * @param string $element the name of the element
     * @param int $pageid the page id we are saving it to
     * @return stdClass the required attributes
     */
    public static function get_required_attributes($element, $pageid) {
        // Set the time as a variable.
        $time = time();

        $data = new stdClass();
        $data->pageid = $pageid;
        $data->element = $element;
        $data->sequence = customcert_element_base::get_element_sequence($pageid);
        $data->timecreated = $time;
        $data->timemodified = $time;

        return $data;
    }

    /**
     * Helper function that returns the sequence on a specified customcert page for a
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
     * Helper function to render the font elements.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements_font($mform) {
        $mform->addElement('select', 'font_' . $this->element->id, get_string('font', 'customcert'), customcert_get_fonts());
        $mform->setType('font_' . $this->element->id, PARAM_TEXT);
        $mform->setDefault('font_' . $this->element->id, $this->element->font);
        $mform->addHelpButton('font_' . $this->element->id, 'font', 'customcert');

        $mform->addElement('select', 'size_' . $this->element->id, get_string('fontsize', 'customcert'), customcert_get_font_sizes());
        $mform->setType('size_' . $this->element->id, PARAM_INT);
        $mform->setDefault('size_' . $this->element->id, $this->element->size);
        $mform->addHelpButton('size_' . $this->element->id, 'fontsize', 'customcert');
    }


    /**
     * Helper function to render the colour elements.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements_colour($mform) {
        $mform->addElement('customcert_colourpicker', 'colour_' . $this->element->id, get_string('fontcolour', 'customcert'));
        $mform->setType('colour_' . $this->element->id, PARAM_RAW); // Need to validate that this is a valid colour.
        $mform->setDefault('colour_' . $this->element->id, $this->element->colour);
        $mform->addHelpButton('colour_' . $this->element->id, 'fontcolour', 'customcert');
    }

    /**
     * Helper function to render the position elements.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements_position($mform) {
        $mform->addElement('text', 'posx_' . $this->element->id, get_string('posx', 'customcert'), array('size' => 10));
        $mform->setType('posx_' . $this->element->id, PARAM_INT);
        $mform->setDefault('posx_' . $this->element->id, $this->element->posx);
        $mform->addHelpButton('posx_' . $this->element->id, 'posx', 'customcert');

        $mform->addElement('text', 'posy_' . $this->element->id, get_string('posy', 'customcert'), array('size' => 10));
        $mform->setType('posy_' . $this->element->id, PARAM_INT);
        $mform->setDefault('posy_' . $this->element->id, $this->element->posy);
        $mform->addHelpButton('posy_' . $this->element->id, 'posy', 'customcert');
    }

    /**
     * Helper function to performs validation on the colour element.
     *
     * @param array $data the submitted data
     * @return array the validation errors
     */
    public function validate_form_elements_colour($data) {
        $errors = array();

        // Validate the colour.
        $colour = 'colour_' . $this->element->id;
        $colourdata = $data[$colour];
        if (!$this->validate_colour($colourdata)) {
            $errors[$colour] = get_string('invalidcolour', 'customcert');
        }

        return $errors;
    }

    /**
     * Helper function to performs validation on the position elements.
     *
     * @param array $data the submitted data
     * @return array the validation errors
     */
    public function validate_form_elements_position($data) {
        $errors = array();

        // Get position X.
        $posx = 'posx_' . $this->element->id;
        // Check if posx is not set, or not numeric or less than 0.
        if ((!isset($data[$posx])) || (!is_numeric($data[$posx])) || ($data[$posx] < 0)) {
            $errors[$posx] = get_string('invalidposition', 'customcert', 'X');
        }

        // Get position Y.
        $posy = 'posy_' . $this->element->id;
        // Check if posy is not set, or not numeric or less than 0.
        if ((!isset($data[$posy])) || (!is_numeric($data[$posy])) || ($data[$posy] < 0)) {
            $errors[$posy] = get_string('invalidposition', 'customcert', 'Y');
        }

        return $errors;
    }

    /**
     * Sets the font for the element.
     *
     * @param stdClass $pdf the pdf object
     */
    public function set_font($pdf) {
        // Variable for the font.
        $font = $this->element->font;
        // Get the last two characters of the font name.
        $fontlength = strlen($font);
        $lastchar = $font[$fontlength - 1];
        $secondlastchar = $font[$fontlength - 2];
        // The attributes of the font.
        $attr = '';
        // Check if the last character is 'i'.
        if ($lastchar == 'i') {
            // Remove the 'i' from the font name.
            $font = substr($font, 0, -1);
            // Check if the second last char is b.
            if ($secondlastchar == 'b') {
                // Remove the 'b' from the font name.
                $font = substr($font, 0, -1);
                $attr .= 'B';
            }
            $attr .= 'I';
        } else if ($lastchar == 'b') {
            // Remove the 'b' from the font name.
            $font = substr($font, 0, -1);
            $attr .= 'B';
        }
        $pdf->setFont($font, $attr, $this->element->size);
    }

    /**
     * Validates the colour selected.
     *
     * @param string $colour
     * @return bool returns true if the colour is valid, false otherwise
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

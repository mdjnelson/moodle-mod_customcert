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
 * The base class for the customcert elements.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

defined('MOODLE_INTERNAL') || die();

/**
 * Class element
 *
 * All customercert element plugins are based on this class.
 */
abstract class element {

    /**
     * The data for the element we are adding.
     */
    public $element;

    /**
     * Constructor.
     *
     * @param \stdClass $element the element data
     */
    public function __construct($element) {
        $this->element = clone($element);
    }

    /**
     * This function renders the form elements when adding a customcert element.
     * Can be overridden if more functionality is needed.
     *
     * @param \mod_customcert_edit_element_form $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // Render the common elements.
        $this->render_form_element_font($mform);
        $this->render_form_element_colour($mform);
        $this->render_form_element_position($mform);
    }

    /**
     * Sets the data on the form when editing an element.
     * Can be overridden if more functionality is needed.
     *
     * @param \mod_customcert_edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Loop through the properties of the element and set the values
        // of the corresponding form element, if it exists.
        foreach ($this->element as $property => $value) {
            if ($mform->elementExists($property)) {
                $element = $mform->getElement($property);
                $element->setValue($value);
            }
        }
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
        $errors += $this->validate_form_element_colour($data);
        $errors += $this->validate_form_element_position($data);

        return $errors;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overridden if more functionality is needed.
     *
     * @param \stdClass $data the form data
     */
    public function save_form_elements($data) {
        global $DB;

        // Get the data from the form.
        $element = new \stdClass();
        $element->name = $data->name;
        $element->data = $this->save_unique_data($data);
        $element->font = (isset($data->font)) ? $data->font : null;
        $element->size = (isset($data->size)) ? $data->size : null;
        $element->colour = (isset($data->colour)) ? $data->colour : null;
        $element->posx = (isset($data->posx)) ? $data->posx : null;
        $element->posy = (isset($data->posy)) ? $data->posy : null;
        $element->width = (isset($data->width)) ? $data->width : null;
        $element->refpoint = (isset($data->refpoint)) ? $data->refpoint : null;
        $element->timemodified = time();

        // Check if we are updating, or inserting a new element.
        if (!empty($this->element->id)) { // Must be updating a record in the database.
            $element->id = $this->element->id;
            $DB->update_record('customcert_elements', $element);
        } else { // Must be adding a new one.
            $element->element = $data->element;
            $element->pageid = $data->pageid;
            $element->sequence = $this->get_element_sequence($element->pageid);
            $element->timecreated = time();
            $DB->insert_record('customcert_elements', $element);
        }
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert column.
     * Can be overridden if more functionality is needed.
     *
     * @param \stdClass $data the form data
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
     * @param \stdClass $data the form data
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
     * @param \stdClass $data the form data
     * @return bool returns true if the data was loaded from the template, false otherwise
     */
    public function load_data_from_template($data) {
        return true;
    }

    /**
     * Handles rendering the element on the pdf.
     * Must be overridden.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     */
    public abstract function render($pdf, $preview);

    /**
     * Common behaviour for rendering specified content on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param string $content the content to render
     */
    public function render_content($pdf, $content) {
        list($font, $attr) = $this->get_font();
        $pdf->setFont($font, $attr, $this->element->size);
        $fontcolour = \TCPDF_COLORS::convertHTMLColorToDec($this->element->colour, $fontcolour);
        $pdf->SetTextColor($fontcolour['R'], $fontcolour['G'], $fontcolour['B']);

        $x = $this->element->posx;
        $y = $this->element->posy;
        $w = $this->element->width;
        $refpoint = $this->element->refpoint;
        $actualwidth = $pdf->GetStringWidth($content);

        if ($w and $w < $actualwidth) {
            $actualwidth = $w;
        }

        switch ($refpoint) {
            case CUSTOMCERT_REF_POINT_TOPRIGHT:
                $x = $this->element->posx - $actualwidth;
                if ($x < 0) {
                    $x = 0;
                    $w = $this->element->posx;
                } else {
                    $w = $actualwidth;
                }
                break;
            case CUSTOMCERT_REF_POINT_TOPCENTER:
                $x = $this->element->posx - $actualwidth / 2;
                if ($x < 0) {
                    $x = 0;
                    $w = $this->element->posx * 2;
                } else {
                    $w = $actualwidth;
                }
                break;
        }

        if ($w) {
            $w += 0.0001;
        }
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->writeHTMLCell($w, 0, $x, $y, $content, 0, 0, false, true);
    }

    /**
     * Render the element in html.
     *
     * Must be overridden.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     */
    public abstract function render_html();

    /**
     * Common behaviour for rendering specified content on the drag and drop page.
     *
     * @param string $content the content to render
     * @return string the html
     */
    public function render_html_content($content) {
        list($font, $attr) = $this->get_font();
        $fontstyle = 'font-family: ' . $font;
        if (strpos($attr, 'B') !== false) {
            $fontstyle .= ': font-weight: bold';
        }
        if (strpos($attr, 'I') !== false) {
            $fontstyle .= ': font-style: italic';
        }
        $style = $fontstyle . '; color: ' . $this->element->colour . '; font-size: ' . $this->element->size . 'pt';
        return \html_writer::tag('span', $content, array('style' => $style));
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
     * @param \mod_customcert_edit_element_form $mform the edit_form instance.
     */
    public function render_form_element_font($mform) {
        $mform->addElement('select', 'font', get_string('font', 'customcert'), customcert_get_fonts());
        $mform->setType('font', PARAM_TEXT);
        $mform->setDefault('font', 'times');
        $mform->addHelpButton('font', 'font', 'customcert');

        $mform->addElement('select', 'size', get_string('fontsize', 'customcert'), customcert_get_font_sizes());
        $mform->setType('size', PARAM_INT);
        $mform->setDefault('size', 12);
        $mform->addHelpButton('size', 'fontsize', 'customcert');
    }

    /**
     * Helper function to render the colour elements.
     *
     * @param \mod_customcert_edit_element_form $mform the edit_form instance.
     */
    public function render_form_element_colour($mform) {
        $mform->addElement('customcert_colourpicker', 'colour', get_string('fontcolour', 'customcert'));
        $mform->setType('colour', PARAM_RAW); // Need to validate that this is a valid colour.
        $mform->setDefault('colour', '#000000');
        $mform->addHelpButton('colour', 'fontcolour', 'customcert');
    }

    /**
     * Helper function to render the position elements.
     *
     * @param \mod_customcert_edit_element_form $mform the edit_form instance.
     */
    public function render_form_element_position($mform) {
        $mform->addElement('text', 'posx', get_string('posx', 'customcert'), array('size' => 10));
        $mform->setType('posx', PARAM_INT);
        $mform->setDefault('posx', 0);
        $mform->addHelpButton('posx', 'posx', 'customcert');

        $mform->addElement('text', 'posy', get_string('posy', 'customcert'), array('size' => 10));
        $mform->setType('posy', PARAM_INT);
        $mform->setDefault('posy', 0);
        $mform->addHelpButton('posy', 'posy', 'customcert');

        $mform->addElement('text', 'width', get_string('elementwidth', 'customcert'), array('size' => 10));
        $mform->setType('width', PARAM_INT);
        $mform->setDefault('width', 0);
        $mform->addHelpButton('width', 'elementwidth', 'customcert');

        $refpointoptions = array();
        $refpointoptions[CUSTOMCERT_REF_POINT_TOPLEFT] = get_string('topleft', 'customcert');
        $refpointoptions[CUSTOMCERT_REF_POINT_TOPCENTER] = get_string('topcenter', 'customcert');
        $refpointoptions[CUSTOMCERT_REF_POINT_TOPRIGHT] = get_string('topright', 'customcert');

        $mform->addElement('select', 'refpoint', get_string('refpoint', 'customcert'), $refpointoptions);
        $mform->setType('refpoint', PARAM_INT);
        $mform->setDefault('refpoint', CUSTOMCERT_REF_POINT_TOPCENTER);
        $mform->addHelpButton('refpoint', 'refpoint', 'customcert');
    }

    /**
     * Helper function to performs validation on the colour element.
     *
     * @param array $data the submitted data
     * @return array the validation errors
     */
    public function validate_form_element_colour($data) {
        $errors = array();

        // Validate the colour.
        if (!$this->validate_colour($data['colour'])) {
            $errors['colour'] = get_string('invalidcolour', 'customcert');
        }

        return $errors;
    }

    /**
     * Helper function to performs validation on the position elements.
     *
     * @param array $data the submitted data
     * @return array the validation errors
     */
    public function validate_form_element_position($data) {
        $errors = array();

        // Check if posx is not set, or not numeric or less than 0.
        if ((!isset($data['posx'])) || (!is_numeric($data['posx'])) || ($data['posx'] < 0)) {
            $errors['posx'] = get_string('invalidposition', 'customcert', 'X');
        }

        // Check if posy is not set, or not numeric or less than 0.
        if ((!isset($data['posy'])) || (!is_numeric($data['posy'])) || ($data['posy'] < 0)) {
            $errors['posy'] = get_string('invalidposition', 'customcert', 'Y');
        }

        // Check if width is less than 0.
        if (isset($data['width']) && $data['width'] < 0) {
            $errors['width'] = get_string('invalidelementwidth', 'customcert');
        }

        return $errors;
    }

    /**
     * Returns the font used for this element.
     *
     * @return array the font and font attributes
     */
    public function get_font() {
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
        return array($font, $attr);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * For example, the function may save data that is related to another course module, this
     * data will need to be updated if we are restoring the course as the course module id will
     * be different in the new course.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {

    }

    /**
     * Validates the colour selected.
     *
     * @param string $colour
     * @return bool returns true if the colour is valid, false otherwise
     */
    protected function validate_colour($colour) {
         // List of valid HTML colour names.
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

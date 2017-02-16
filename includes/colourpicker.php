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
 * This file contains the form element for handling the colour picker.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once("HTML/QuickForm/text.php");

/**
 * Form element for handling the colour picker.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodlequickform_customcert_colourpicker extends HTML_QuickForm_text {

    /**
     * @var string The string for the help icon, if empty then no help icon will be displayed.
     */
    public $_helpbutton = '';

    /**
     * Returns the html string to display this element.
     *
     * @return string
     */
    public function tohtml() {
        global $PAGE, $OUTPUT;

        $PAGE->requires->js_init_call('M.util.init_colour_picker', array($this->getAttribute('id'), null));
        $content = '<label class="accesshide" for="' . $this->getAttribute('id') . '" >' . $this->getLabel() . '</label>';
        $content .= html_writer::start_tag('div', array('class' => 'form-colourpicker defaultsnext'));
        $content .= html_writer::tag('div', $OUTPUT->pix_icon('i/loading', get_string('loading', 'admin'), 'moodle',
            array('class' => 'loadingicon')), array('class' => 'admin_colourpicker clearfix'));
        $content .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $this->getAttribute('id'),
            'name' => $this->getName(), 'value' => $this->getValue(), 'size' => '12'));
        $content .= html_writer::end_tag('div');

        return $content;
    }

    /**
     * Return the html for the help button.
     *
     * @return string html for help button
     */
    public function gethelpbutton() {
        return $this->_helpbutton;
    }
}

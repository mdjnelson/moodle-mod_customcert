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
 * This file contains the form for handling editing a customcert element.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use core_text;
use mod_customcert\service\element_factory;
use mod_customcert\service\form_service;
use mod_customcert\service\validation_service;
use moodleform;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

global $CFG;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/customcert/includes/colourpicker.php');

MoodleQuickForm::registerElementType(
    'customcert_colourpicker',
    $CFG->dirroot . '/mod/customcert/includes/colourpicker.php',
    'MoodleQuickForm_customcert_colourpicker'
);

/**
 * The form for handling editing a customcert element.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_element_form extends moodleform {
    /**
     * @var element The element object.
     */
    protected $element;

    /**
     * Form definition.
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->updateAttributes(['id' => 'editelementform']);

        $element = $this->_customdata['element'];

        // Add the field for the name of the element, this is required for all elements.
        $mform->addElement('text', 'name', get_string('elementname', 'customcert'), 'maxlength="255"');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', get_string('pluginname', 'customcertelement_' . $element->element));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'elementname', 'customcert');

        $factory = $this->_customdata['factory'] ?? element_factory::build_with_defaults();
        $this->element = $factory->create_from_legacy_record($element);
        if (!$this->element) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $this->element->set_edit_element_form($this);

        $formservice = new form_service();
        $formservice->build_form($mform, $this->element);

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'savechanges', get_string('savechanges', 'customcert'));

        // Only the Background image, Image, and Digital signature require the 'Save and continue' button.
        if ($this->element->has_save_and_continue()) {
            $buttonarray[] = $mform->createElement(
                'submit',
                'saveandcontinue',
                get_string('saveandcontinue', 'customcert')
            );
        }
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Fill in the current page data for this customcert.
     *
     * This method populates standard element fields (name, font, fontsize, colour, position, width,
     * refpoint, alignment) from the element's getter methods. Element-specific fields are populated
     * by each element's prepare_form() method via the preparable_form_interface.
     */
    public function definition_after_data() {
        $mform = $this->_form;

        // Populate standard fields from element getters.
        $properties = [
            'name' => $this->element->get_name(),
            'font' => $this->element->get_font(),
            'fontsize' => $this->element->get_fontsize(),
            'colour' => $this->element->get_colour(),
            'posx' => $this->element->get_posx(),
            'posy' => $this->element->get_posy(),
            'width' => $this->element->get_width(),
            'refpoint' => $this->element->get_refpoint(),
            'alignment' => $this->element->get_alignment(),
        ];

        foreach ($properties as $property => $value) {
            if ($value !== null && $mform->elementExists($property)) {
                $mform->getElement($property)->setValue($value);
            }
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = [];

        if (core_text::strlen($data['name'] ?? '') > 255) {
            $errors['name'] = get_string('nametoolong', 'customcert');
        }

        $validationservice = new validation_service();
        $errors += $validationservice->validate($this->element, $data);

        return $errors;
    }
}

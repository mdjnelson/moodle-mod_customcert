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
     * @var \stdClass $element The data for the element we are adding.
     */
    public $element;

    /**
     * @var bool $showposxy Show position XY form elements?
     */
    public $showposxy;

    /**
     * Constructor.
     *
     * @param \stdClass $element the element data
     */
    public function __construct($element) {
        $showposxy = get_config('customcert', 'showposxy');

        $this->element = clone($element);
        $this->showposxy = isset($showposxy) && $showposxy;
    }

    /**
     * This function renders the form elements when adding a customcert element.
     * Can be overridden if more functionality is needed.
     *
     * @param edit_element_form $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // Render the common elements.
        element_helper::render_form_element_font($mform);
        element_helper::render_form_element_colour($mform);
        if ($this->showposxy) {
            element_helper::render_form_element_position($mform);
        }
        element_helper::render_form_element_width($mform);
    }

    /**
     * Sets the data on the form when editing an element.
     * Can be overridden if more functionality is needed.
     *
     * @param edit_element_form $mform the edit_form instance
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
        $errors += element_helper::validate_form_element_colour($data);
        if ($this->showposxy) {
            $errors += element_helper::validate_form_element_position($data);
        }
        $errors += element_helper::validate_form_element_width($data);

        return $errors;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overridden if more functionality is needed.
     *
     * @param \stdClass $data the form data
     * @return bool true of success, false otherwise.
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
        if ($this->showposxy) {
            $element->posx = (isset($data->posx)) ? $data->posx : null;
            $element->posy = (isset($data->posy)) ? $data->posy : null;
        }
        $element->width = (isset($data->width)) ? $data->width : null;
        $element->refpoint = (isset($data->refpoint)) ? $data->refpoint : null;
        $element->timemodified = time();

        // Check if we are updating, or inserting a new element.
        if (!empty($this->element->id)) { // Must be updating a record in the database.
            $element->id = $this->element->id;
            return $DB->update_record('customcert_elements', $element);
        } else { // Must be adding a new one.
            $element->element = $data->element;
            $element->pageid = $data->pageid;
            $element->sequence = \mod_customcert\element_helper::get_element_sequence($element->pageid);
            $element->timecreated = time();
            return $DB->insert_record('customcert_elements', $element, false);
        }
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     * Can be overridden if more functionality is needed.
     *
     * @param \stdClass $data the form data
     * @return string the unique data to save
     */
    public function save_unique_data($data) {
        return '';
    }

    /**
     * This handles copying data from another element of the same type.
     * Can be overridden if more functionality is needed.
     *
     * @param \stdClass $data the form data
     * @return bool returns true if the data was copied successfully, false otherwise
     */
    public function copy_element($data) {
        return true;
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * Must be overridden.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public abstract function render($pdf, $preview, $user);

    /**
     * Render the element in html.
     *
     * Must be overridden.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public abstract function render_html();


    /**
     * Handles deleting any data this element may have introduced.
     * Can be overridden if more functionality is needed.
     *
     * @return bool success return true if deletion success, false otherwise
     */
    public function delete() {
        global $DB;

        return $DB->delete_records('customcert_elements', array('id' => $this->element->id));
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
    public function after_restore($restore) { }

    /**
     * Magic getter for read only access.
     *
     * @param string $name
     */
    public function __get($name) {
        if (property_exists($this->element, $name)) {
            return $this->element->$name;
        }
    }

    /**
     * Returns an instance of the element class.
     *
     * @param \stdClass $element the element
     * @return \mod_customcert\element|bool returns the instance of the element class, or false if element
     *         class does not exists.
     */
    public static function instance($element) {
        // Get the class name.
        $classname = '\\customcertelement_' . $element->element . '\\element';

        // Ensure the necessary class exists.
        if (class_exists($classname)) {
            return new $classname($element);
        }

        return false;
    }

    /**
     * Return the list of possible elements to add.
     *
     * @return array the list of element types that can be used.
     */
    public static function get_available_types() {
        global $CFG;

        // Array to store the element types.
        $options = array();

        // Check that the directory exists.
        $elementdir = "$CFG->dirroot/mod/customcert/element";
        if (file_exists($elementdir)) {
            // Get directory contents.
            $elementfolders = new \DirectoryIterator($elementdir);
            // Loop through the elements folder.
            foreach ($elementfolders as $elementfolder) {
                // If it is not a directory or it is '.' or '..', skip it.
                if (!$elementfolder->isDir() || $elementfolder->isDot()) {
                    continue;
                }
                // Check that the standard class exists, if not we do
                // not want to display it as an option as it will not work.
                $foldername = $elementfolder->getFilename();
                // Get the class name.
                $classname = '\\customcertelement_' . $foldername . '\\element';
                // Ensure the necessary class exists.
                if (class_exists($classname)) {
                    $component = "customcertelement_{$foldername}";
                    $options[$foldername] = get_string('pluginname', $component);
                }
            }
        }

        \core_collator::asort($options);
        return $options;
    }
}

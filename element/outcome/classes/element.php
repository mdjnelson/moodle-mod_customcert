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
 * This file contains the customcert element outcome's core interaction API.
 *
 * @package    customcertelement_outcome
 * @copyright  2020 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element outcome's core interaction API.
 *
 * @package    customcertelement_outcome
 * @copyright  2020 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        // Get grade items with outcomes/scales attached to them.
        $gradeitems = \grade_item::fetch_all(
            [
                'courseid' => $COURSE->id,
                'gradetype' => GRADE_TYPE_SCALE
            ]
        );

        $selectoutcomes = [];
        foreach ($gradeitems as $gradeitem) {
            // Get the name of the activity.
            $cm = get_coursemodule_from_instance($gradeitem->itemmodule, $gradeitem->iteminstance, $COURSE->id);
            $modcontext = \context_module::instance($cm->id);
            $modname = format_string($cm->name, true, array('context' => $modcontext));

            $optionname = $modname . " - " . $gradeitem->get_name();
            $selectoutcomes[$gradeitem->id] = $optionname;
        }
        asort($selectoutcomes);

        $mform->addElement('select', 'gradeitem', get_string('outcome', 'customcertelement_outcome'), $selectoutcomes);

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        if (!empty($data->gradeitem)) {
            return $data->gradeitem;
        }

        return '';
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $gradeitem = \grade_item::fetch(['id' => $this->get_data()]);

        if ($preview) {
            // Get the outcome.
            $outcome = \grade_outcome::fetch(['id' => $gradeitem->outcomeid]);

            $display = $outcome->get_name();
        } else {
            // Get the grade.
            $grade = new \grade_grade(
                [
                    'itemid' => $gradeitem->id,
                    'userid' => $user->id
                ]
            );

            $display = grade_format_gradevalue($grade->finalgrade, $gradeitem);
        }

        \mod_customcert\element_helper::render_content($pdf, $this, $display);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        // Get the grade item.
        $gradeitem = \grade_item::fetch(['id' => $this->get_data()]);

        // Get the outcome.
        $outcome = \grade_outcome::fetch(['id' => $gradeitem->outcomeid]);

        return \mod_customcert\element_helper::render_html_content($this, $outcome->get_name());
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $element = $mform->getElement('gradeitem');
            $element->setValue($this->get_data());
        }

        parent::definition_after_data($mform);
    }
}

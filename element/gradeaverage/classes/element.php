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
 * This file contains the customcert element grade's core interaction API.
 *
 * @package    customcertelement_gradeaverage
 * @copyright  2020 3&Punt <https://tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_gradeaverage;

use coding_exception;
use core\notification;
use dml_exception;
use grade_item;
use mod_customcert\element_helper;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use restore_dbops;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package    customcertelement_gradeaverage
 * @copyright  2020 3&Punt <https://tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param MoodleQuickForm $mform the edit_form instance
     * @throws coding_exception
     */
    public function render_form_elements($mform) {
        global $COURSE;
        if ((int)$COURSE->enablecompletion !== 1) {
            notification::add(get_string('coursenotcompletion', 'customcertelement_gradeaverage'),
                \core\output\notification::NOTIFY_WARNING);
        }
        $courses = get_courses('all', 'c.sortorder ASC');
        unset($courses[SITEID]);
        $options = [];
        foreach ($courses as $course) {
            if ((int)$course->enablecompletion === 1) {
                $options[$course->id] = $course->fullname;
            }
        }
        $attributes = ['multiple' => 'multiple', 'size' => count($options)];
        $mform->addElement('select', 'coursescompletion', get_string('coursescompletion', 'customcertelement_gradeaverage'), $options, $attributes);
        $mform->setType('coursescompletion', PARAM_TEXT);
        $mform->addHelpButton('coursescompletion', 'coursescompletion', 'customcertelement_gradeaverage');
        $mform->setDefault('coursescompletion', $COURSE->id);

        // The grade format.
        $mform->addElement('select', 'gradeformat', get_string('gradeformat', 'customcertelement_gradeaverage'),
            self::get_grade_format_options());
        $mform->setType('gradeformat', PARAM_INT);
        $mform->addHelpButton('gradeformat', 'gradeformat', 'customcertelement_gradeaverage');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'coursescompletion' => $data->coursescompletion,
            'gradeformat' => $data->gradeformat
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        if (empty($this->get_data())) {
            return;
        }
        $courseid = element_helper::get_courseid($this->id);
        $gradeaverageinfo = json_decode($this->get_data());
        $coursescompletion = $gradeaverageinfo->coursescompletion;
        $gradeformat = (int)$gradeaverageinfo->gradeformat;

        if ($preview) {
            $courseitem = grade_item::fetch_course_item($courseid);
            $gradeaverage = grade_format_gradevalue('100', $courseitem, true, $gradeaverageinfo->gradeformat);
        } else {
            $grades = [];
            foreach ($coursescompletion as $course) {
                $coursegrade = element_helper::get_course_grade_info(
                    $course,
                    GRADE_DISPLAY_TYPE_REAL,
                    $user->id
                )->get_grade();
                $grades[] = $coursegrade !== '' ? $coursegrade : 0;
            }
            $gradeaverage = array_sum($grades) / count($coursescompletion);
            if ($gradeformat === GRADE_DISPLAY_TYPE_PERCENTAGE) {
                $gradeitem = grade_item::fetch_course_item($courseid);
                $gradeaverage = grade_format_gradevalue_percentage($gradeaverage, $gradeitem, $gradeitem->get_decimals(), true);
            }
            if ($gradeformat === GRADE_DISPLAY_TYPE_LETTER) {
                $gradeitem = grade_item::fetch_course_item($courseid);
                $gradeaverage = grade_format_gradevalue_letter($gradeaverage, $gradeitem);
            }
        }
        element_helper::render_content($pdf, $this, $gradeaverage);
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
        global $COURSE;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $gradeaverageinfo = json_decode($this->get_data());

        $courseitem = grade_item::fetch_course_item($COURSE->id);

        $grade = grade_format_gradevalue('100', $courseitem, true, $gradeaverageinfo->gradeformat);

        return element_helper::render_html_content($this, $grade);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $gradeaverageinfo = json_decode($this->get_data());

            $element = $mform->getElement('coursescompletion');
            $element->setValue($gradeaverageinfo->coursescompletion);

            $element = $mform->getElement('gradeformat');
            $element->setValue($gradeaverageinfo->gradeformat);
        }

        parent::definition_after_data($mform);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the grade element is pointing to as it will
     * have changed in the course restore.
     *
     * @param restore_customcert_activity_task $restore
     * @throws dml_exception
     */
    public function after_restore($restore) {
        global $DB;
        $gradeaverageinfo = json_decode($this->get_data());
        if ($newitem = restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $gradeaverageinfo->coursescompletion)) {
            $gradeaverageinfo->coursescompletion = $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($gradeaverageinfo), array('id' => $this->get_id()));
        }
    }

    /**
     * Helper function to return all the possible grade formats.
     *
     * @return array returns an array of grade formats
     * @throws coding_exception
     */
    public static function get_grade_format_options() {
        $gradeformat = array();
        $gradeformat[GRADE_DISPLAY_TYPE_REAL] = get_string('gradepoints', 'customcertelement_gradeaverage');
        $gradeformat[GRADE_DISPLAY_TYPE_PERCENTAGE] = get_string('gradepercent', 'customcertelement_gradeaverage');
        $gradeformat[GRADE_DISPLAY_TYPE_LETTER] = get_string('gradeletter', 'customcertelement_gradeaverage');

        return $gradeformat;
    }
}

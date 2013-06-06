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
 * The grade elements core interaction API.
 *
 * @package    customcertelement_grade
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/customcert/elements/element.class.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Grade - Course
 */
define('CUSTOMCERT_GRADE_COURSE', '0');

class customcert_element_grade extends customcert_element_base {

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    function __construct($element) {
        parent::__construct($element);

        // Set the item and format for this element.
        $gradeitem = '';
        $gradeformat = '';

        if (!empty($this->element->data)) {
            $gradeinfo = json_decode($this->element->data);
            $gradeitem = $gradeinfo->gradeitem;
            $gradeformat = $gradeinfo->gradeformat;
        }

        $this->element->gradeitem = $gradeitem;
        $this->element->gradeformat = $gradeformat;
    }

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // Get the grade items we can display.
        $gradeitems = array();
        $gradeitems[CUSTOMCERT_GRADE_COURSE] = get_string('coursegrade', 'customcertelement_grade');
        $gradeitems = $gradeitems + $this->get_grade_items();

        // The grade items.
        $mform->addElement('select', 'gradeitem', get_string('gradeitem', 'customcertelement_grade'), $gradeitems);
        $mform->setType('gradeitem', PARAM_INT);
        $mform->addHelpButton('gradeitem', 'gradeitem', 'customcertelement_grade');

        // The grade format.
        $mform->addElement('select', 'gradeformat', get_string('gradeformat', 'customcertelement_grade'), $this->get_grade_format_options());
        $mform->setType('gradeformat', PARAM_INT);
        $mform->addHelpButton('gradeformat', 'gradeformat', 'customcertelement_grade');

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
            'gradeitem' => $data->gradeitem,
            'gradeformat' => $data->gradeformat
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);

    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param stdClass $pdf the pdf object
     */
    public function render($pdf) {
        global $USER;

        // If there is no element data, we have nothing to display.
        if (empty($this->element->data)) {
            return;
        }

        // Decode the information stored in the database.
        $gradeinfo = json_decode($this->element->data);

        // Get the grade for the grade item.
        $grade = $this->get_grade($gradeinfo, $USER->id);
        parent::render_content($pdf, $grade);
    }

    /**
     * Helper function to return all the grades items for this course.
     *
     * @return array the array of gradeable items in the course
     */
    public static function get_grade_items() {
        global $COURSE, $DB;

        // Array to store the grade items.
        $modules = array();

        // Collect course modules data.
        $modinfo = get_fast_modinfo($COURSE);
        $mods = $modinfo->get_cms();
        $sections = $modinfo->get_section_info_all();

        // Create the section label depending on course format.
        switch ($COURSE->format) {
            case "topics":
                $sectionlabel = get_string("topic");
                break;
            case "weeks":
                $sectionlabel = get_string("week");
                break;
            default:
                $sectionlabel = get_string("section");
                break;
        }

        // Loop through each course section.
        for ($i = 0; $i <= count($sections) - 1; $i++) {
            // Confirm the index exists, should always be true.
            if (isset($sections[$i])) {
            	// Get the individual section.
                $section = $sections[$i];
                // Get the mods for this section.
                $sectionmods = explode(",", $section->sequence);
                // Loop through the section mods.
                foreach ($sectionmods as $sectionmod) {
                	// Should never happen unless DB is borked.
                    if (empty($mods[$sectionmod])) {
                        continue;
                    }
                    $mod = $mods[$sectionmod];
                    $mod->courseid = $COURSE->id;
                    $instance = $DB->get_record($mod->modname, array('id' => $mod->instance));
                    // Get the grade items for this activity.
                    if ($grade_items = grade_get_grade_items_for_activity($mod)) {
                        $mod_item = grade_get_grades($COURSE->id, 'mod', $mod->modname, $mod->instance);
                        $gradeitem = reset($mod_item->items);
                        if (isset($gradeitem->grademax)) {
                            $modules[$mod->id] = $sectionlabel . ' ' . $section->section . ' : ' . $instance->name;
                        }
                    }
                }
            }
        }

        return $modules;
    }

    /**
     * Helper function to return all the possible grade formats.
     *
     * @return array returns an array of grade formats
     */
    public static function get_grade_format_options() {
        $gradeformat = array();
        $gradeformat[GRADE_DISPLAY_TYPE_REAL] = get_string('gradepoints', 'customcertelement_grade');
        $gradeformat[GRADE_DISPLAY_TYPE_PERCENTAGE] = get_string('gradepercent', 'customcertelement_grade');
        $gradeformat[GRADE_DISPLAY_TYPE_LETTER] = get_string('gradeletter', 'customcertelement_grade');

        return $gradeformat;
    }

    /**
     * Helper function to return the grade to display.
     *
     * @param stdClass $gradeinfo
     * @param int $userid
     * @return string the grade result
     */
    public static function get_grade($gradeinfo, $userid) {
        global $COURSE;

        // Get the grade information.
        $gradeitem = $gradeinfo->gradeitem;
        $gradeformat = $gradeinfo->gradeformat;

        // Check if we are displaying the course grade.
        if ($gradeitem == CUSTOMCERT_GRADE_COURSE) {
            if ($courseitem = grade_item::fetch_course_item($COURSE->id)) {
                // Set the grade type we want.
                $courseitem->gradetype = GRADE_TYPE_VALUE;
                $grade = new grade_grade(array('itemid' => $courseitem->id, 'userid' => $userid));
                $coursegrade = grade_format_gradevalue($grade->finalgrade, $courseitem, true, $gradeformat, 2);
                return get_string('coursegrade', 'certificate') . ':  ' . $coursegrade;
            }
        } else { // Get the module grade.
            if ($modinfo = customcert_element_grade::get_mod_grade($gradeitem, $gradeformat, $userid)) {
                return get_string('grade', 'certificate') . ':  ' . $modinfo->gradetodisplay;
            }
        }

        // Only gets here if no grade was retrieved from the DB.
        return '';
    }

    /**
     * Helper function to return the grade the user achieved for a specified module.
     *
     * @param int $moduleid
     * @param int $gradeformat
     * @param int $userid
     * @return stdClass the grade information
     */
    public static function get_mod_grade($moduleid, $gradeformat, $userid) {
        global $DB;

        $cm = $DB->get_record('course_modules', array('id' => $moduleid), '*', MUST_EXIST);
        $module = $DB->get_record('modules', array('id' => $cm->module), '*', MUST_EXIST);

        if ($gradeitem = grade_get_grades($cm->course, 'mod', $module->name, $cm->instance, $userid)) {
            $gradeitem = new grade_item();
            $gradeitem->gradetype = GRADE_TYPE_VALUE;
            $gradeitem->courseid = $cm->course;
            $gradeitemproperties = reset($gradeitem->items);
            foreach ($gradeitemproperties as $key => $value) {
                $gradeitem->$key = $value;
            }
            // Grade for the user.
            $grade = $gradeitem->grades[$userid]->grade;
            // Create the object we will be returning.
            $modinfo = new stdClass;
            $modinfo->name = $DB->get_field($module->name, 'name', array('id' => $cm->instance));
            $modinfo->gradetodisplay = grade_format_gradevalue($grade, $gradeitem, true, $gradeformat, 2);

            if ($grade) {
                $modinfo->dategraded = $gradeitem->grades[$userid]->dategraded;
            } else {
                $modinfo->dategraded = time();
            }
            return $modinfo;
        }

        return false;
    }
}

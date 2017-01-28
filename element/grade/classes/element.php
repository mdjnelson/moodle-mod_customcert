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

namespace customcertelement_grade;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Grade - Course
 */
define('CUSTOMCERT_GRADE_COURSE', '0');

/**
 * The customcert element grade's core interaction API.
 *
 * @package    customcertelement_grade
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        // Get the grade items we can display.
        $gradeitems = array();
        $gradeitems[CUSTOMCERT_GRADE_COURSE] = get_string('coursegrade', 'customcertelement_grade');
        $gradeitems = $gradeitems + self::get_grade_items();

        // The grade items.
        $mform->addElement('select', 'gradeitem', get_string('gradeitem', 'customcertelement_grade'), $gradeitems);
        $mform->setType('gradeitem', PARAM_INT);
        $mform->addHelpButton('gradeitem', 'gradeitem', 'customcertelement_grade');

        // The grade format.
        $mform->addElement('select', 'gradeformat', get_string('gradeformat', 'customcertelement_grade'), self::get_grade_format_options());
        $mform->setType('gradeformat', PARAM_INT);
        $mform->addHelpButton('gradeformat', 'gradeformat', 'customcertelement_grade');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data.
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
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        global $COURSE;

        // If there is no element data, we have nothing to display.
        if (empty($this->element->data)) {
            return;
        }

        // Decode the information stored in the database.
        $gradeinfo = json_decode($this->element->data);

        // If we are previewing this certificate then just show a demonstration grade.
        if ($preview) {
            $courseitem = \grade_item::fetch_course_item($COURSE->id);
            $grade = grade_format_gradevalue('100', $courseitem, true, $gradeinfo->gradeformat, 2);
        } else {
            // Get the grade for the grade item.
            $grade = self::get_grade($gradeinfo, $user->id);
        }

        \mod_customcert\element_helper::render_content($pdf, $this, $grade);
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
        if (empty($this->element->data)) {
            return;
        }

        // Decode the information stored in the database.
        $gradeinfo = json_decode($this->element->data);

        $courseitem = \grade_item::fetch_course_item($COURSE->id);
        // Define how many decimals to display.
        $decimals = 2;
        if ($gradeinfo->gradeformat == GRADE_DISPLAY_TYPE_PERCENTAGE) {
            $decimals = 0;
        }
        $grade = grade_format_gradevalue('100', $courseitem, true, $gradeinfo->gradeformat, $decimals);

        return \mod_customcert\element_helper::render_html_content($this, $grade);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->element->data)) {
            $gradeinfo = json_decode($this->element->data);
            $this->element->gradeitem = $gradeinfo->gradeitem;
            $this->element->gradeformat = $gradeinfo->gradeformat;
        }

        parent::definition_after_data($mform);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the grade element is pointing to as it will
     * have changed in the course restore.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        global $DB;

        $gradeinfo = json_decode($this->element->data);
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $gradeinfo->gradeitem)) {
            $gradeinfo->gradeitem = $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', self::save_unique_data($gradeinfo), array('id' => $this->element->id));
        }
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
            case 'topics':
                $sectionlabel = get_string('topic');
                break;
            case 'weeks':
                $sectionlabel = get_string('week');
                break;
            default:
                $sectionlabel = get_string('section');
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
                    $instance = $DB->get_record($mod->modname, array('id' => $mod->instance));
                    // Get the grade items for this activity.
                    if ($gradeitems = grade_get_grade_items_for_activity($mod)) {
                        $moditem = grade_get_grades($COURSE->id, 'mod', $mod->modname, $mod->instance);
                        $gradeitem = reset($moditem->items);
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
     * @param \stdClass $gradeinfo
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
            if ($courseitem = \grade_item::fetch_course_item($COURSE->id)) {
                // Set the grade type we want.
                $courseitem->gradetype = GRADE_TYPE_VALUE;
                $grade = new \grade_grade(array('itemid' => $courseitem->id, 'userid' => $userid));
                $coursegrade = grade_format_gradevalue($grade->finalgrade, $courseitem, true, $gradeformat, 2);
                return $coursegrade;
            }
        } else { // Get the module grade.
            if ($modinfo = self::get_mod_grade($gradeitem, $gradeformat, $userid)) {
                return $modinfo->gradetodisplay;
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
     * @return \stdClass|bool the grade information, or false if there is none.
     */
    public static function get_mod_grade($moduleid, $gradeformat, $userid) {
        global $DB;

        if (!$cm = $DB->get_record('course_modules', array('id' => $moduleid))) {
            return false;
        }

        if (!$module = $DB->get_record('modules', array('id' => $cm->module))) {
            return false;
        }

        $gradeitem = grade_get_grades($cm->course, 'mod', $module->name, $cm->instance, $userid);
        if (!empty($gradeitem)) {
            $item = new \grade_item();
            $item->gradetype = GRADE_TYPE_VALUE;
            $item->courseid = $cm->course;
            $itemproperties = reset($gradeitem->items);
            foreach ($itemproperties as $key => $value) {
                $item->$key = $value;
            }
            // Grade for the user.
            $grade = $item->grades[$userid]->grade;
            // Define how many decimals to display.
            $decimals = 2;
            if ($gradeformat == GRADE_DISPLAY_TYPE_PERCENTAGE) {
                $decimals = 0;
            }

            // Create the object we will be returning.
            $modinfo = new \stdClass;
            $modinfo->name = $DB->get_field($module->name, 'name', array('id' => $cm->instance));
            $modinfo->gradetodisplay = grade_format_gradevalue($grade, $item, true, $gradeformat, $decimals);

            if ($grade) {
                $modinfo->dategraded = $item->grades[$userid]->dategraded;
            } else {
                $modinfo->dategraded = time();
            }
            return $modinfo;
        }

        return false;
    }
}

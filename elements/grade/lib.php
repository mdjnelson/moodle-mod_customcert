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
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

class customcert_element_grade extends customcert_element_base {

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    function __construct($element) {
        parent::__construct($element);
    }

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // The identifier.
        $id = $this->element->id;

        $gradeitem = '';
        $gradeformat = '';

        // Check if there is any data for this element.
        if (!empty($this->element->data)) {
            $gradeinfo = json_decode($this->element->data);
            $gradeitem = $gradeinfo->gradeitem;
            $gradeformat = $gradeinfo->gradeformat;
        }

        // The elements unique to this field.
        $mform->addElement('select', 'gradeitem_' . $id, get_string('gradeitem', 'customcertelement_grade'), $this->get_grade_items());
        $mform->addElement('select', 'gradeformat_' . $id, get_string('gradeformat', 'customcertelement_grade'), $this->get_grade_format_options());

        parent::render_form_elements($mform);

        $mform->setDefault('gradeitem_' . $id, $gradeitem);
        $mform->setDefault('gradeformat_' . $id, $gradeformat);

        // Add help buttons.
        $mform->addHelpButton('gradeitem_' . $id, 'gradeitem', 'customcertelement_grade');
        $mform->addHelpButton('gradeformat_' . $id, 'gradeformat', 'customcertelement_grade');
	}

	/**
     * This will handle how form data will be saved into the data column in the
     * customcert column.
     *
     * @param stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
    	// The identifier.
        $id = $this->element->id;

        // Get the grade item and format from the form.
        $gradeitem = 'gradeitem_' . $id;
        $gradeitem = $data->$gradeitem;
        $gradeformat = 'gradeformat_' . $id;
        $gradeformat = $data->$gradeformat;

        // Array of data we will be storing in the database.
        $arrtostore = array(
        	'gradeitem' => $gradeitem,
        	'gradeformat' => $gradeformat
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);

    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param $pdf the pdf object, see lib/pdflib.php
     */
    public function render($pdf) {
        global $USER;

        // TO DO.
    }

    /**
     * Helper function to return all the grades items for this course.
     *
     * @return array the array of gradeable items in the course
     */
    public function get_grade_items() {
    	global $COURSE, $DB;

    	$strtopic = get_string("topic");
	    $strweek = get_string("week");
	    $strsection = get_string("section");

	    // Array to store the grade items.
	    $modules = array();
	    $modules['coursegrade'] = get_string('coursegrade', 'customcertelement_grade');

	    // Collect course modules data
	    $modinfo = get_fast_modinfo($COURSE);
	    $mods = $modinfo->get_cms();
	    $sections = $modinfo->get_section_info_all();

	    // Create the section label depending on course format.
        switch ($COURSE->format) {
            case "topics": $sectionlabel = $strtopic;
            case "weeks": $sectionlabel = $strweek;
            default: $sectionlabel = $strsection;
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
                        $item = reset($mod_item->items);
                        if (isset($item->grademax)) {
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
    function get_grade_format_options() {
    	$gradeformat = array();
    	$gradeformat[1] = get_string('gradepercent', 'customcertelement_grade');
    	$gradeformat[2] = get_string('gradepoints', 'customcertelement_grade');
    	$gradeformat[3] = get_string('gradeletter', 'customcertelement_grade');

		return $gradeformat;
    }
}

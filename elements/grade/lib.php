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

defined('MOODLE_INTERNAL') || die();

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
     * @return array the form elements
     */
    public function render_form_elements($mform) {
        // Keep track of the number of times these elements have been
        // added, so we only add the help icon once.
        static $numtimesadded = 0;

        // The identifier.
        $id = $this->element->id;

        $gradeinfo = json_decode($this->element->data);
        $gradeitem = $gradeinfo->gradeitem;
        $gradeformat = $gradeinfo->gradeformat;

        // The common group of elements.
        $group = array();
        $group[] = $mform->createElement('select', 'gradeitem_' . $id, '', $this->get_grade_items());
        $group[] = $mform->createElement('select', 'gradeformat_' . $id, '', $this->get_grade_format_options());
        $group[] = $mform->createElement('select', 'font_' . $id, '', customcert_get_fonts());
        $group[] = $mform->createElement('select', 'size_' . $id, '', customcert_get_font_sizes());
        $group[] = $mform->createELement('text', 'colour_' . $id, '', array('size' => 10, 'maxlength' => 6));
        $group[] = $mform->createElement('text', 'posx_' . $id, '', array('size' => 10));
        $group[] = $mform->createElement('text', 'posy_' . $id, '', array('size' => 10));

        // Add this group.
        $mform->addElement('group', 'elementfieldgroup_' . $id, get_string('pluginname', 'customcertelement_grade'), $group,
            array(' ' . get_string('gradeformat', 'customcertelement_grade') . ' ', ' ' . get_string('font', 'customcert') . ' ',
            	  ' ' . get_string('fontsize', 'customcert') . ' ', ' ' . get_string('colour', 'customcert') . ' ',
                  ' ' . get_string('posx', 'customcert') . ' ', ' ' . get_string('posy', 'customcert') . ' '), false);

        $this->set_form_element_types($mform);

        $mform->setDefault('gradeitem_' . $id, $gradeitem);
        $mform->setDefault('gradeformat_' . $id, $gradeformat);

        if ($numtimesadded == 0) {
            $mform->addHelpButton('elementfieldgroup_' . $id, 'gradeformelements', 'customcertelement_grade');
        }

        $numtimesadded++;
	}

	/**
     * This will handle how form data will be saved into the data column in the
     * customcert column.
     *
     * @param stdClass $data the form data.
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
     * Handles displaying the element on the pdf.
     *
     * @param $pdf the pdf object, see lib/pdflib.php
     * @todo functionality missing, add when we start rendering the pdf
     */
    public function display($pdf) {
        global $USER;

        // TO DO.
    }

    /**
     * Helper function to return all the grades items for this course.
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
     */
    function get_grade_format_options() {
    	$gradeformat = array();
    	$gradeformat[1] = get_string('gradepercent', 'customcertelement_grade');
    	$gradeformat[2] = get_string('gradepoints', 'customcertelement_grade');
    	$gradeformat[3] = get_string('gradeletter', 'customcertelement_grade');

		return $gradeformat;
    }
}
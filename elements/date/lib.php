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
 * The date elements core interaction API.
 *
 * @package    customcertelements_date
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/customcert/elements/element.class.php');
require_once($CFG->dirroot . '/mod/customcert/elements/grade/lib.php');

class customcert_elements_date extends customcert_elements_base {

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    function __construct($element) {
        parent::__construct($element);

        // Set the item and format for this element.
        $dateitem = '';
        $dateformat = '';

        if (!empty($this->element->data)) {
            $dateinfo = json_decode($this->element->data);
            $dateitem = $dateinfo->dateitem;
            $dateformat = $dateinfo->dateformat;
        }

        $this->element->dateitem = $dateitem;
        $this->element->dateformat = $dateformat;
    }

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param stdClass $mform the edit_form instance.
     */
    public function render_form_elements($mform) {
        // Get the possible date options.
        $dateoptions = array();
        $dateoptions['1'] = get_string('issueddate', 'certificate');
        $dateoptions['2'] = get_string('completiondate', 'certificate');
        $dateoptions = $dateoptions + customcert_elements_grade::get_grade_items();

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelements_date'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelements_date');

        $mform->addElement('select', 'dateformat', get_string('dateformat', 'customcertelements_date'), self::get_date_formats());
        $mform->addHelpButton('dateformat', 'dateformat', 'customcertelements_date');

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
        	'dateitem' => $data->dateitem,
        	'dateformat' => $data->dateformat
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
        // TO DO.
    }

	/**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    public static function get_date_formats() {
	    $dateformats = array();
	    $dateformats[] = 'January 1, 2000';
	    $dateformats[] = 'January 1st, 2000';
	    $dateformats[] = '1 January 2000';
        $dateformats[] = 'January 2000';
        $dateformats[] = get_string('userdateformat', 'certificate');

	    return $dateformats;
	}
}

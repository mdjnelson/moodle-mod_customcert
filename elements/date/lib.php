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
 * @package    customcertelement_date
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/customcert/elements/element.class.php');

class customcert_element_date extends customcert_element_base {

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
        $dateitem = '';
        $dateformat = '';

        // Check if there is any data for this element.
        if (!empty($this->element->data)) {
            $dateinfo = json_decode($this->element->data);
            $dateitem = $dateinfo->dateitem;
            $dateformat = $dateinfo->dateformat;
        }

        // Get the possible date options.
        $dateoptions = array();
        $dateoptions['1'] = get_string('issueddate', 'certificate');
        $dateoptions['2'] = get_string('completiondate', 'certificate');
        $dateoptions = $dateoptions + customcert_element_grade::get_grade_items();
        $mform->addElement('select', 'dateitem_' . $this->element->id, get_string('dateitem', 'customcertelement_date'), $dateoptions);
        $mform->addElement('select', 'dateformat_' . $this->element->id, get_string('dateformat', 'customcertelement_date'), customcert_element_date::get_date_formats());

        parent::render_form_elements($mform);

        $mform->setDefault('dateitem_' . $this->element->id, $dateitem);
        $mform->setDefault('dateformat_' . $this->element->id, $dateformat);

        // Add help buttons.
        $mform->addHelpButton('dateitem_' . $this->element->id, 'dateitem', 'customcertelement_date');
        $mform->addHelpButton('dateformat_' . $this->element->id, 'dateformat', 'customcertelement_date');
	}

	/**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Get the date item and format from the form.
        $dateitem = 'dateitem_' . $this->element->id;
        $dateitem = $data->$dateitem;
        $dateformat = 'dateformat_' . $this->element->id;
        $dateformat = $data->$dateformat;

        // Array of data we will be storing in the database.
        $arrtostore = array(
        	'dateitem' => $dateitem,
        	'dateformat' => $dateformat
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param stdClass $pdf the pdf object
     * @param int $userid
     */
    public function render($pdf, $userid) {
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

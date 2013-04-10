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
        // The identifier.
        $id = $this->element->id;

        $dateitem = '';
        $dateformat = '';

        // Check if there is any data for this element.
        if (!empty($this->element->data)) {
            $dateinfo = json_decode($this->element->data);
            $dateitem = $dateinfo->dateitem;
            $dateformat = $dateinfo->dateformat;
        }

        $mform->addElement('select', 'dateitem_' . $id, get_string('dateitem', 'customcertelement_date'), $this->get_date_options());
        $mform->addElement('select', 'dateformat_' . $id, get_string('dateformat', 'customcertelement_date'), $this->get_date_formats());

        parent::render_form_elements($mform);

        $mform->setDefault('dateitem_' . $id, $dateitem);
        $mform->setDefault('dateformat_' . $id, $dateformat);

        // Add help buttons.
        $mform->addHelpButton('dateitem_' . $id, 'dateitem', 'customcertelement_date');
        $mform->addHelpButton('dateformat_' . $id, 'dateformat', 'customcertelement_date');
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

        // Get the date item and format from the form.
        $dateitem = 'dateitem_' . $id;
        $dateitem = $data->$dateitem;
        $dateformat = 'dateformat_' . $id;
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
     * Helper function to return all the date options.
     *
     * @return array the list of date options
     */
    public function get_date_options() {
	    $dateoptions['1'] = get_string('issueddate', 'certificate');
	    $dateoptions['2'] = get_string('completiondate', 'certificate');

	    return $dateoptions;
	}

	/**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    public function get_date_formats() {
	    $dateformats = array();
	    $dateformats[] = 'January 1, 2000';
	    $dateformats[] = 'January 1st, 2000';
	    $dateformats[] = '1 January 2000';
        $dateformats[] = 'January 2000';
        $dateformats[] = get_string('userdateformat', 'certificate');

	    return $dateformats;
	}
}

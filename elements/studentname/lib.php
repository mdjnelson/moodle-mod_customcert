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
 * The studentname elements core interaction API.
 *
 * @package    customcertelement_studentname
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/customcert/elements/element.class.php');

class customcert_element_studentname extends customcert_element_base {

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    function __construct($element) {
        parent::__construct($element);
    }

    /**
     * Handles displaying the element on the pdf.
     *
     * @param $pdf the pdf object, see lib/pdflib.php
     */
    public function display($pdf) {
        global $USER;

        $pdf->setFont($this->element->font, '', $this->element->size);
        $pdf->SetXY($this->element->posx, $this->element->posy);
        $pdf->writeHTMLCell(0, 0, '', '', fullname($USER), 0, 0, 0, true, $align);
    }
}

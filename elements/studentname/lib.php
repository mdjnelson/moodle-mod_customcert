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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

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
     * Handles rendering the element on the pdf.
     *
     * @param stdClass $pdf the pdf object
     * @param int $userid
     */
    public function render($pdf, $userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), 'id, firstname, lastname', MUST_EXIST);
        $fullname = fullname($user);

        parent::render_content($pdf, $fullname);
    }
}

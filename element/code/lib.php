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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/customcert/element/element.class.php');

/**
 * The customcert element code's core interaction API.
 *
 * @package    customcertelement_code
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customcert_element_code extends customcert_element_base {

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     */
    public function render($pdf, $preview) {
        global $DB, $USER;

        // Get the page.
        $page = $DB->get_record('customcert_pages', array('id' => $this->element->pageid), '*', MUST_EXIST);
        // Now we can get the issue for this user.
        $issue = $DB->get_record('customcert_issues', array('userid' => $USER->id, 'customcertid' => $page->customcertid), '*', MUST_EXIST);

        parent::render_content($pdf, $issue->code);
    }
}

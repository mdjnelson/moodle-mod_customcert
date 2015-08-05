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

/**
 * Handles AJAX requests for the customcert module.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$values = required_param('values', PARAM_RAW);
$values = json_decode($values);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$elements = $DB->get_records_sql('SELECT * FROM {customcert_elements} e
                                           JOIN {customcert_pages} p ON e.pageid = p.id
                                          WHERE p.customcertid = ?', array($cm->instance));

// Check that the user is able to perform the change.
require_login($course, false, $cm);
require_capability('mod/customcert:manage', $context);

// Loop through the data
foreach ($values as $value) {
//    if (array_key_exists($value->id, $elements)) {
        // Perform the update.
        $element = new stdClass();
        $element->id = $value->id;
        $element->posx = $value->posx;
        $element->posy = $value->posy;
        $DB->update_record('customcert_elements', $element);
//    }
}
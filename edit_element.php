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
 * Edit a customcert element.
 *
 * @package    mod_customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/edit_element_form.php');
require_once($CFG->dirroot . '/mod/customcert/element/element.class.php');

$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$popup = optional_param('popup', '0', PARAM_INT);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

if ($action == 'edit') {
    // The id of the element must be supplied if we are currently editing one.
    $id = required_param('id', PARAM_INT);
    $element = $DB->get_record('customcert_elements', array('id' => $id), '*', MUST_EXIST);
    $pageurl = new moodle_url('/mod/customcert/edit_element.php', array('id' => $id, 'cmid' => $cmid, 'action' => $action, 'popup' => $popup));
} else { // Must be adding an element.
    // Page id must be supplied in order to add an element.
    $pageid = required_param('pageid', PARAM_INT);
    // Create the new element object, will have no data.
    $element = new stdClass();
    $element->element = required_param('element', PARAM_ALPHA);
    // Set the page url.
    $params = array();
    $params['cmid'] = $cmid;
    $params['action'] = 'add';
    $params['element'] = $element->element;
    $params['pageid'] = $pageid;
    $params['popup'] = $popup;
    $pageurl = new moodle_url('/mod/customcert/edit_element.php', $params);
}

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

if ($popup) {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_heading($course->fullname);
}

$PAGE->set_title(get_string('editcustomcert', 'customcert', format_string($customcert->name)));
$PAGE->set_url($pageurl);

$mform = new mod_customcert_edit_element_form($pageurl, array('element' => $element, 'cmid' => $cmid));

// Check if they cancelled.
if ($mform->is_cancelled()) {
    if ($popup) {
        close_window();
    } else {
        $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cmid));
        redirect($url);
    }
}

if ($data = $mform->get_data()) {
    // Set the id, or page id depending on if we are editing an element, or adding a new one.
    if ($action == 'edit') {
        $data->id = $id;
    } else {
        $data->pageid = $pageid;
    }
    // Set the element variable.
    $data->element = $element->element;
    // Get an instance of the element class.
    if ($e = customcert_get_element_instance($data)) {
        $e->save_form_elements($data);
    }
    if ($popup) {
        close_window();
    } else {
        $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cmid));
        redirect($url);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editcustomcert', 'customcert'));
$mform->display();
echo $OUTPUT->footer();

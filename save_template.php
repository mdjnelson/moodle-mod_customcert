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
 * Handles saving customcert templates.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/locallib.php');
require_once($CFG->dirroot . '/mod/customcert/save_template_form.php');

$cmid = required_param('cmid', PARAM_INT);
$name = required_param('name', PARAM_TEXT);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

// Store the current time in a variable.
$time = time();

if (!$template = $DB->get_record('customcert_template', array('name' => $name))) {
    // Create the template.
    $template = new stdClass();
    $template->name = $name;
    $template->timecreated = $time;
}

$template->timemodified = $time;

if (empty($template->id)) {
    $template->id = $DB->insert_record('customcert_template', $template);
} else {
    $DB->update_record('customcert_template', $template);
    $templatepages = $DB->get_records_menu('customcert_template_pages', array('templateid' => $template->id));
    $DB->delete_records_list('customcert_template_elements', 'templatepageid', array_keys($templatepages));
    $DB->delete_records('customcert_template_pages', array('templateid' => $template->id));
}

// Get the pages of the customcert we are copying.
if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $customcert->id))) {
    // Create an array to store any errors saving an element to a template.
    $errors = array();
    // Loop through and copy the data.
    foreach ($pages as $page) {
        // Insert into the template page table.
        $templatepage = clone($page);
        $templatepage->templateid = $template->id;
        $templatepage->timecreated = $time;
        $templatepage->timemodified = $time;
        $templatepage->id = $DB->insert_record('customcert_template_pages', $templatepage);
        // Get the elements.
        if ($elements = $DB->get_records('customcert_elements', array('pageid' => $page->id))) {
            // Loop through the elements.
            foreach ($elements as $element) {
                // Insert into the template element table.
                $templateelement = clone($element);
                $templateelement->templatepageid = $templatepage->id;
                $templateelement->timecreated = $time;
                $templateelement->timemodified = $time;
                $templateelement->id = $DB->insert_record('customcert_template_elements', $templateelement);
                // Save any other information the element may need to for the template.
                if ($e = customcert_get_element_instance($element)) {
                    if (!$e->save_data_to_template($element)) {
                        // Remove from the customcert_template_elements table.
                        $DB->delete_records('customcert_template_elements', array('id' => $templateelement->id));
                        // Add the error message to the array to display later.
                        $errors[] = get_string('errorsavingelement', 'customcert', $element->element);
                    }
                }
            }
        }
    }
}

// Get any errors caused by the loading of an element and put into a message.
$message = '';
if (!empty($errors)) {
    foreach ($errors as $e) {
        $message .= $OUTPUT->notification($e) . '<br />';
    }
}

// Redirect.
$url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cmid));
redirect($url, $message);
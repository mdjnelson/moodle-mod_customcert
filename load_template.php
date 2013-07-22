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
 * Handles loading a customcert template.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->dirroot . '/mod/customcert/save_template_form.php');

$cmid = required_param('cmid', PARAM_INT);
$tid = required_param('tid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
$template = $DB->get_record('customcert_template', array('id' => $tid), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

// Check that they have confirmed they wish to load the template.
if ($confirm) {
    // First, remove all the existing elements and pages.
    $sql = "SELECT e.*
            FROM {customcert_elements} e
            INNER JOIN {customcert_pages} p
            ON e.pageid = p.id
            WHERE p.customcertid = :customcertid";
    if ($elements = $DB->get_records_sql($sql, array('customcertid' => $customcert->id))) {
        foreach ($elements as $element) {
            // Get an instance of the element class.
            if ($e = customcert_get_element_instance($element)) {
                $e->delete_element();
            }
        }
    }

    // Delete the pages.
    $DB->delete_records('customcert_pages', array('customcertid' => $customcert->id));

    // Store the current time in a variable.
    $time = time();

    // Now, get the template data.
    if ($templatepages = $DB->get_records('customcert_template_pages', array('templateid' => $tid))) {
        // Create an array to store any errors loading an element to a template.
        $errors = array();
        // Loop through the pages.
        foreach ($templatepages as $templatepage) {
            $page = clone($templatepage);
            $page->customcertid = $customcert->id;
            $page->timecreated = $time;
            $page->timemodified = $time;
            // Insert into the database.
            $page->id = $DB->insert_record('customcert_pages', $page);
            // Now go through the elements.
            if ($templateelements = $DB->get_records('customcert_template_elements', array('templatepageid' => $templatepage->id))) {
                foreach ($templateelements as $templateelement) {
                    $element = clone($templateelement);
                    $element->pageid = $page->id;
                    $element->timecreated = $time;
                    $element->timemodified = $time;
                    // Ok, now we want to insert this into the database.
                    $element->id = $DB->insert_record('customcert_elements', $element);
                    // Load any other information the element may need to for the template.
                    if ($e = customcert_get_element_instance($element)) {
                        if (!$e->load_data_from_template($element)) {
                            // Remove from the customcert_elements table.
                            $DB->delete_records('customcert_elements', array('id' => $element->id));
                            // Add the error message to the array to display later.
                            $errors[] = get_string('errorloadingelement', 'customcert', $element->element);
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
}


// Create the link options.
$nourl = new moodle_url('/mod/customcert/edit.php', array('cmid' => $cmid));
$yesurl = new moodle_url('/mod/customcert/load_template.php', array('cmid' => $cmid,
                                                                    'tid' => $tid,
                                                                    'confirm' => 1));
// Show a confirmation page.
$strheading = get_string('loadtemplate', 'customcert');
$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_url('/mod/customcert/load_template.php', array('cmid' => $cmid, 'tid' => $tid));
echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);
echo $OUTPUT->confirm(get_string('loadtemplatemsg', 'customcert'), $yesurl, $nourl);
echo $OUTPUT->footer();
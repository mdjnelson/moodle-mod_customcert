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
 * Handles viewing a report that shows who has received a customcert.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id   = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', \mod_customcert\certificate::CUSTOMCERT_PER_PAGE, PARAM_INT);
$pageurl = $url = new moodle_url('/mod/customcert/report.php', array('id' => $id, 'page' => $page, 'perpage' => $perpage));

$cm = get_coursemodule_from_id('customcert', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);

// Requires a course login.
require_login($course, false, $cm);

// Check capabilities.
$context = context_module::instance($cm->id);
require_capability('mod/customcert:manage', $context);

// Get the users who have been issued.
if ($groupmode = groups_get_activity_groupmode($cm)) {
    groups_get_activity_group($cm, true);
}

$table = new \mod_customcert\report_table($customcert->id, $cm, $groupmode);
$table->define_baseurl($pageurl);

if ($table->is_downloading($download, 'customcert-report')) {
    $table->download();
    exit();
}

// Set up the page.
\mod_customcert\page_helper::page_setup($pageurl, $context, get_string('customcertreport', 'customcert'));

// Additional page setup.
$PAGE->navbar->add(get_string('customcertreport', 'customcert'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'customcert'));

groups_print_activity_menu($cm, $url);

$table->out($perpage, false);

echo $OUTPUT->footer($course);

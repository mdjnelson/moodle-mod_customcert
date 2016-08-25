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
 * Manage customcert templates.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action) {
    $tid = required_param('tid', PARAM_INT);
} else {
    $tid = optional_param('tid', 0, PARAM_INT);
}

if ($tid) {
    $template = $DB->get_record('customcert_templates', array('id' => $tid), '*', MUST_EXIST);
    $template = new \mod_customcert\template($template);
}

$context = context::instance_by_id($contextid);

require_login();
require_capability('mod/customcert:manage', $context);

// Set up the page.
$pageurl = new moodle_url('/mod/customcert/manage_templates.php');
\mod_customcert\page_helper::page_setup($pageurl, $context, get_string('managetemplates', 'customcert'));

// Additional page setup.
$PAGE->navbar->add(get_string('managetemplates', 'customcert'));

// Check if we are deleting a template.
if ($tid) {
    if ($action == 'delete') {
        if (!$confirm) {
            $nourl = new moodle_url('/mod/customcert/manage_templates.php');
            $yesurl = new moodle_url('/mod/customcert/manage_templates.php', array('tid' => $tid,
                'action' => 'delete',
                'confirm' => 1,
                'sesskey' => sesskey()));

            // Show a confirmation page.
            $strheading = get_string('deleteconfirm', 'customcert');
            $PAGE->navbar->add($strheading);
            $PAGE->set_title($strheading);
            $message = get_string('deletetemplateconfirm', 'customcert');
            echo $OUTPUT->header();
            echo $OUTPUT->heading($strheading);
            echo $OUTPUT->confirm($message, $yesurl, $nourl);
            echo $OUTPUT->footer();
            exit();
        }

        // Delete the template.
        $template->delete();

        // Redirect back to the manage templates page.
        redirect(new moodle_url('/mod/customcert/manage_templates.php'));
    }
}
// Get all the templates that are available.
if ($templates = $DB->get_records('customcert_templates', array('contextid' => $contextid), 'timecreated DESC')) {
    // Create a table to display these elements.
    $table = new html_table();
    $table->head = array(get_string('name', 'customcert'), '');
    $table->align = array('left', 'center');

    foreach ($templates as $template) {
        // Link to edit the element.
        $editlink = new \moodle_url('/mod/customcert/edit.php', array('tid' => $template->id));
        $editicon = $OUTPUT->action_icon($editlink, new \pix_icon('t/edit', get_string('edit')));

        // Link to delete the element.
        $deletelink = new \moodle_url('/mod/customcert/manage_templates.php', array('tid' => $template->id,
            'action' => 'delete'));
        $deleteicon = $OUTPUT->action_icon($deletelink, new \pix_icon('t/delete', get_string('delete')));

        $row = new html_table_row();
        $row->cells[] = $template->name;
        $row->cells[] = $editicon . $deleteicon;
        $table->data[] = $row;
    }
}

echo $OUTPUT->header();
if (isset($table)) {
    echo html_writer::table($table);
} else {
    echo html_writer::tag('div', get_string('notemplates', 'customcert'), array('class' => 'alert'));
}
$url = new moodle_url('/mod/customcert/edit.php?contextid=' . $contextid);
echo $OUTPUT->single_button($url, get_string('createtemplate', 'customcert'), 'get');
echo $OUTPUT->footer();

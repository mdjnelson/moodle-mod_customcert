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
 * Edit a customcert element.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_customcert\edit_element_form;
use mod_customcert\element;
use mod_customcert\element_helper;
use mod_customcert\event\element_created;
use mod_customcert\event\element_updated;
use mod_customcert\event\template_updated;
use mod_customcert\page_helper;
use mod_customcert\service\element_factory;
use mod_customcert\service\form_service;
use mod_customcert\template;

require_once('../../config.php');

$tid = required_param('tid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$template = $DB->get_record('customcert_templates', ['id' => $tid], '*', MUST_EXIST);

// Set the template object.
$template = new template($template);

// Perform checks.
if ($cm = $template->get_cm()) {
    require_login($cm->course, false, $cm);
} else {
    require_login();
}
// Make sure the user has the required capabilities.
$template->require_manage();

if ($template->get_context()->contextlevel == CONTEXT_MODULE) {
    $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', MUST_EXIST);
    $title = $customcert->name;
} else {
    $title = $SITE->fullname;
}

if ($action == 'edit') {
    // The id of the element must be supplied if we are currently editing one.
    $id = required_param('id', PARAM_INT);
    $element = $DB->get_record('customcert_elements', ['id' => $id], '*', MUST_EXIST);
    $pageurl = new moodle_url('/mod/customcert/edit_element.php', ['id' => $id, 'tid' => $tid, 'action' => $action]);
} else { // Must be adding an element.
    // We need to supply what element we want added to what page.
    $pageid = required_param('pageid', PARAM_INT);
    $element = new stdClass();
    $element->element = required_param('element', PARAM_ALPHA);
    $pageurl = new moodle_url('/mod/customcert/edit_element.php', ['tid' => $tid, 'element' => $element->element,
        'pageid' => $pageid, 'action' => $action]);
}

// Set up the page.
page_helper::page_setup($pageurl, $template->get_context(), $title);
$PAGE->activityheader->set_attrs(['hidecompletion' => true,
            'description' => '']);

// Additional page setup.
if ($template->get_context()->contextlevel == CONTEXT_SYSTEM) {
    $PAGE->navbar->add(
        get_string('managetemplates', 'customcert'),
        new moodle_url('/mod/customcert/manage_templates.php')
    );
}
$PAGE->navbar->add(get_string('editcustomcert', 'customcert'), new moodle_url(
    '/mod/customcert/edit.php',
    ['tid' => $tid]
));
$PAGE->navbar->add(get_string('editelement', 'customcert'));

$mform = new edit_element_form($pageurl, ['element' => $element]);

// Check if they cancelled.
if ($mform->is_cancelled()) {
    $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
    redirect($url);
}

if ($data = $mform->get_data()) {
    // Set the id, or page id depending on if we are editing an element, or adding a new one.
    if ($action == 'edit') {
        $data->id = $id;
        $data->pageid = $element->pageid;
    } else {
        $data->pageid = $pageid;
    }
    // Set the element variable.
    $data->element = $element->element;

    // Normalise submission: process file uploads from draft areas before saving.
    $formservice = new form_service();
    $dataarray = (array) $data;
    $formservice->normalise_submission($dataarray);
    // Merge back any changes (e.g., file metadata populated from fileid).
    foreach ($dataarray as $key => $value) {
        $data->$key = $value;
    }

    // Get an instance of the element class (legacy element) so we can use save_unique_data().
    if ($e = element_factory::get_element_instance($data)) {
        // Build record similar to legacy element::save_form_elements(), but without calling the deprecated method.
        $record = new stdClass();
        $record->name = $data->name;
        $record->data = $e->save_unique_data($data);
        $record->font = $data->font ?? null;
        $record->fontsize = $data->fontsize ?? null;
        $record->colour = $data->colour ?? null;
        if (!empty(get_config('customcert', 'showposxy'))) {
            $record->posx = $data->posx ?? null;
            $record->posy = $data->posy ?? null;
        }
        $record->width = $data->width ?? null;
        $record->refpoint = $data->refpoint ?? null;
        $record->alignment = $data->alignment ?? element::ALIGN_LEFT;
        $record->timemodified = time();

        if (!empty($data->id)) {
            // Update existing element.
            $record->id = $data->id;
            $DB->update_record('customcert_elements', $record);

            // Fire updated event for the element.
            element_updated::create_from_id((int)$record->id, $template)->trigger();
            $newlyid = $record->id;
        } else {
            // Insert new element.
            $record->element = $data->element;
            $record->pageid = $data->pageid;
            $record->sequence = element_helper::get_element_sequence($record->pageid);
            $record->timecreated = time();
            $record->id = (int)$DB->insert_record('customcert_elements', $record, true);

            // Fire created event for the new element.
            // We cannot easily instantiate the new element type here; trigger directly via DB context.
            // Fallback to building created event via page/template lookup.
            try {
                $page = $DB->get_record('customcert_pages', ['id' => $record->pageid], '*', MUST_EXIST);
                $templateforctx = $DB->get_record('customcert_templates', ['id' => $page->templateid], '*', MUST_EXIST);
                element_created::create([
                    'contextid' => (int)$templateforctx->contextid,
                    'objectid' => (int)$record->id,
                ])->trigger();
            } catch (\Throwable $ex) {
                // Best-effort; do not block. Assign to a local variable to avoid empty catch warnings.
                $ignoredexception = $ex;
                unset($ignoredexception);
            }

            $newlyid = $record->id;
        }

        // Trigger updated event for the template containing the element.
        template_updated::create_from_template($template)->trigger();
    }

    $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
    $editurl = new moodle_url('/mod/customcert/edit_element.php', [
            'id' => $newlyid,
            'tid' => $tid,
            'action' => 'edit',
    ]);
    $redirecturl = $url;

    if (isset($data->saveandcontinue)) {
        $redirecturl = ($action === 'add') ? $editurl : $PAGE->url;
    }
    redirect($redirecturl);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();

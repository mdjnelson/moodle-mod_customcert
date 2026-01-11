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
use mod_customcert\event\template_updated;
use mod_customcert\page_helper;
use mod_customcert\element\element_bootstrap;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_repository;
use mod_customcert\service\form_service;
use mod_customcert\service\persistence_helper;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use mod_customcert\template;

require_once('../../config.php');

$templaterepo = new template_repository();
$pagerepo = new page_repository();
$registry = new element_registry();
element_bootstrap::register_defaults($registry);
$factory = new element_factory($registry);
$elementrepo = new element_repository($factory);

$tid = required_param('tid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

// Set the template object.
$template = template::load((int)$tid);

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
    $element = $elementrepo->get_by_id_or_fail($id);
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

    // Get an instance of the element class.
    $elementinstance = element_factory::get_element_instance($data);
    if ($elementinstance) {
        // Build record similar to legacy element::save_form_elements().
        $record = new stdClass();
        $record->pageid = (int)$data->pageid;
        $record->element = (string)$data->element;
        $record->name = $data->name;

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            // Preserve existing positional fields when not provided in the form.
            $record->posx = $element->posx ?? null;
            $record->posy = $element->posy ?? null;
            $record->refpoint = $element->refpoint ?? null;
            $record->alignment = $element->alignment ?? element::ALIGN_LEFT;
        }
        // Persist JSON using helper (supports persistable and legacy elements).
        $record->data = persistence_helper::to_json_data($elementinstance, $data);
        // Merge font-related fields into JSON 'data' rather than separate DB columns.
        $rawjson = $record->data;
        $decoded = is_string($rawjson) && $rawjson !== '' ? json_decode($rawjson, true) : null;
        if (!is_array($decoded)) {
            // Start from an envelope if we had scalar/non-JSON previously.
            $decoded = [];
        }
        if (isset($data->font) && $data->font !== '') {
            $decoded['font'] = (string)$data->font;
        }
        if (isset($data->fontsize) && $data->fontsize !== '') {
            $decoded['fontsize'] = (int)$data->fontsize;
        }
        if (isset($data->colour) && $data->colour !== '') {
            $decoded['colour'] = (string)$data->colour;
        }
        if (!empty(get_config('customcert', 'showposxy'))) {
            $record->posx = $data->posx ?? null;
            $record->posy = $data->posy ?? null;
        }
        // Merge width into JSON 'data' rather than a dropped DB column.
        if (isset($data->width) && $data->width !== '') {
            $decoded['width'] = (int)$data->width;
        }
        // Persist the merged JSON payload.
        $record->data = json_encode($decoded);
        $record->refpoint = $data->refpoint ?? $record->refpoint ?? null;
        $record->alignment = $data->alignment ?? $record->alignment ?? element::ALIGN_LEFT;

        $instance = $factory->create($record->element, $record);

        if (!empty($record->id)) {
            $elementrepo->save($instance);
            $newlyid = $record->id;
        } else {
            $newlyid = $elementrepo->create($instance);
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

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
 * Edit the customcert settings.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use mod_customcert\edit_form;
use mod_customcert\load_template_form;
use mod_customcert\local\preview_renderer;
use mod_customcert\page_helper;
use mod_customcert\service\item_move_service;
use mod_customcert\service\certificate_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;

require_once('../../config.php');

$pagerepo = new page_repository();
$templaterepo = new template_repository();
$templateservice = template_service::create();
$pdfservice = pdf_generation_service::create();

$tid = optional_param('tid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
if ($action) {
    $actionid = required_param('aid', PARAM_INT);
}
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($tid) {
    $template = template::from_record($templaterepo->get_by_id_or_fail((int)$tid));
    $contextid = $template->get_contextid();
    $pageurl = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
} else { // Adding a new template.
    $contextid = required_param('contextid', PARAM_INT);
    $pageurl = new moodle_url('/mod/customcert/edit.php', ['contextid' => $contextid]);
}

$context = context::instance_by_id($contextid);
if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id('customcert', $context->instanceid, 0, false, MUST_EXIST);
    require_login($cm->course, false, $cm);

    $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', MUST_EXIST);
    $title = $customcert->name;
} else {
    require_login();
    $title = $SITE->fullname;
}

require_capability('mod/customcert:manage', $context);

page_helper::page_setup($pageurl, $context, $title);
$PAGE->activityheader->set_attrs(['hidecompletion' => true,
            'description' => '']);

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $PAGE->navbar->add(
        get_string('managetemplates', 'customcert'),
        new moodle_url('/mod/customcert/manage_templates.php')
    );
    if (!$tid) {
        $PAGE->navbar->add(get_string('editcustomcert', 'customcert'));
    } else {
        $PAGE->navbar->add(
            get_string('editcustomcert', 'customcert'),
            new moodle_url('/mod/customcert/edit.php', ['tid' => $tid])
        );
    }
}

$deleting = false;

if ($tid) {
    if ($action && confirm_sesskey()) {
        switch ($action) {
            case 'pmoveup':
                $templateservice->move_item(
                    $template,
                    item_move_service::ITEM_PAGE,
                    $actionid,
                    item_move_service::DIRECTION_UP
                );
                break;
            case 'pmovedown':
                $templateservice->move_item(
                    $template,
                    item_move_service::ITEM_PAGE,
                    $actionid,
                    item_move_service::DIRECTION_DOWN
                );
                break;
            case 'emoveup':
                $templateservice->move_item(
                    $template,
                    item_move_service::ITEM_ELEMENT,
                    $actionid,
                    item_move_service::DIRECTION_UP
                );
                break;
            case 'emovedown':
                $templateservice->move_item(
                    $template,
                    item_move_service::ITEM_ELEMENT,
                    $actionid,
                    item_move_service::DIRECTION_DOWN
                );
                break;
            case 'addpage':
                $templateservice->add_page($template);
                $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
                redirect($url);
                break;
            case 'deletepage':
                if (!empty($confirm)) {
                    $templateservice->delete_page($template, $actionid);
                    $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
                    redirect($url);
                } else {
                    $deleting = true;
                    $message = get_string('deletepageconfirm', 'customcert');
                    $nourl = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
                    $yesurl = new moodle_url(
                        '/mod/customcert/edit.php',
                        [
                            'tid' => $tid,
                            'action' => 'deletepage',
                            'aid' => $actionid,
                            'confirm' => 1,
                            'sesskey' => sesskey(),
                        ]
                    );
                }
                break;
            case 'deleteelement':
                if (!empty($confirm)) {
                    $templateservice->delete_element($template, $actionid);
                } else {
                    $deleting = true;
                    $message = get_string('deleteelementconfirm', 'customcert');
                    $nourl = new moodle_url('/mod/customcert/edit.php', ['tid' => $tid]);
                    $yesurl = new moodle_url(
                        '/mod/customcert/edit.php',
                        [
                            'tid' => $tid,
                            'action' => 'deleteelement',
                            'aid' => $actionid,
                            'confirm' => 1,
                            'sesskey' => sesskey(),
                        ]
                    );
                }
                break;
        }
    }
}

if ($deleting) {
    $PAGE->navbar->add(get_string('deleteconfirm', 'customcert'));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm($message, $yesurl, $nourl);
    echo $OUTPUT->footer();
    exit();
}

if ($tid) {
    $mform = new edit_form($pageurl, ['tid' => $tid]);
    $mform->set_data(['name' => $template->get_name()]);
} else {
    $mform = new edit_form($pageurl);
}

if ($data = $mform->get_data()) {
    if (!$tid) {
        $template = template::create($data->name, $contextid);

        $pageid = $templateservice->add_page($template, false);

        $width = 'pagewidth_' . $pageid;
        $height = 'pageheight_' . $pageid;
        $leftmargin = 'pageleftmargin_' . $pageid;
        $rightmargin = 'pagerightmargin_' . $pageid;
        $rightmargin = 'pagerightmargin_' . $pageid;

        $data->$width = $data->pagewidth_0;
        $data->$height = $data->pageheight_0;
        $data->$leftmargin = $data->pageleftmargin_0;
        $data->$rightmargin = $data->pagerightmargin_0;

        // We may also have clicked to add an element, so these need changing as well.
        if (isset($data->element_0) && isset($data->addelement_0)) {
            $element = 'element_' . $pageid;
            $addelement = 'addelement_' . $pageid;
            $data->$element = $data->element_0;
            $data->$addelement = $data->addelement_0;

            // Need to remove the temporary element and add element placeholders so we
            // don't try add an element to the wrong page.
            unset($data->element_0);
            unset($data->addelement_0);
        }
    }

    if ($tid) {
        $templateservice->update($template, $data);
    }

    // Save any page data and emit template_updated when changes occur.
    $templateservice->save_pages($template, $data, true);

    foreach ($data as $key => $value) {
        if (strpos($key, 'addelement_') !== false) {
            $pageid = str_replace('addelement_', '', $key);
            $element = "element_" . $pageid;
            $element = $data->$element;
            $params = [];
            $params['tid'] = $template->get_id();
            $params['action'] = 'add';
            $params['element'] = $element;
            $params['pageid'] = $pageid;
            $url = new moodle_url('/mod/customcert/edit_element.php', $params);
            redirect($url);
        }
    }

    if (!empty($data->previewbtn)) {
        $preview = preview_renderer::create();
        $pdf = $pdfservice->create_preview_pdf($template, $USER);

        $pages = $pagerepo->list_by_template($template->get_id());
        foreach ($pages as $page) {
            $preview->render_pdf_page((int)$page->id, $pdf, $USER);
        }

        // Compute preview filename using the same rules as generate_pdf().
        $customcert = (new certificate_repository())->get_by_template_id($template->get_id());
        $pdffilename = $pdfservice->compute_filename_for_user($template, $USER, $customcert);
        $pdf->Output($pdffilename, pdf_generation_service::DELIVERY_OPTION_INLINE);
        exit();
    }

    $url = new moodle_url('/mod/customcert/edit.php', ['tid' => $template->get_id()]);
    redirect($url);
}

echo $OUTPUT->header();
$mform->display();
if ($tid && $context->contextlevel == CONTEXT_MODULE) {
    $loadtemplateurl = new moodle_url('/mod/customcert/load_template.php', ['tid' => $tid]);
    $loadtemplateform = new load_template_form(
        $loadtemplateurl,
        ['context' => $context],
        'post',
        '',
        ['id' => 'loadtemplateform']
    );
    $loadtemplateform->display();
}
echo $OUTPUT->footer();

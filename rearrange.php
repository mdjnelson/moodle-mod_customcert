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
 * Handles position elements on the PDF via drag and drop.
 *
 * The interactive editor is a React ESM component mounted via Moodle's
 * data-react-component auto-init mechanism. Element preview HTML and edit forms
 * remain server-rendered so third-party customcertelement_* plugins keep working.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use action_link;
use mod_customcert\page_helper;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use mod_customcert\template;

require_once('../../config.php');

// The page of the customcert we are editing.
$pid = required_param('pid', PARAM_INT);

$pagerepo = new page_repository();
$page = $pagerepo->get_by_id_or_fail($pid);

$factory = element_factory::build_with_defaults();
$elementrepo = new element_repository($factory);

$elementrecords = $elementrepo->list_by_page($pid);
$elementinstances = [];
foreach ($elementrepo->load_by_page_id($pid) as $instance) {
    $elementinstances[$instance->get_id()] = $instance;
}

// Set the template.
$template = template::from_record((new template_repository())->get_by_id_or_fail((int)$page->templateid));
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
    $heading = format_string($title);
} else {
    $title = $SITE->fullname;
    $heading = $title;
}

// Set the $PAGE settings.
$pageurl = new moodle_url('/mod/customcert/rearrange.php', ['pid' => $pid]);
page_helper::page_setup($pageurl, $template->get_context(), $title);
$PAGE->activityheader->set_attrs(['hidecompletion' => true,
            'description' => '']);

// Add more links to the navigation.
if (!$cm = $template->get_cm()) {
    $str = get_string('managetemplates', 'customcert');
    $link = new moodle_url('/mod/customcert/manage_templates.php');
    $PAGE->navbar->add($str, new action_link($link, $str));
}

$str = get_string('editcustomcert', 'customcert');
$link = new moodle_url('/mod/customcert/edit.php', ['tid' => $template->get_id()]);
$PAGE->navbar->add($str, new action_link($link, $str));

$PAGE->navbar->add(get_string('rearrangeelements', 'customcert'));

// Build element props with server-rendered preview HTML (element-type agnostic).
// Save / cancel controls are rendered by the React rearranger.
$elementsdata = [];
foreach ($elementrecords as $element) {
    $instance = $elementinstances[(int)$element->id] ?? null;
    if (!$instance) {
        continue;
    }

    $elementsdata[] = [
        'id' => (int)$element->id,
        'name' => (string)$element->name,
        'posx' => (int)($element->posx ?? 0),
        'posy' => (int)($element->posy ?? 0),
        'width' => $instance->get_width(),
        'refpoint' => (int)($element->refpoint ?? 0),
        'alignment' => (string)($element->alignment ?? 'L'),
        'html' => $instance->render_html(),
    ];
}

$editurl = (new moodle_url('/mod/customcert/edit.php', ['tid' => $template->get_id()]))->out(false);
$rearrangeurl = (new moodle_url('/mod/customcert/rearrange.php', ['pid' => $pid]))->out(false);

$reactprops = [
    'templateid' => $template->get_id(),
    'contextid' => $template->get_contextid(),
    'pageid' => (int)$pid,
    'page' => [
        'width' => (float)$page->width,
        'height' => (float)$page->height,
        'leftmargin' => (float)($page->leftmargin ?? 0),
        'rightmargin' => (float)($page->rightmargin ?? 0),
    ],
    'elements' => $elementsdata,
    'editurl' => $editurl,
    'rearrangeurl' => $rearrangeurl,
];

// Mount the React rearranger; Moodle's react_autoinit picks this up from data attributes.
$html = html_writer::react_component('@moodle/lms/mod_customcert/Rearrange', $reactprops);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rearrangeelementsheading', 'customcert'), 3);
echo $OUTPUT->notification(get_string('exampledatawarning', 'customcert'), \core\output\notification::NOTIFY_WARNING);
echo $html;
echo $OUTPUT->footer();

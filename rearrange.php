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
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/customcert/locallib.php');

// The page of the customcert we are editing.
$pid = required_param('id', PARAM_INT);

$page = $DB->get_record('customcert_pages', array('id' => $pid), '*', MUST_EXIST);
$elements = $DB->get_records('customcert_elements', array('pageid' => $pid), 'sequence');
$cm = get_coursemodule_from_instance('customcert', $page->customcertid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/customcert:manage', $context);

// Set the $PAGE settings.
$PAGE->set_url(new moodle_url('/mod/customcert/rearrange.php', array('id' => $pid)));
$PAGE->set_pagetype('mod-customcert-position');
$PAGE->set_title(get_string('rearrangeelements', 'customcert'));
$PAGE->set_heading($course->fullname);

// Include the JS we need.
$module = array(
    'name' => 'mod_customcert',
    'fullpath' => '/mod/customcert/yui/src/rearrange.js',
    'requires' => array('dd-delegate', 'dd-drag')
);
$PAGE->requires->js_init_call('M.mod_customcert.rearrange.init', array($cm->id, $page, $elements), false, $module);

// Create the buttons to save the position of the elements.
$html = html_writer::start_tag('div', array('class' => 'buttons'));
$html .= $OUTPUT->single_button(new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id)),
        get_string('saveandclose', 'customcert'), 'get', array('class' => 'savepositionsbtn'));
$html .= $OUTPUT->single_button(new moodle_url('/mod/customcert/rearrange.php', array('id' => $pid)),
        get_string('saveandcontinue', 'customcert'), 'get', array('class' => 'applypositionsbtn'));
$html .= $OUTPUT->single_button(new moodle_url('/mod/customcert/edit.php', array('cmid' => $cm->id)),
        get_string('cancel'), 'get', array('class' => 'cancelbtn'));
$html .= html_writer::end_tag('div');

// Create the div that represents the PDF.
$style = 'height: ' . $page->height . 'mm; line-height: normal; width: ' . $page->width . 'mm;';
$marginstyle = 'height: ' . $page->height . 'mm; width:1px; float:left; position:relative;';
$html .= html_writer::start_tag('div', array('id' => 'pdf', 'style' => $style));
if ($page->leftmargin) {
    $position = 'left:' . $page->leftmargin . 'mm;';
    $html .= "<div id='leftmargin' style='$position $marginstyle'></div>";
}
if ($elements) {
    foreach ($elements as $element) {
        // Get an instance of the element class.
        if ($e = customcert_get_element_instance($element)) {
            switch ($element->refpoint) {
                case CUSTOMCERT_REF_POINT_TOPRIGHT:
                    $class = 'element refpoint-right';
                    break;
                case CUSTOMCERT_REF_POINT_TOPCENTER:
                    $class = 'element refpoint-center';
                    break;
                case CUSTOMCERT_REF_POINT_TOPLEFT:
                default:
                $class = 'element refpoint-left';
            }
            $html .= html_writer::tag('div', $e->render_html(), array('class' => $class, 'id' => 'element-' . $element->id));
        }
    }
}
if ($page->rightmargin) {
    $position = 'left:' . ($page->width - $page->rightmargin) . 'mm;';
    $html .= "<div id='rightmargin' style='$position $marginstyle'></div>";
}
$html .= html_writer::end_tag('div');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editcustomcert', 'customcert'));
echo $OUTPUT->heading(get_string('rearrangeelementsheading', 'customcert'), 4);
echo $html;
echo $OUTPUT->footer();
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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/customcert/lib.php');

/**
 * Instance add/edit form.
 *
 * @package    mod
 * @subpackage customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_edit_form extends moodleform {

    /**
     * The instance id.
     */
    protected $id = null;

    /**
     * The course.
     */
    protected $course = null;

    /**
     * The total number of pages for this cert.
     */
    protected $numpages = 1;

    /**
     * The orientation options.
     */
    protected $orientationoptions = array();

    /**
     * The image options.
     */
    protected $imageoptions = array();

    /**
     * The filemanager options.
     */
    protected $filemanageroptions = array();

    /**
     * Form definition.
     */
    function definition() {
        global $CFG, $DB, $OUTPUT;

        $this->id = $this->_customdata['customcertid'];
        $this->orientationoptions = array('L' => get_string('landscape', 'customcert'),
                                          'P' => get_string('portrait', 'customcert'));
        $this->imageoptions = customcert_get_images();
        $this->filemanageroptions = array('maxbytes' => $this->_customdata['course']->maxbytes,
                                          'subdirs' => 1,
                                          'accepted_types' => 'image');

        $mform =& $this->_form;

        // Get the number of pages for this module.
        if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $this->id), 'pagenumber')) {
            $this->numpages = count($pages);
            foreach ($pages as $p) {
                $this->add_customcert_page_elements($p);
            }
        } else {
            $this->add_customcert_page_elements();
        }

        $mform->closeHeaderBefore('addcertpage');

        $mform->addElement('submit', 'addcertpage', get_string('addcertpage', 'customcert'));

        $mform->addElement('header', 'uploadimage', get_string('uploadimage', 'customcert'));

        $mform->addElement('filemanager', 'customcertimage', get_string('uploadimage', 'customcert'), '', $this->filemanageroptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->id);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $this->_customdata['cmid']);

        $this->add_action_buttons();
    }

    /**
     * Fill in the current page data for this certificate.
     */
    function definition_after_data() {
        global $DB;

        $mform = $this->_form;

        // Editing existing instance - copy existing files into draft area.
        $draftitemid = file_get_submitted_draft_itemid('customcertimage');
        file_prepare_draft_area($draftitemid, context_system::instance()->id, 'mod_customcert', 'image', 0, $this->filemanageroptions);
        $element = $mform->getElement('customcertimage');
        $element->setValue($draftitemid);

        // Check that we are updating a current customcert.
        if ($this->id) {
            // Get the pages for this certificate.
            if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $this->id))) {
                // Loop through the pages.
                foreach ($pages as $p) {
                    // Set the orientation.
                    $element = $mform->getElement('orientation_'.$p->id);
                    $element->setValue($p->orientation);
                    // Set the width.
                    $element = $mform->getElement('width_'.$p->id);
                    $element->setValue($p->width);
                    // Set the height.
                    $element = $mform->getElement('height_'.$p->id);
                    $element->setValue($p->height);
                    // Set the background image.
                    $element = $mform->getElement('backgroundimage_'.$p->id);
                    $element->setValue($p->backgroundimage);
                    // Now get the page text fields.
                    if ($textfields = $DB->get_records('customcert_text_fields', array())) {

                    }
                }
            }
        }
    }

    /**
     * Some basic validation.
     *
     * @param $data
     * @param $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Go through the data and check any width or height values.
        foreach ($data as $key => $value) {
            if (strpos($key, 'width_') !== false) {
                $page = str_replace('width_', '', $key);
                // Validate that the weight is a valid value.
                if (!isset($data['width_'.$page]) || !is_number($data['width_'.$page])) {
                    $errors['width_'.$page] = get_string('widthnotvalid', 'customcert');
                }
            }
            if (strpos($key, 'height_') !== false) {
                $page = str_replace('height_', '', $key);
                // Validate that the height is a valid value.
                if (!isset($data['height_'.$page]) || !is_number($data['height_'.$page])) {
                    $errors['height_'.$page] = get_string('heightnotvalid', 'customcert');
                }
            }
        }

        return $errors;
    }

    /**
     * Adds the page elements to the form.
     *
     * @param stdClass $page the customcert page
     **/
    private function add_customcert_page_elements($page = null) {
        global $DB, $OUTPUT;

        // Create the form object.
        $mform =& $this->_form;

        // If page is null we are adding a customcert, not editing one, so set identifier to 1.
        if (is_null($page)) {
            $identifier = 1;
            $pagenum = 1;
        } else {
            $identifier = $page->id;
            $pagenum = $page->pagenumber;
        }

        $mform->addElement('header', 'page_'.$identifier, get_string('page', 'customcert', $pagenum));

        // Place the ordering arrows.
        // Only display the move up arrow if it is not the first.
        if ($pagenum > 1) {
            $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $this->_customdata['cmid'], 'moveup' => $identifier));
            $mform->addElement('html', $OUTPUT->action_icon($url, new pix_icon('t/up', get_string('moveup'))));
        }
        // Only display the move down arrow if it is not the last.
        if ($pagenum < $this->numpages) {
            $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $this->_customdata['cmid'], 'movedown' => $identifier));
            $mform->addElement('html', $OUTPUT->action_icon($url, new pix_icon('t/down', get_string('movedown'))));
        }

        $mform->addElement('select', 'orientation_'.$identifier, get_string('orientation', 'customcert'), $this->orientationoptions);
        $mform->setDefault('orientation_'.$identifier, 'P');
        $mform->addHelpButton('orientation_'.$identifier, 'orientation', 'customcert');

        $mform->addElement('text', 'width_'.$identifier, get_string('width', 'customcert'));
        $mform->addRule('width_'.$identifier, null, 'required', null, 'client');
        $mform->addHelpButton('width_'.$identifier, 'width', 'customcert');

        $mform->addElement('text', 'height_'.$identifier, get_string('height', 'customcert'));
        $mform->addRule('height_'.$identifier, null, 'required', null, 'client');
        $mform->addHelpButton('height_'.$identifier, 'height', 'customcert');

        // Get the other image options.
        $mform->addElement('select', 'backgroundimage_'.$identifier, get_string('backgroundimage', 'customcert'), $this->imageoptions);
        $mform->setDefault('backgroundimage_'.$identifier, 0);
        $mform->addHelpButton('backgroundimage_'.$identifier, 'backgroundimage', 'customcert');

        // Add text fields.
        $textgroup = array();
        $textgroup[] =& $mform->createElement('text', 'certtext_'.$identifier, '',
            array('cols' => '40', 'rows' => '4', 'wrap' => 'virtual'));
        $group = $mform->createElement('group', 'customcerttextgroup_'.$identifier,
            get_string('addtext', 'customcert'), $textgroup);

        $count = (is_null($page)) ? 1 : $DB->count_records('customcert_text_fields', array('customcertpageid' => $identifier)) + 1;
        $this->repeat_elements(array($group), $count, array(), 'customcertimagerepeats_'.$identifier, 'imageadd_'.$identifier, 1,
            get_string('addanothertextfield', 'customcert'), true);

        // Add option to delete this page if it is not the first page.
        if ($pagenum > 1) {
            $mform->addElement('html', '<div class=\'deletecertpage\'>');
            $mform->addElement('submit', 'deletecertpage_'.$identifier, get_string('deletecertpage', 'customcert'));
            $mform->addElement('html', '</div>');
        }
    }
}

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

namespace customcertelement_bgimage;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element background image's core interaction API.
 *
 * @package    customcertelement_bgimage
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \customcertelement_image\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        $mform->addElement('select', 'image', get_string('image', 'customcertelement_image'), self::get_images());
        $mform->addElement('filemanager', 'customcertimage', get_string('uploadimage', 'customcert'), '', $this->filemanageroptions);
    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        // Array to return the errors.
        return array();
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'pathnamehash' => $data->image,
            'width' => 0,
            'height' => 0
        );

        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        global $CFG;

        // If there is no element data, we have nothing to display.
        if (empty($this->element->data)) {
            return;
        }

        $imageinfo = json_decode($this->element->data);

        // Get the image.
        $fs = get_file_storage();
        if ($file = $fs->get_file_by_hash($imageinfo->pathnamehash)) {
            $contenthash = $file->get_contenthash();
            $l1 = $contenthash[0] . $contenthash[1];
            $l2 = $contenthash[2] . $contenthash[3];
            $location = $CFG->dataroot . '/filedir' . '/' . $l1 . '/' . $l2 . '/' . $contenthash;

            // Set the image to the size of the PDF page.
            $pdf->Image($location, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight());
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        global $DB;

        // If there is no element data, we have nothing to display.
        if (empty($this->element->data)) {
            return '';
        }

        $imageinfo = json_decode($this->element->data);

        // Get the image.
        $fs = get_file_storage();
        if ($file = $fs->get_file_by_hash($imageinfo->pathnamehash)) {
            $url = \moodle_url::make_pluginfile_url($file->get_contextid(), 'mod_customcert', 'image', $file->get_itemid(),
                $file->get_filepath(), $file->get_filename());
            // Get the page we are rendering this on.
            $page = $DB->get_record('customcert_pages', array('id' => $this->element->pageid), '*', MUST_EXIST);

            // Set the image to the size of the page.
            $style = 'width: ' . $page->width . 'mm; height: ' . $page->height . 'mm';
            return \html_writer::tag('img', '', array('src' => $url, 'style' => $style));
        }
    }
}


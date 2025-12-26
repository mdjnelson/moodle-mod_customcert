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
 * This file contains the customcert element image's core interaction API.
 *
 * @package    customcertelement_image
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_image;

use context_course;
use context_system;
use core_collator;
use html_writer;
use mod_customcert\certificate;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element_helper;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\dynamic_selects_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use moodle_url;
use pdf;
use restore_customcert_activity_task;
use stdClass;
use stored_file;

/**
 * The customcert element image's core interaction API.
 *
 * @package    customcertelement_image
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    dynamic_selects_interface,
    element_interface,
    form_definable_interface,
    preparable_form_interface
{
    /**
     * @var array The file manager options.
     */
    protected $filemanageroptions = [];

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    public function __construct($element) {
        global $COURSE;

        $this->filemanageroptions = [
            'maxbytes' => $COURSE->maxbytes,
            'subdirs' => 1,
            'accepted_types' => 'image',
        ];

        parent::__construct($element);
    }

    /**
     * Define the configuration fields for this element in the same order as before the refactor.
     * The form builder renders fields exactly in this order, mapping standard names to helpers.
     *
     * @return array
     */
    public function get_form_fields(): array {
        $alphachannelvalues = [
            '0' => 0,
            '0.1' => 0.1,
            '0.2' => 0.2,
            '0.3' => 0.3,
            '0.4' => 0.4,
            '0.5' => 0.5,
            '0.6' => 0.6,
            '0.7' => 0.7,
            '0.8' => 0.8,
            '0.9' => 0.9,
            '1' => 1,
        ];

        return [
            'fileid' => [
                'type' => 'select',
                'label' => get_string('image', 'customcertelement_image'),
            ],
            // Standard fields in the expected order.
            'width' => [],
            'height' => [],
            'alphachannel' => [
                'type' => 'select',
                'label' => get_string('alphachannel', 'customcertelement_image'),
                'options' => $alphachannelvalues,
                'type_param' => PARAM_FLOAT,
                'default' => 1,
                'help' => ['alphachannel', 'customcertelement_image'],
            ],
            // Position controls (rendered once when enabled).
            'posx' => [],
            'posy' => [],
            // Upload image last.
            'customcertimage' => [
                'type' => 'filemanager',
                'label' => get_string('uploadimage', 'customcert'),
                'options' => $this->filemanageroptions,
            ],
        ];
    }

    /**
     * Advertise dynamic selects to be populated centrally by the form service.
     *
     * @return array
     */
    public function get_dynamic_selects(): array {
        return [
            'fileid' => [self::class, 'get_images'],
        ];
    }

    /**
     * Prepare form defaults and draft areas for the image element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        global $COURSE, $SITE;

        // If element has an image stored, select it in the dropdown (only when metadata exists).
        if (!empty($this->get_data())) {
            $imageinfo = json_decode($this->get_data());
            if (
                is_object($imageinfo)
                && isset(
                    $imageinfo->contextid,
                    $imageinfo->filearea,
                    $imageinfo->itemid,
                    $imageinfo->filepath,
                    $imageinfo->filename
                )
            ) {
                if ($file = $this->get_file()) {
                    $mform->setDefault('fileid', $file->get_id());
                }
            }

            // Populate size/alpha fields from stored JSON data if present.
            if (isset($imageinfo->width)) {
                $mform->setDefault('width', (int)$imageinfo->width);
            }
            if (isset($imageinfo->height)) {
                $mform->setDefault('height', (int)$imageinfo->height);
            }
            if (isset($imageinfo->alphachannel)) {
                $mform->setDefault('alphachannel', (float)$imageinfo->alphachannel);
            }
        }

        // Prepare the draft area for the uploader so previously uploaded files show up.
        if ($COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }
        $draftitemid = file_get_submitted_draft_itemid('customcertimage');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_customcert', 'image', 0, $this->filemanageroptions);
        $mform->getElement('customcertimage')->setValue($draftitemid);
    }


    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        global $COURSE, $SITE;

        // Set the context.
        if ($COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }

        // Handle file uploads.
        if (isset($data->customcertimage)) {
            certificate::upload_files($data->customcertimage, $context->id);
        }

        $arrtostore = [
            'width' => !empty($data->width) ? (int) $data->width : 0,
            'height' => !empty($data->height) ? (int) $data->height : 0,
        ];

        if (isset($data->alphachannel)) {
            $arrtostore['alphachannel'] = (float) $data->alphachannel;
        }

        if (!empty($data->fileid)) {
            // Array of data we will be storing in the database.
            $fs = get_file_storage();
            if ($file = $fs->get_file_by_id($data->fileid)) {
                $arrtostore += [
                    'contextid' => $file->get_contextid(),
                    'filearea' => $file->get_filearea(),
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ];
            }
        }

        return json_encode($arrtostore);
    }

    /**
     * Returns the configured width for this image element.
     *
     * These elements store width/height inside their JSON `data` payload, not in the standard
     * element `width` column. Override to expose the JSON value to forms and renderers consistently.
     *
     * @return int|null Width in mm, or null if not set.
     */
    public function get_width(): ?int {
        $data = $this->get_data();

        if (empty($data)) {
            return null;
        }

        $decoded = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

        return isset($decoded->width) && $decoded->width !== ''
            ? (int) $decoded->width
            : null;
    }

    /**
     * Returns the configured height for this image element from JSON data.
     *
     * @return int|null Height in mm, or null if not set.
     */
    public function get_height(): ?int {
        $data = $this->get_data();

        if (empty($data)) {
            return null;
        }

        $decoded = json_decode($data);

        return isset($decoded->height) && $decoded->height !== ''
            ? (int) $decoded->height
            : null;
    }


    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $imageinfo = json_decode($this->get_data());

        // If there is no file, we have nothing to display.
        if (empty($imageinfo->filename)) {
            return;
        }

        if ($file = $this->get_file()) {
            if ($renderer) {
                $this->render_html($renderer);
            } else {
                $location = make_request_directory() . '/target';
                $file->copy_content_to($location);

                // Check if the alpha channel is set, if it is, use it.
                if (isset($imageinfo->alphachannel)) {
                    $pdf->SetAlpha($imageinfo->alphachannel);
                }

                $mimetype = $file->get_mimetype();
                if ($mimetype == 'image/svg+xml') {
                    $pdf->ImageSVG($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
                } else {
                    $pdf->Image($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
                }

                // Restore to full opacity.
                $pdf->SetAlpha(1);
            }
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        $imageinfo = json_decode($this->get_data());

        // If there is no file, we have nothing to display.
        if (empty($imageinfo->filename)) {
            return '';
        }

        // Get the image.
        $fs = get_file_storage();
        if (
            $file = $fs->get_file(
                $imageinfo->contextid,
                'mod_customcert',
                $imageinfo->filearea,
                $imageinfo->itemid,
                $imageinfo->filepath,
                $imageinfo->filename
            )
        ) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'mod_customcert',
                'image',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
            $fileimageinfo = $file->get_imageinfo();
            $whratio = $fileimageinfo['width'] / $fileimageinfo['height'];
            // The size of the images to use in the CSS style.
            $style = '';
            if ($imageinfo->width === 0 && $imageinfo->height === 0) {
                $style .= 'width: ' . $fileimageinfo['width'] . 'px; ';
                $style .= 'height: ' . $fileimageinfo['height'] . 'px';
            } else if ($imageinfo->width === 0) { // Then the height must be set.
                // We must get the width based on the height to keep the ratio.
                $style .= 'width: ' . ($imageinfo->height * $whratio) . 'mm; ';
                $style .= 'height: ' . $imageinfo->height . 'mm';
            } else if ($imageinfo->height === 0) { // Then the width must be set.
                $style .= 'width: ' . $imageinfo->width . 'mm; ';
                // We must get the height based on the width to keep the ratio.
                $style .= 'height: ' . ($imageinfo->width / $whratio) . 'mm';
            } else { // Must both be set.
                $style .= 'width: ' . $imageinfo->width . 'mm; ';
                $style .= 'height: ' . $imageinfo->height . 'mm';
            }

            $content = html_writer::tag('img', '', ['src' => $url, 'style' => $style]);

            if ($renderer) {
                return (string) $renderer->render_content($this, $content);
            }

            return $content;
        }

        return '';
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param MoodleQuickForm $mform the edit_form instance
     */
    // Deprecated legacy API no longer used by this element.

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the file's pathname hash.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        global $DB;

        // Get the current data we have stored for this element.
        $elementinfo = json_decode($this->get_data());

        // Update the context.
        $elementinfo->contextid = context_course::instance($restore->get_courseid())->id;

        // Encode again before saving.
        $elementinfo = json_encode($elementinfo);

        // Perform the update.
        $DB->set_field('customcert_elements', 'data', $elementinfo, ['id' => $this->get_id()]);
    }

    /**
     * Fetch stored file.
     *
     * @return stored_file|bool stored_file instance if exists, false if not
     */
    public function get_file() {
        $imageinfo = json_decode($this->get_data());

        $fs = get_file_storage();

        return $fs->get_file(
            $imageinfo->contextid,
            'mod_customcert',
            $imageinfo->filearea,
            $imageinfo->itemid,
            $imageinfo->filepath,
            $imageinfo->filename
        );
    }

    /**
     * Return the list of possible images to use.
     *
     * @return array the list of images that can be used
     */
    public static function get_images() {
        global $COURSE;

        // Create file storage object.
        $fs = get_file_storage();

        // The array used to store the images.
        $arrfiles = [];
        // Loop through the files uploaded in the system context.
        if ($files = $fs->get_area_files(context_system::instance()->id, 'mod_customcert', 'image', false, 'filename', false)) {
            foreach ($files as $hash => $file) {
                $arrfiles[$file->get_id()] = get_string('systemimage', 'customcertelement_image', $file->get_filename());
            }
        }
        // Loop through the files uploaded in the course context.
        if (
            $files = $fs->get_area_files(
                context_course::instance($COURSE->id)->id,
                'mod_customcert',
                'image',
                false,
                'filename',
                false
            )
        ) {
            foreach ($files as $hash => $file) {
                $arrfiles[$file->get_id()] = get_string('courseimage', 'customcertelement_image', $file->get_filename());
            }
        }

        core_collator::asort($arrfiles);
        $arrfiles = ['0' => get_string('noimage', 'customcert')] + $arrfiles;

        return $arrfiles;
    }

    /**
     * This handles copying data from another element of the same type.
     *
     * @param stdClass $data the form data
     * @return bool returns true if the data was copied successfully, false otherwise
     */
    public function copy_element($data) {
        global $COURSE, $DB, $SITE;

        $imagedata = json_decode($data->data);

        // If we are in the site context we don't have to do anything, the image is already there.
        if ($COURSE->id == $SITE->id) {
            return true;
        }

        $coursecontext = context_course::instance($COURSE->id);
        $systemcontext = context_system::instance();

        $fs = get_file_storage();

        // Check that a file has been selected.
        if (isset($imagedata->filearea)) {
            // If the course file doesn't exist, copy the system file to the course context.
            if (
                !$coursefile = $fs->get_file(
                    $coursecontext->id,
                    'mod_customcert',
                    $imagedata->filearea,
                    $imagedata->itemid,
                    $imagedata->filepath,
                    $imagedata->filename
                )
            ) {
                $systemfile = $fs->get_file(
                    $systemcontext->id,
                    'mod_customcert',
                    $imagedata->filearea,
                    $imagedata->itemid,
                    $imagedata->filepath,
                    $imagedata->filename
                );

                // We want to update the context of the file if it doesn't exist in the course context.
                $fieldupdates = [
                    'contextid' => $coursecontext->id,
                ];
                $coursefile = $fs->create_file_from_storedfile($fieldupdates, $systemfile);
            }

            // Set the image to the copied file in the course.
            $imagedata->fileid = $coursefile->get_id();
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($imagedata), ['id' => $this->get_id()]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function has_save_and_continue(): bool {
        return true;
    }
}

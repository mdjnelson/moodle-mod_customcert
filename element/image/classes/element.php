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
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element as base_element;
use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\element\form_buildable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\element\validatable_element_interface;
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
    constructable_element_interface,
    element_interface,
    form_buildable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    restorable_element_interface,
    validatable_element_interface
{
    /**
     * @var array The file manager options.
     */
    protected array $filemanageroptions = [];

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
     * Build an element instance from a DB record.
     *
     * @param stdClass $record Raw DB row from customcert_elements.
     * @return static
     */
    public static function from_record(stdClass $record): static {
        return new static($record);
    }

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('select', 'fileid', get_string('image', 'customcertelement_image'), self::get_images());

        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_height($mform);

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

        $mform->addElement(
            'select',
            'alphachannel',
            get_string('alphachannel', 'customcertelement_image'),
            $alphachannelvalues
        );
        $mform->setType('alphachannel', PARAM_FLOAT);
        $mform->setDefault('alphachannel', 1);
        $mform->addHelpButton('alphachannel', 'alphachannel', 'customcertelement_image');

        if (get_config('customcert', 'showposxy')) {
            element_helper::render_form_element_position($mform);
        }

        $mform->addElement(
            'filemanager',
            'customcertimage',
            get_string('uploadimage', 'customcert'),
            '',
            $this->filemanageroptions
        );
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
            $payload = $this->get_payload();
            if (
                isset(
                    $payload['contextid'],
                    $payload['filearea'],
                    $payload['itemid'],
                    $payload['filepath'],
                    $payload['filename']
                )
            ) {
                if ($file = $this->get_file()) {
                    $mform->setDefault('fileid', $file->get_id());
                }
            }

            // Populate size/alpha fields from stored JSON data if present.
            if (isset($payload['width'])) {
                $mform->setDefault('width', (int)$payload['width']);
            }
            if (isset($payload['height'])) {
                $mform->setDefault('height', (int)$payload['height']);
            }
            if (isset($payload['alphachannel'])) {
                $mform->setDefault('alphachannel', (float)$payload['alphachannel']);
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
     * Normalise user picture element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        global $COURSE, $SITE;

        // Set the context.
        if ($COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }

        // Handle file uploads.
        if (isset($formdata->customcertimage)) {
            certificate::upload_files($formdata->customcertimage, $context->id);
        }

        $arrtostore = [
            'width' => !empty($formdata->width) ? (int) $formdata->width : 0,
            'height' => !empty($formdata->height) ? (int) $formdata->height : 0,
        ];

        if (isset($formdata->alphachannel)) {
            $arrtostore['alphachannel'] = (float) $formdata->alphachannel;
        }

        if (!empty($formdata->fileid)) {
            // Array of data we will be storing in the database.
            $fs = get_file_storage();
            if ($file = $fs->get_file_by_id($formdata->fileid)) {
                $arrtostore += [
                    'contextid' => $file->get_contextid(),
                    'filearea' => $file->get_filearea(),
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ];
            }
        }

        return $arrtostore;
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
        $payload = $this->get_payload();
        if (empty($payload)) {
            return null;
        }
        return isset($payload['height']) && $payload['height'] !== ''
            ? (int)$payload['height']
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

        $payload = $this->get_payload();

        // If there is no file, we have nothing to display.
        if (empty($payload['filename'])) {
            return;
        }

        if ($file = $this->get_file()) {
            if ($renderer) {
                $this->render_html($renderer);
            } else {
                $location = make_request_directory() . '/target';
                $file->copy_content_to($location);

                // Check if the alpha channel is set, if it is, use it.
                if (isset($payload['alphachannel'])) {
                    $pdf->SetAlpha((float)$payload['alphachannel']);
                }

                $mimetype = $file->get_mimetype();
                if ($mimetype == 'image/svg+xml') {
                    $pdf->ImageSVG(
                        $location,
                        $this->get_posx(),
                        $this->get_posy(),
                        (int)($payload['width'] ?? 0),
                        (int)($payload['height'] ?? 0)
                    );
                } else {
                    $pdf->Image(
                        $location,
                        $this->get_posx(),
                        $this->get_posy(),
                        (int)($payload['width'] ?? 0),
                        (int)($payload['height'] ?? 0)
                    );
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

        $payload = $this->get_payload();

        // If there is no file, we have nothing to display.
        if (empty($payload['filename'])) {
            return '';
        }

        // Get the image.
        $fs = get_file_storage();
        if (
            $file = $fs->get_file(
                (int)$payload['contextid'],
                'mod_customcert',
                (string)$payload['filearea'],
                (int)$payload['itemid'],
                (string)$payload['filepath'],
                (string)$payload['filename']
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
            $w = (int)($payload['width'] ?? 0);
            $h = (int)($payload['height'] ?? 0);
            if ($w === 0 && $h === 0) {
                $style .= 'width: ' . $fileimageinfo['width'] . 'px; ';
                $style .= 'height: ' . $fileimageinfo['height'] . 'px';
            } else if ($w === 0) { // Then the height must be set.
                // We must get the width based on the height to keep the ratio.
                $style .= 'width: ' . ($h * $whratio) . 'mm; ';
                $style .= 'height: ' . $h . 'mm';
            } else if ($h === 0) { // Then the width must be set.
                $style .= 'width: ' . $w . 'mm; ';
                // We must get the height based on the width to keep the ratio.
                $style .= 'height: ' . ($w / $whratio) . 'mm';
            } else { // Must both be set.
                $style .= 'width: ' . $w . 'mm; ';
                $style .= 'height: ' . $h . 'mm';
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
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the file's pathname hash.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        // Get the current data we have stored for this element.
        $payload = $this->get_payload();

        // Update the context.
        $payload['contextid'] = context_course::instance($restore->get_courseid())->id;

        // Encode again before saving.
        $elementinfo = json_encode($payload);

        // Perform the update.
        $DB->set_field('customcert_elements', 'data', $elementinfo, ['id' => $this->get_id()]);
    }

    /**
     * Fetch stored file.
     *
     * @return stored_file|bool stored_file instance if exists, false if not
     */
    public function get_file() {
        $payload = $this->get_payload();

        $fs = get_file_storage();

        return $fs->get_file(
            (int)$payload['contextid'],
            'mod_customcert',
            (string)$payload['filearea'],
            (int)$payload['itemid'],
            (string)$payload['filepath'],
            (string)$payload['filename']
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
    public function copy_element(stdClass $data): bool {
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
            $jsondata = json_encode($this->normalise_data($imagedata));
            $DB->set_field(
                'customcert_elements',
                'data',
                $jsondata,
                ['id' => $this->get_id()]
            );
        }

        return true;
    }

    /**
     * Validate submitted form data for this element.
     * Core validations are handled by validation_service; no extra rules here.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        return [];
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

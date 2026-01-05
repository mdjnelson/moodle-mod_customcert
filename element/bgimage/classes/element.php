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
 * This file contains the customcert element background image's core interaction API.
 *
 * @package    customcertelement_bgimage
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_bgimage;

use html_writer;
use mod_customcert\element\field_type;
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_renderer;
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\dynamic_selects_interface;
use mod_customcert\element\preparable_form_interface;
use moodle_url;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * The customcert element background image's core interaction API.
 *
 * @package    customcertelement_bgimage
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \customcertelement_image\element implements
    constructable_element_interface,
    dynamic_selects_interface,
    form_definable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Background image covers the whole page; width/height fields are ignored and
     * always treated as auto-fit. Override getters to prevent stale values.
     */
    public function get_width(): ?int {
        return 0;
    }
    /**
     * Background image covers the whole page; width/height fields are ignored and
     * always treated as auto-fit. Override getters to prevent stale values.
     *
     * @return int|null
     */
    public function get_height(): ?int {
        return 0;
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
     * Define the configuration fields for this element in the same order as before the refactor.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [
            'fileid' => [
                'type' => field_type::select,
                'label' => get_string('image', 'customcertelement_image'),
            ],
            'customcertimage' => [
                'type' => field_type::filemanager,
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
     * Prepare form defaults and draft areas for the background image element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        global $COURSE, $SITE;

        // If element has an image stored, select it in the dropdown.
        if (!empty($this->get_data())) {
            $payload = $this->get_payload();
            // Only attempt get_file() if required metadata is present.
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
                    $mform->getElement('fileid')->setValue($file->get_id());
                }
            }
        }

        // Prepare the draft area for the uploader so previously uploaded files show up.
        if ($COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }

        // Mirror Image element exactly: draft area for component 'mod_customcert', filearea 'image', itemid 0.
        $draftitemid = file_get_submitted_draft_itemid('customcertimage');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_customcert', 'image', 0, $this->filemanageroptions);
        $mform->getElement('customcertimage')->setValue($draftitemid);
    }

    /**
     * Normalise background image element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        // Prepare data to store; form service will have populated file metadata when applicable.
        $arrtostore = [];

        // If a file was selected in the dropdown, persist its metadata so we can resolve it later.
        if (!empty($formdata->fileid)) {
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
        } else if (!empty($this->get_data())) {
            // Preserve existing metadata if no new selection was provided.
            $existing = json_decode($this->get_data(), true);
            if (is_array($existing)) {
                $arrtostore += array_intersect_key(
                    $existing,
                    array_flip(['contextid', 'filearea', 'itemid', 'filepath', 'filename'])
                );
            }
        }

        return $arrtostore;
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
            $location = make_request_directory() . '/target';
            $file->copy_content_to($location);

            // Set the image to the size of the PDF page.
            $mimetype = $file->get_mimetype();
            if ($mimetype == 'image/svg+xml') {
                $pdf->ImageSVG($location, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight());
            } else {
                $pdf->Image($location, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight());
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
        global $DB;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        $imageinfo = json_decode($this->get_data());

        // If there is no file, we have nothing to display.
        if (empty($imageinfo->filename)) {
            return '';
        }

        if ($file = $this->get_file()) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'mod_customcert',
                'image',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
            // Get the page we are rendering this on.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);

            // Set the image to the size of the page.
            $style = 'width: ' . $page->width . 'mm; height: ' . $page->height . 'mm';
            return html_writer::tag('img', '', ['src' => $url, 'style' => $style]);
        }

        return '';
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

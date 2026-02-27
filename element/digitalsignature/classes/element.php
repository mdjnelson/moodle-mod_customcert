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
 * This file contains the customcert element digitial signature's core interaction API.
 *
 * @package    customcertelement_digitalsignature
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_digitalsignature;

use context_course;
use context_system;
use core_collator;
use mod_customcert\certificate;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element\form_buildable_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;
use stored_file;

/**
 * The customcert element digital signature's core interaction API.
 *
 * @package    customcertelement_digitalsignature
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \customcertelement_image\element implements
    form_buildable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * @var array The file manager options for the certificate.
     */
    protected array $signaturefilemanageroptions = [];

    /**
     * Constructor.
     *
     * @param stdClass $element the element data
     */
    public function __construct($element) {
        global $COURSE;

        $this->signaturefilemanageroptions = [
            'maxbytes' => $COURSE->maxbytes,
            'subdirs' => 1,
            'accepted_types' => ['.crt'],
        ];

        parent::__construct($element);
    }

    /**
     * Render the element in html for preview positioning.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        if ($renderer) {
            return (string)$renderer->render_content($this, '');
        }
        return '';
    }

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        // Image selector first.
        $mform->addElement('select', 'fileid', get_string('image', 'customcertelement_image'), self::get_images());

        // Existing signature selection.
        $mform->addElement(
            'select',
            'signaturefileid',
            get_string('digitalsignature', 'customcertelement_digitalsignature'),
            self::get_signatures()
        );

        // Signature metadata fields.
        $mform->addElement('text', 'signaturename', get_string('signaturename', 'customcertelement_digitalsignature'));
        $mform->setType('signaturename', PARAM_TEXT);
        $mform->setDefault('signaturename', '');

        $mform->addElement(
            'passwordunmask',
            'signaturepassword',
            get_string('signaturepassword', 'customcertelement_digitalsignature')
        );
        $mform->setType('signaturepassword', PARAM_TEXT);
        $mform->setDefault('signaturepassword', '');

        $mform->addElement('text', 'signaturelocation', get_string('signaturelocation', 'customcertelement_digitalsignature'));
        $mform->setType('signaturelocation', PARAM_TEXT);
        $mform->setDefault('signaturelocation', '');

        $mform->addElement('text', 'signaturereason', get_string('signaturereason', 'customcertelement_digitalsignature'));
        $mform->setType('signaturereason', PARAM_TEXT);
        $mform->setDefault('signaturereason', '');

        $mform->addElement(
            'text',
            'signaturecontactinfo',
            get_string('signaturecontactinfo', 'customcertelement_digitalsignature')
        );
        $mform->setType('signaturecontactinfo', PARAM_TEXT);
        $mform->setDefault('signaturecontactinfo', '');

        // Width and height fields.
        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_height($mform);

        // Position fields (if enabled).
        if (get_config('customcert', 'showposxy')) {
            element_helper::render_form_element_position($mform);
        }

        // Uploaders last.
        $mform->addElement(
            'filemanager',
            'customcertimage',
            get_string('uploadimage', 'customcert'),
            '',
            $this->filemanageroptions
        );

        $mform->addElement(
            'filemanager',
            'digitalsignature',
            get_string('uploaddigitalsignature', 'customcertelement_digitalsignature'),
            '',
            $this->signaturefilemanageroptions
        );
    }

    /**
     * Prepare form defaults and draft areas for the digital signature element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        global $COURSE, $SITE;

        // Populate signature-related fields from stored JSON data when editing.
        if (!empty($this->get_data())) {
            $payload = $this->get_payload();

            // Populate signature metadata fields.
            if (isset($payload['signaturename'])) {
                $mform->setDefault('signaturename', (string)$payload['signaturename']);
            }
            if (isset($payload['signaturepassword'])) {
                $mform->setDefault('signaturepassword', (string)$payload['signaturepassword']);
            }
            if (isset($payload['signaturelocation'])) {
                $mform->setDefault('signaturelocation', (string)$payload['signaturelocation']);
            }
            if (isset($payload['signaturereason'])) {
                $mform->setDefault('signaturereason', (string)$payload['signaturereason']);
            }
            if (isset($payload['signaturecontactinfo'])) {
                $mform->setDefault('signaturecontactinfo', (string)$payload['signaturecontactinfo']);
            }

            // Populate signature file select if a signature file is stored.
            if (!empty($payload['signaturefilename'])) {
                if ($signaturefile = $this->get_signature_file()) {
                    $mform->setDefault('signaturefileid', $signaturefile->get_id());
                }
            }

            // Populate image file select if an image file is stored.
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

            // Populate size controls via defaults so they survive set_data lifecycle.
            if (isset($payload['width'])) {
                $mform->setDefault('width', (int)$payload['width']);
            }
            if (isset($payload['height'])) {
                $mform->setDefault('height', (int)$payload['height']);
            }
        }

        // Prepare the draft areas for the uploaders so previously uploaded files show up.
        if ($COURSE->id == $SITE->id) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }

        $draftitemid = file_get_submitted_draft_itemid('customcertimage');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_customcert', 'image', 0, $this->filemanageroptions);
        $mform->getElement('customcertimage')->setValue($draftitemid);

        $draftitemid = file_get_submitted_draft_itemid('digitalsignature');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_customcert', 'signature', 0, $this->signaturefilemanageroptions);
        $mform->getElement('digitalsignature')->setValue($draftitemid);
    }

    /**
     * Returns the configured width for this element from JSON data.
     *
     * @return int|null
     */
    public function get_width(): ?int {
        $payload = $this->get_payload();
        if (empty($payload)) {
            return null;
        }
        return isset($payload['width']) && $payload['width'] !== '' ? (int)$payload['width'] : null;
    }

    /**
     * Returns the configured height for this element from JSON data.
     *
     * @return int|null
     */
    public function get_height(): ?int {
        $payload = $this->get_payload();
        if (empty($payload)) {
            return null;
        }
        return isset($payload['height']) && $payload['height'] !== '' ? (int)$payload['height'] : null;
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
     * Normalise digital signature element data.
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

        // Handle file certificate uploads.
        if (isset($formdata->digitalsignature)) {
            certificate::upload_files($formdata->digitalsignature, $context->id, 'signature');
        }

        $arrtostore = [
            'signaturename' => $formdata->signaturename ?? '',
            'signaturepassword' => $formdata->signaturepassword ?? '',
            'signaturelocation' => $formdata->signaturelocation ?? '',
            'signaturereason' => $formdata->signaturereason ?? '',
            'signaturecontactinfo' => $formdata->signaturecontactinfo ?? '',
            'width' => !empty($formdata->width) ? (int) $formdata->width : 0,
            'height' => !empty($formdata->height) ? (int) $formdata->height : 0,
        ];

        // Array of data we will be storing in the database.
        $fs = get_file_storage();

        if (!empty($formdata->fileid)) {
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

        if (!empty($formdata->signaturefileid)) {
            if ($signaturefile = $fs->get_file_by_id($formdata->signaturefileid)) {
                $arrtostore += [
                    'signaturecontextid' => $signaturefile->get_contextid(),
                    'signaturefilearea' => $signaturefile->get_filearea(),
                    'signatureitemid' => $signaturefile->get_itemid(),
                    'signaturefilepath' => $signaturefile->get_filepath(),
                    'signaturefilename' => $signaturefile->get_filename(),
                ];
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

        // If there is no signature file, we have nothing to display.
        if (empty($payload['signaturefilename'])) {
            return;
        }

        if ($file = $this->get_file()) {
            $location = make_request_directory() . '/target';
            $file->copy_content_to($location);

            $mimetype = $file->get_mimetype();
            if ($mimetype == 'image/svg+xml') {
                $pdf->ImageSVG($location, $this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
            } else {
                $pdf->Image($location, $this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
            }
        }

        if ($signaturefile = $this->get_signature_file()) {
            $location = make_request_directory() . '/target';
            $signaturefile->copy_content_to($location);
            $info = [
                'Name' => (string)($payload['signaturename'] ?? ''),
                'Location' => (string)($payload['signaturelocation'] ?? ''),
                'Reason' => (string)($payload['signaturereason'] ?? ''),
                'ContactInfo' => (string)($payload['signaturecontactinfo'] ?? ''),
            ];
            $pdf->setSignature('file://' . $location, '', (string)($payload['signaturepassword'] ?? ''), '', 2, $info);
            $pdf->setSignatureAppearance($this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
        }
    }

    /**
     * Return the list of possible images to use.
     *
     * @return array the list of images that can be used
     */
    public static function get_signatures() {
        global $COURSE;

        // Create file storage object.
        $fs = get_file_storage();

        // The array used to store the digital signatures.
        $arrfiles = [];
        // Loop through the files uploaded in the system context.
        if (
            $files = $fs->get_area_files(
                context_system::instance()->id,
                'mod_customcert',
                'signature',
                false,
                'filename',
                false
            )
        ) {
            foreach ($files as $hash => $file) {
                $arrfiles[$file->get_id()] = $file->get_filename();
            }
        }
        // Loop through the files uploaded in the course context.
        if (
            $files = $fs->get_area_files(
                context_course::instance($COURSE->id)->id,
                'mod_customcert',
                'signature',
                false,
                'filename',
                false
            )
        ) {
            foreach ($files as $hash => $file) {
                $arrfiles[$file->get_id()] = $file->get_filename();
            }
        }

        core_collator::asort($arrfiles);
        $arrfiles = ['0' => get_string('nosignature', 'customcertelement_digitalsignature')] + $arrfiles;

        return $arrfiles;
    }

    /**
     * Fetch stored file.
     *
     * @return stored_file|bool stored_file instance if exists, false if not
     */
    public function get_signature_file() {
        $payload = $this->get_payload();

        $fs = get_file_storage();

        return $fs->get_file(
            (int)$payload['signaturecontextid'],
            'mod_customcert',
            (string)$payload['signaturefilearea'],
            (int)$payload['signatureitemid'],
            (string)$payload['signaturefilepath'],
            (string)$payload['signaturefilename']
        );
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

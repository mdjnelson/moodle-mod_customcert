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
use mod_customcert\element\form_definable_interface;
use mod_customcert\element\dynamic_selects_interface;
use mod_customcert\element\preparable_form_interface;
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
    dynamic_selects_interface,
    form_definable_interface,
    preparable_form_interface
{
    /**
     * @var array The file manager options for the certificate.
     */
    protected $signaturefilemanageroptions = [];

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
     * Define the configuration fields for this element in the same order as before the refactor.
     *
     * @return array
     */
    public function get_form_fields(): array {
        return [
            // Image selector first.
            'fileid' => [
                'type' => 'select',
                'label' => get_string('image', 'customcertelement_image'),
            ],

            // Existing signature selection.
            'signaturefileid' => [
                'type' => 'select',
                'label' => get_string('digitalsignature', 'customcertelement_digitalsignature'),
            ],

            // Signature metadata fields.
            'signaturename' => [
                'type' => 'text',
                'label' => get_string('signaturename', 'customcertelement_digitalsignature'),
                'type_param' => PARAM_TEXT,
                'default' => '',
            ],
            'signaturepassword' => [
                'type' => 'passwordunmask',
                'label' => get_string('signaturepassword', 'customcertelement_digitalsignature'),
                'type_param' => PARAM_TEXT,
                'default' => '',
            ],
            'signaturelocation' => [
                'type' => 'text',
                'label' => get_string('signaturelocation', 'customcertelement_digitalsignature'),
                'type_param' => PARAM_TEXT,
                'default' => '',
            ],
            'signaturereason' => [
                'type' => 'text',
                'label' => get_string('signaturereason', 'customcertelement_digitalsignature'),
                'type_param' => PARAM_TEXT,
                'default' => '',
            ],
            'signaturecontactinfo' => [
                'type' => 'text',
                'label' => get_string('signaturecontactinfo', 'customcertelement_digitalsignature'),
                'type_param' => PARAM_TEXT,
                'default' => '',
            ],

            // Standard placement controls.
            'width' => [],
            'height' => [],
            'posx' => [],
            'posy' => [],

            // Uploaders last.
            'customcertimage' => [
                'type' => 'filemanager',
                'label' => get_string('uploadimage', 'customcert'),
                'options' => $this->filemanageroptions,
            ],
            'digitalsignature' => [
                'type' => 'filemanager',
                'label' => get_string('uploaddigitalsignature', 'customcertelement_digitalsignature'),
                'options' => $this->signaturefilemanageroptions,
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
            'signaturefileid' => [self::class, 'get_signatures'],
        ];
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
            $imageinfo = json_decode($this->get_data());

            if (is_object($imageinfo)) {
                // Populate signature metadata fields.
                if (isset($imageinfo->signaturename)) {
                    $mform->setDefault('signaturename', $imageinfo->signaturename);
                }
                if (isset($imageinfo->signaturepassword)) {
                    $mform->setDefault('signaturepassword', $imageinfo->signaturepassword);
                }
                if (isset($imageinfo->signaturelocation)) {
                    $mform->setDefault('signaturelocation', $imageinfo->signaturelocation);
                }
                if (isset($imageinfo->signaturereason)) {
                    $mform->setDefault('signaturereason', $imageinfo->signaturereason);
                }
                if (isset($imageinfo->signaturecontactinfo)) {
                    $mform->setDefault('signaturecontactinfo', $imageinfo->signaturecontactinfo);
                }

                // Populate signature file select if a signature file is stored.
                if (!empty($imageinfo->signaturefilename)) {
                    if ($signaturefile = $this->get_signature_file()) {
                        $mform->setDefault('signaturefileid', $signaturefile->get_id());
                    }
                }

                // Populate image file select if an image file is stored.
                if (
                    isset(
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

                // Populate size controls via defaults so they survive set_data lifecycle.
                if (isset($imageinfo->width)) {
                    $mform->setDefault('width', (int)$imageinfo->width);
                }
                if (isset($imageinfo->height)) {
                    $mform->setDefault('height', (int)$imageinfo->height);
                }
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
        $data = $this->get_data();
        if (empty($data)) {
            return null;
        }
        $decoded = json_decode($data);
        return isset($decoded->width) && $decoded->width !== '' ? (int)$decoded->width : null;
    }

    /**
     * Returns the configured height for this element from JSON data.
     *
     * @return int|null
     */
    public function get_height(): ?int {
        $data = $this->get_data();
        if (empty($data)) {
            return null;
        }
        $decoded = json_decode($data);
        return isset($decoded->height) && $decoded->height !== '' ? (int)$decoded->height : null;
    }

    /**
     * Handles saving the form elements created by this element.
     * Can be overridden if more functionality is needed.
     *
     * @param stdClass $data the form data
     * @return bool true of success, false otherwise.
     */
    public function save_form_elements($data) {
        global $COURSE, $SITE;

        // Set the context.
        if ($COURSE->id == $SITE->id) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($COURSE->id);
        }

        // Handle file uploads.
        certificate::upload_files($data->customcertimage, $context->id);

        // Handle file certificate uploads.
        certificate::upload_files($data->digitalsignature, $context->id, 'signature');

        return parent::save_form_elements($data);
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

        // Handle file certificate uploads.
        if (isset($data->digitalsignature)) {
            certificate::upload_files($data->digitalsignature, $context->id, 'signature');
        }

        $arrtostore = [
            'signaturename' => $data->signaturename,
            'signaturepassword' => $data->signaturepassword,
            'signaturelocation' => $data->signaturelocation,
            'signaturereason' => $data->signaturereason,
            'signaturecontactinfo' => $data->signaturecontactinfo,
            'width' => !empty($data->width) ? (int) $data->width : 0,
            'height' => !empty($data->height) ? (int) $data->height : 0,
        ];

        // Array of data we will be storing in the database.
        $fs = get_file_storage();

        if (!empty($data->fileid)) {
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

        if (!empty($data->signaturefileid)) {
            if ($signaturefile = $fs->get_file_by_id($data->signaturefileid)) {
                $arrtostore += [
                    'signaturecontextid' => $signaturefile->get_contextid(),
                    'signaturefilearea' => $signaturefile->get_filearea(),
                    'signatureitemid' => $signaturefile->get_itemid(),
                    'signaturefilepath' => $signaturefile->get_filepath(),
                    'signaturefilename' => $signaturefile->get_filename(),
                ];
            }
        }

        return json_encode($arrtostore);
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

        // If there is no signature file, we have nothing to display.
        if (empty($imageinfo->signaturefilename)) {
            return;
        }

        if ($file = $this->get_file()) {
            $location = make_request_directory() . '/target';
            $file->copy_content_to($location);

            $mimetype = $file->get_mimetype();
            if ($mimetype == 'image/svg+xml') {
                $pdf->ImageSVG($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
            } else {
                $pdf->Image($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
            }
        }

        if ($signaturefile = $this->get_signature_file()) {
            $location = make_request_directory() . '/target';
            $signaturefile->copy_content_to($location);
            $info = [
                'Name' => $imageinfo->signaturename,
                'Location' => $imageinfo->signaturelocation,
                'Reason' => $imageinfo->signaturereason,
                'ContactInfo' => $imageinfo->signaturecontactinfo,
            ];
            $pdf->setSignature('file://' . $location, '', $imageinfo->signaturepassword, '', 2, $info);
            $pdf->setSignatureAppearance($this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
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
        $imageinfo = json_decode($this->get_data());

        $fs = get_file_storage();

        return $fs->get_file(
            $imageinfo->signaturecontextid,
            'mod_customcert',
            $imageinfo->signaturefilearea,
            $imageinfo->signatureitemid,
            $imageinfo->signaturefilepath,
            $imageinfo->signaturefilename
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

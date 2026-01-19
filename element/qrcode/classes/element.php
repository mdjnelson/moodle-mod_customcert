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
 * This file contains the customcert element QR code's core interaction API.
 *
 * @package    customcertelement_qrcode
 * @copyright  2019 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_qrcode;

use mod_customcert\element as base_element;
use mod_customcert\element\constructable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\form_buildable_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use moodle_url;
use pdf;
use stdClass;
use TCPDF2DBarcode;
use Throwable;


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');

/**
 * The customcert element QR code's core interaction API.
 *
 * @package    customcertelement_qrcode
 * @copyright  2019 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    constructable_element_interface,
    element_interface,
    form_buildable_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_height($mform);
        if (get_config('customcert', 'showposxy')) {
            element_helper::render_form_element_position($mform);
        }
    }

    /**
     * @var string The barcode type.
     */
    const BARCODETYPE = 'QRCODE';

    /**
     * Normalise QR code element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'width' => isset($formdata->width) ? (int)$formdata->width : 0,
            'height' => isset($formdata->height) ? (int)$formdata->height : 0,
        ];
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
     * Prepare the form by populating the width and height fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->get_payload();
        if (isset($payload['width'])) {
            // Use defaults so the values persist across set_data()/definition_after_data.
            $mform->setDefault('width', (int)$payload['width']);
        }
        if (isset($payload['height'])) {
            $mform->setDefault('height', (int)$payload['height']);
        }
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
        global $DB;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $payload = $this->get_payload();

        if ($preview) {
            // Generate the URL to verify this.
            $qrcodeurl = new moodle_url('/');
            $qrcodeurl = $qrcodeurl->out(false);
        } else {
            // Get the information we need.
            $sql = "SELECT c.id, c.verifyany, ct.contextid, ci.code
                      FROM {customcert_issues} ci
                      JOIN {customcert} c
                        ON ci.customcertid = c.id
                      JOIN {customcert_templates} ct
                        ON c.templateid = ct.id
                      JOIN {customcert_pages} cp
                        ON cp.templateid = ct.id
                     WHERE ci.userid = :userid
                       AND cp.id = :pageid";

            // Now we can get the issue for this user.
            $issue = $DB->get_record_sql(
                $sql,
                ['userid' => $user->id, 'pageid' => $this->get_pageid()],
                '*',
                MUST_EXIST
            );
            $code = $issue->code;

            $context = context::instance_by_id($issue->contextid);

            $urlparams = [
                'code' => $code,
                'qrcode' => 1,
            ];

            // We only add the 'contextid' to the link if the site setting for verifying all certificates is off,
            // or if the individual certificate doesn't allow verification. However, if the user has the
            // mod/customcert:verifyallcertificates then they can verify anything regardless.
            $verifyallcertificatessitesetting = get_config('customcert', 'verifyallcertificates');
            $verifycertificateactivitysettings = $issue->verifyany;
            $canverifyallcertificates = has_capability('mod/customcert:verifyallcertificates', $context);
            if (
                (!$verifyallcertificatessitesetting || !$verifycertificateactivitysettings)
                    && !$canverifyallcertificates
            ) {
                $urlparams['contextid'] = $issue->contextid;
            }

            $qrcodeurl = new moodle_url('/mod/customcert/verify_certificate.php', $urlparams);
            $qrcodeurl = $qrcodeurl->out(false);
        }

        if ($renderer) {
            $this->render_html($renderer);
        } else {
            try {
                $barcode = new TCPDF2DBarcode($qrcodeurl, self::BARCODETYPE);
                $image = $barcode->getBarcodePngData((int)$payload['width'], (int)$payload['height']);

                $location = make_request_directory() . '/target';
                file_put_contents($location, $image);

                $pdf->Image($location, $this->get_posx(), $this->get_posy(), (int)$payload['width'], (int)$payload['height']);
            } catch (Throwable $e) {
                if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                    debugging('QR code render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }

                return;
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

        $qrcodeurl = new moodle_url('/');
        $qrcodeurl = $qrcodeurl->out(false);

        try {
            $barcode = new TCPDF2DBarcode($qrcodeurl, self::BARCODETYPE);
            $content = $barcode->getBarcodeHTML(((int)$payload['width']) / 10, ((int)$payload['height']) / 10);

            if ($renderer) {
                return (string) $renderer->render_content($this, $content);
            }

            return $content;
        } catch (\Throwable $e) {
            if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                debugging('QR code render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            return '';
        }
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
}

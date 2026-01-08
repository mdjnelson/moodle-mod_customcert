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

namespace customcertelement_digitalsignature;

use core\di;
use mod_customcert\export\contracts\i_template_appendix_manager;
use mod_customcert\export\contracts\subplugin_exportable;
use stored_file;

/**
 * Handles import and export of digital signature elements for custom certificates.
 *
 * @package    customcertelement_digitalsignature
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * @var i_template_appendix_manager Manages template appendix files such as images and signatures.
     */
    private i_template_appendix_manager $filemng;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->filemng = di::get(i_template_appendix_manager::class);
        parent::__construct();
    }

    /**
     * Combines signature metadata and file references into a JSON string for storage.
     *
     * Uses the appendix manager to resolve references for the image and signature files.
     *
     * @param array $data Input configuration data for the digital signature.
     * @return string|null JSON-encoded configuration for database storage.
     */
    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'signaturename' => $data["signaturename"],
            'signaturepassword' => $data["signaturepassword"],
            'signaturelocation' => $data["signaturelocation"],
            'signaturereason' => $data["signaturereason"],
            'signaturecontactinfo' => $data["signaturecontactinfo"],
            'width' => $data["width"],
            'height' => $data["height"],
        ];
        $arrtostore += $this->filemng->get_file_reference($data['imageref']);
        $arrtostore += $this->filemng->get_file_reference($data['signatureref']);

        return json_encode($arrtostore);
    }

    /**
     * Converts stored signature configuration back into an associative array for export.
     *
     * Resolves image and signature file identifiers via the appendix manager to rebind
     * the file references for re-import.
     *
     * @param int $elementid ID of the element.
     * @param string $customdata JSON-encoded settings and file references.
     * @return array Exportable structure containing metadata and file refs.
     */
    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);

        $arrtostore = [
            'signaturename' => $data->signaturename,
            'signaturepassword' => $data->signaturepassword,
            'signaturelocation' => $data->signaturelocation,
            'signaturereason' => $data->signaturereason,
            'signaturecontactinfo' => $data->signaturecontactinfo,
            'width' => $data->width,
            'height' => $data->height,
        ];

        if ($image = $this->get_file_from_customdata($customdata)) {
            $arrtostore['imageref'] = $this->filemng->get_identifier($image);
        }

        if ($signature = $this->get_file_from_customdata($customdata, 'signature')) {
            $arrtostore['signatureref'] = $this->filemng->get_identifier($signature);
        }

        return $arrtostore;
    }

    /**
     * Collects all files referenced by the digital signature element for export.
     *
     * This includes the visual signature image and the actual digital signature file.
     *
     * @param int $id ID of the element.
     * @param string $customdata JSON-encoded data with file references.
     * @return stored_file[] List of stored_file objects used by this element.
     */
    public function get_used_files(int $id, string $customdata): array {
        $files = [];

        if ($image = $this->get_file_from_customdata($customdata)) {
            $files[] = $image;
        }

        if ($certificate = $this->get_file_from_customdata($customdata, 'signature')) {
            $files[] = $certificate;
        }

        return $files;
    }
}

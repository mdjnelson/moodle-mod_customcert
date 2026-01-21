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

use core\di;
use Exception;
use mod_customcert\export\contracts\i_template_appendix_manager;
use mod_customcert\export\contracts\subplugin_exportable;
use stored_file;

/**
 * Handles import and export of background image elements for custom certificates.
 *
 * This exporter deals with serialization and deserialization of image references and properties.
 * It ensures referenced images are available, correctly mapped, and included in import/export
 * operations using the appendix manager service.
 *
 * @package    customcertelement_bgimage
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * @var i_template_appendix_manager Reference to the template appendix manager used for file lookup and identification.
     */
    private i_template_appendix_manager $filemng;
    /**
     * @var string Database table associated with custom certificate elements.
     */
    protected $dbtable = 'customcert_elements';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->filemng = di::get(i_template_appendix_manager::class);
        parent::__construct();
    }

    /**
     * Validates that the referenced image file exists during import.
     *
     * Logs a warning if the referenced image cannot be found and cancels import for the element.
     *
     * @param array $data The image element data to validate.
     * @return array|false The validated data, or false if the import should be canceled.
     */
    public function validate(array $data): array|false {
        $file = $this->filemng->find($data['imageref']);
        if (!$file) {
            $this->logger->warning("Image with ref " . $data['imageref'] . " not found");
            return false;
        }

        return $data;
    }

    /**
     * Converts validated data to a JSON string with detailed file metadata for storage.
     *
     * @param array $data The validated import data.
     * @return string|null The JSON-encoded storage string or null on failure.
     */
    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'width' => $data['width'],
            'height' => $data['height'],
            'alphachannel' => $data['alphachannel'],
        ];
        $arrtostore += $this->filemng->get_file_reference($data['imageref']);

        return json_encode($arrtostore);
    }

    /**
     * Extracts element data and adds a file identifier for export.
     *
     * Looks up the associated image and returns the export structure with an image reference.
     *
     * @param int $elementid ID of the element being exported.
     * @param string $customdata JSON string containing the element's settings.
     * @return array Associative array for export.
     */
    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);

        $file = $this->get_file_from_customdata($customdata);
        $fileid = $this->filemng->get_identifier($file);

        $arrtostore = [
            'width' => $data->width,
            'height' => $data->height,
            'alphachannel' => $data->alphachannel ?? null,
            'imageref' => $fileid,
        ];
        return $arrtostore;
    }

    /**
     * Returns the image file associated with this element for inclusion in export bundles.
     *
     * @param int $id The element ID.
     * @param string $customdata JSON-encoded data for the element.
     * @return stored_file[] An array containing the stored file, or empty if not found.
     */
    public function get_used_files(int $id, string $customdata): array {
        if (!$coursefile = $this->get_file_from_customdata($customdata)) {
            return [];
        }

        return [$coursefile];
    }
}

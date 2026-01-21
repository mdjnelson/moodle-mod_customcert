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

namespace customcertelement_border;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of border elements for custom certificates.
 *
 * @package    customcertelement_border
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates the width of the border element during import.
     *
     * Issues a warning and sets width to 0 if it is not a valid integer.
     *
     * @param array $data Element data containing width.
     * @return array|false Validated data array or false if validation fails.
     */
    public function validate(array $data): array|false {
        $width = $data['width'];

        if (intval($width) == 0) {
            $this->logger->warning("invalid border width");
            $width = 0;
        }

        return [
            'width' => (int) $width,
        ];
    }

    /**
     * Converts validated border data to a string format for storage.
     *
     * @param array $data Validated data array with 'width'.
     * @return string|null The width value as string or null on failure.
     */
    public function convert_for_import(array $data): ?string {
        return $data['width'];
    }

    /**
     * Prepares the stored border width for export as an associative array.
     *
     * @param int $elementid ID of the border element.
     * @param string $customdata Stored width as string.
     * @return array Associative array with the width value.
     */
    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['width'] = $customdata;
        return $data;
    }
}

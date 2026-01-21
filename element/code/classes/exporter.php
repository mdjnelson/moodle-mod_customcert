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

namespace customcertelement_code;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Empty custom data exporter.
 *
 * @package    customcertelement_code
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * No conversion required during import.
     *
     * @param array $data Input data (ignored).
     * @return null|string Always returns null.
     */
    public function convert_for_import(array $data): ?string {
        return null;
    }

    /**
     * No data is exported.
     *
     * @param int $elementid ID of the element.
     * @param string $customdata Ignored.
     * @return array Always returns an empty array.
     */
    public function export(int $elementid, string $customdata): array {
        return [];
    }
}

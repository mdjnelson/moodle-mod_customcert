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

namespace customcertelement_grade;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of grade elements for custom certificates.
 *
 * @package    customcertelement_grade
 * @autor      Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates the grade format against supported options.
     *
     * @param array $data Input data with 'gradeformat'.
     * @return array|false Validated data or false on error.
     */
    public function validate(array $data): array|false {
        $gradeformat = $data["gradeformat"];
        $gradeformats = element::get_grade_format_options();

        if (!array_key_exists($gradeformat, $gradeformats)) {
            $this->logger->warning("The grade format '{$gradeformat}' does not exist.");
            $gradeformat = array_keys($gradeformats)[0];
        }

        $this->logger->info("There is a grade reference, that will not be imported");
        return [
            "gradeformat" => $gradeformat,
        ];
    }

    /**
     * Converts validated grade data into a JSON string for database storage.
     *
     * Ignores any grade item binding and always sets 'gradeitem' to '0'.
     *
     * @param array $data Validated data array.
     * @return string|null JSON-encoded data or null.
     */
    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'gradeitem' => '0',
            'gradeformat' => $data["gradeformat"],
        ];
        return json_encode($arrtostore);
    }

    /**
     * Extracts and exports the grade format from stored data.
     *
     * @param int $elementid ID of the grade element.
     * @param string $customdata JSON-encoded grade settings.
     * @return array Associative array with 'gradeformat'.
     */
    public function export(int $elementid, string $customdata): array {
        $decodeddata = json_decode($customdata);

        $arrtostore = [];
        $arrtostore['gradeformat'] = $decodeddata->gradeformat;
        return $arrtostore;
    }
}

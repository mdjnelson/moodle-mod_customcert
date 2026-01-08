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

namespace customcertelement_expiry;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of expiry elements for custom certificates.
 *
 * This exporter ensures expiry configuration is valid, including the date type,
 * format, and calculation base (award or course completion). Defaults are used for
 * unknown or invalid input, with warnings logged accordingly.
 *
 * @package    customcertelement_expiry
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates expiry date settings including date item, format, and base start.
     *
     * Ensures all input values are from predefined valid sets. If any value is invalid,
     * a default is used and a warning is logged.
     *
     * @param array $data Element data to validate.
     * @return array|false Cleaned and valid data array or false on total failure.
     */
    public function validate(array $data): array|false {
        $dateitem = $data["dateitem"];
        $validdateitems = $this->get_dateitems();
        if (!in_array($dateitem, $validdateitems)) {
            $this->logger->warning("invalid expiry date item");
            $dateitem = $validdateitems[0];
        }

        $dateformat = $data["dateformat"];
        $validdateformats = array_keys(element::get_date_formats());
        if (!in_array($dateformat, $validdateformats)) {
            $this->logger->warning("invalid expiry date format");
            $dateformat = $validdateformats[0];
        }

        $startfrom = $data["startfrom"];
        $validstartfroms = ['award', 'coursecomplete'];
        if (!in_array($startfrom, $validstartfroms)) {
            $this->logger->warning("invalid expiry start date format");
            $startfrom = $validstartfroms[0];
        }

        return [
            'dateitem' => $dateitem,
            'dateformat' => $dateformat,
            'startfrom' => $startfrom,
        ];
    }

    /**
     * Returns the list of valid custom expiry date item constants.
     *
     * These are typically internally reserved identifiers specific to expiry logic.
     *
     * @return array List of accepted date item strings.
     */
    private function get_dateitems(): array {
        return [
            '-8',
            '-9',
            '-10',
            '-11',
            '-12',
        ];
    }

    /**
     * Converts the validated expiry configuration into a JSON string for storage.
     *
     * @param array $data Cleaned input data.
     * @return string|null JSON-encoded data or null if invalid.
     */
    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    /**
     * Reconstructs the expiry element data from stored JSON for export.
     *
     * @param int $elementid ID of the expiry element.
     * @param string $customdata Stored JSON-encoded data.
     * @return array Associative array with keys: dateitem, dateformat, startfrom.
     */
    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);

        $arrtostore = [
            'dateitem' => $data->dateitem,
            'dateformat' => $data->dateformat,
            'startfrom' => $data->startfrom,
        ];
        return $arrtostore;
    }
}

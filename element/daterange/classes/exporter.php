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

namespace customcertelement_daterange;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of date range elements for custom certificates.
 *
 * @package    customcertelement_daterange
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates date range configuration including type, intervals, and recurrence.
     *
     * Checks:
     * - The date item is a known type.
     * - The 'dateranges' key is an array.
     * - Each range has a non-empty string, valid timestamps, and recurrence limits.
     *
     * @param array $data The input data for the date range element.
     * @return array|false Validated array or false if any checks fail.
     */
    public function validate(array $data): array|false {
        $dateitem = $data['dateitem'];
        $validdateitems = $this->get_valid_dateitems();
        if (!in_array($dateitem, $validdateitems)) {
            $this->logger->warning("invalid dateitem '{$dateitem}'");
            return false;
        }

        $dateranges = $data['dateranges'];
        if (!is_array($dateranges)) {
            $this->logger->warning("Rangedelete should be an array");
            return false;
        }

        // Check that datestring is set dataranges what aren't need to be deleted.
        foreach ($dateranges as $daterange) {
            $startdate = (int) $daterange["startdate"];
            $enddate = (int) $daterange["enddate"];
            $datestring = $daterange["datestring"];
            $recurring = (bool) $daterange["recurring"];

            if (empty($datestring)) {
                $this->logger->warning("datestring should not be empty");
                return false;
            }

            if ($startdate >= $enddate) {
                $this->logger->warning("Start date should be smaller than end date");
                return false;
            }

            if (
                $recurring
                && ($enddate - $startdate) > element::MAX_RECURRING_PERIOD
            ) {
                $this->logger->warning("Range period should be shorter than a year if reoccuring");
                return false;
            }
        }

        return $data;
    }

    /**
     * Returns the list of valid date item constants allowed for range-based configuration.
     *
     * @return array List of valid date item constants.
     */
    private function get_valid_dateitems() {
        return [
            element::DATE_ISSUE,
            element::DATE_CURRENT_DATE,
            element::DATE_COMPLETION,
            element::DATE_COURSE_START,
            element::DATE_COURSE_END,
            element::DATE_COURSE_GRADE,
        ];
    }

    /**
     * Converts validated date range data into JSON for storage.
     *
     * @param array $data The input configuration.
     * @return string|null JSON-encoded string or null on failure.
     */
    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    /**
     * Reconstructs the export structure from stored custom data.
     *
     * @param int $elementid The ID of the element.
     * @param string $customdata JSON-encoded date range data.
     * @return array Decoded export structure.
     */
    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}

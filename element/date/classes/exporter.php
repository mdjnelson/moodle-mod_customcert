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

namespace customcertelement_date;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/constants.php');

use mod_customcert\element_helper;
use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of date elements for custom certificates.
 *
 * @package    customcertelement_date
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates the provided date item type and format.
     *
     * @param array $data The element data to validate.
     * @return array Validated and possibly corrected data.
     */
    public function validate(array $data): array|false {
        $dateitem = $data["dateitem"];
        $dateformat = $data["dateformat"];

        if (!$this->is_valid_dateitem($dateitem)) {
            $this->logger->warning("invalid dateitem $dateitem");
            $dateitem = null;
        }

        if (!$this->is_valid_dateformat($dateformat)) {
            $this->logger->warning("invalid dateformat $dateformat");
            $dateformat = null;
        }

        return [
            'dateitem' => $dateitem,
            'dateformat' => $dateformat,
        ];
    }

    /**
     * Converts validated date data to a JSON string for database storage.
     *
     * @param array $data Validated date configuration.
     * @return string|null JSON-encoded data.
     */
    public function convert_for_import(array $data): ?string {
        return json_encode($data);
    }

    /**
     * Checks whether the provided date item is a known constant.
     *
     * @param string $dateitem The type of date to validate.
     * @return bool True if valid, false otherwise.
     */
    private function is_valid_dateitem(string $dateitem): bool {
        $valid = [
            CUSTOMCERT_DATE_ISSUE,
            CUSTOMCERT_DATE_CURRENT_DATE,
            CUSTOMCERT_DATE_COMPLETION,
            CUSTOMCERT_DATE_ENROLMENT_START,
            CUSTOMCERT_DATE_ENROLMENT_END,
            CUSTOMCERT_DATE_COURSE_START,
            CUSTOMCERT_DATE_COURSE_END,
            CUSTOMCERT_DATE_COURSE_GRADE,
        ];
        // TODO check if item exists.

        return in_array($dateitem, $valid);
    }

    /**
     * Verifies that the specified date format exists in the system.
     *
     * @param string $dateformat The format identifier.
     * @return bool True if the format is known, false otherwise.
     */
    private function is_valid_dateformat(string $dateformat): bool {
        return array_key_exists($dateformat, element_helper::get_date_formats());
    }

    /**
     * Converts stored JSON data back into an array for export.
     *
     * @param int $elementid ID of the date element.
     * @param string $customdata JSON-encoded date configuration.
     * @return array Decoded exportable data.
     */
    public function export(int $elementid, string $customdata): array {
        return (array) json_decode($customdata);
    }
}

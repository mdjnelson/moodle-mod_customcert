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

declare(strict_types=1);

namespace customcertelement_expiry;

use mod_customcert\export\subplugin_text_exportable;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\format_error;
use mod_customcert\export\datatypes\int_field;

/**
 * Handles import and export of expiry elements for custom certificates.
 *
 * This exporter ensures expiry configuration is valid, including the date type,
 * format, and calculation base (award or course completion). Defaults are used for
 * unknown or invalid input, with warnings logged accordingly. An invalid custom interval
 * causes the element to be skipped.
 *
 * @package    customcertelement_expiry
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_text_exportable {
    /**
     * Defines the custom data fields
     *
     * @return field_interface[] plugin-specific custom data fields
     */
    protected function get_fields(): array {
        return parent::get_fields() + [
            'dateitem' => new enum_field($this->get_dateitems()),
            'dateformat' => new enum_field(array_keys(element::get_date_formats())),
            'startfrom' => new enum_field(['award', 'coursecomplete']),
            'intervalvalue' => new int_field(1, 36500),
            'intervalunit' => new enum_field([element::UNIT_DAYS, element::UNIT_MONTHS, element::UNIT_YEARS]),
        ];
    }

    /**
     * Exports expiry data, omitting the interval fields unless a custom interval is used.
     *
     * @param string $customdata Subplugin custom data.
     * @return array Exported data in array format.
     */
    public function export(string $customdata): array {
        $data = parent::export($customdata);
        if (($data['dateitem']['value'] ?? null) !== element::EXPIRY_CUSTOM) {
            unset($data['intervalvalue'], $data['intervalunit']);
        }

        return $data;
    }

    /**
     * Converts import data, tolerating exports that predate custom intervals.
     *
     * A custom interval must be complete and valid, otherwise the element is skipped rather than
     * given an interval the template did not specify.
     *
     * @param array $data The data to convert.
     * @return string|null Converted string.
     * @throws format_error If a custom interval is missing or invalid.
     */
    public function convert_for_import(array $data): ?string {
        $custom = (string)($data['dateitem']['value'] ?? '') === element::EXPIRY_CUSTOM;

        if ($custom) {
            $errors = element::validate_interval(
                $data['intervalvalue']['value'] ?? null,
                $data['intervalunit']['value'] ?? null
            );
            if ($errors) {
                throw new format_error(get_string('errorimportinterval', 'customcertelement_expiry', implode(' ', $errors)));
            }
        } else {
            $data['intervalvalue'] = ['value' => 1];
            $data['intervalunit'] = ['value' => element::UNIT_MONTHS];
        }
        $json = parent::convert_for_import($data);

        if (!$custom) {
            $customdata = json_decode((string)$json, true);
            unset($customdata['intervalvalue'], $customdata['intervalunit']);
            $json = json_encode($customdata);
        }

        return $json;
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
            element::EXPIRY_CUSTOM,
        ];
    }
}

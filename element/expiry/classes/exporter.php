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
use mod_customcert\export\datatypes\int_field;

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
class exporter extends subplugin_text_exportable {
    /** @var array Values used for the custom interval fields when they are not stored. */
    private const array DEFAULTS = [
        'expiryvalue' => 1,
        'expiryunit' => 'years',
    ];

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
            'expiryvalue' => new int_field(1, element::MAX_DURATION),
            'expiryunit' => new enum_field(element::UNITS),
        ];
    }

    /**
     * Converts import data, defaulting the custom interval fields missing from older exports.
     *
     * @param array $data The data to convert.
     * @return string|null Converted string.
     */
    public function convert_for_import(array $data): ?string {
        foreach (self::DEFAULTS as $key => $value) {
            $data[$key] ??= ['value' => $value];
        }

        return parent::convert_for_import($data);
    }

    /**
     * Retrieves field data, defaulting the custom interval fields not stored for the 1-5 year options.
     *
     * @param string $key Field key.
     * @param array $data Decoded custom data array.
     * @return mixed Field value.
     */
    public function get_relevant_data(string $key, array $data): mixed {
        return parent::get_relevant_data($key, $data) ?? (self::DEFAULTS[$key] ?? null);
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

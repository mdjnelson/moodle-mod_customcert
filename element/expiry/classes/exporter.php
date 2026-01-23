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

use mod_customcert\export\contracts\subplugin_text_exportable;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\i_field;

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
    /**
     * Defines the custom data fields
     *
     * @return i_field[] plugin-specific custom data fields
     */
    protected function get_fields(): array {
        return parent::get_fields() + [
            'dateitem' => new enum_field($this->get_dateitems()),
            'dateformat' => new enum_field(array_keys(element::get_date_formats())),
            'startfrom' => new enum_field(['award', 'coursecomplete']),
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
}

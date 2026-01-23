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

namespace mod_customcert\export\contracts;

use mod_customcert\certificate;
use mod_customcert\export\datatypes\int_field;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\string_field;

/**
 * Defines exportable fields for subplugins with texts.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subplugin_text_exportable extends subplugin_exportable {
    /**
     * Standard fields for subplugins with texts.
     *
     * @return array The exportable field definitions
     */
    protected function get_fields(): array {
        return [
            'font' => new enum_field(array_keys(certificate::get_fonts())),
            'fontsize' => new enum_field(array_keys(certificate::get_font_sizes())),
            'colour' => new string_field(true),
            'width' => new int_field(0),
        ];
    }
}

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

namespace customcertelement_coursename;

use mod_customcert\export\contracts\subplugin_text_exportable;
use mod_customcert\export\datatypes\enum_field;

/**
 * Handles import and export of course name elements for custom certificates.
 *
 * This exporter validates and serializes the display format for course names,
 * supporting short or full name formats. Invalid formats fall back to a default
 * with a warning.
 *
 * @package    customcertelement_coursename
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_text_exportable {
    protected function get_fields(): array {
        return parent::get_fields() + [
            'coursenamedisplay' => new enum_field([
                element::COURSE_SHORT_NAME,
                element::COURSE_FULL_NAME
            ]),
        ];
    }
}

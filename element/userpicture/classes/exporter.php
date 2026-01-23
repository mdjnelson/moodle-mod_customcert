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

namespace customcertelement_userpicture;

use mod_customcert\export\datatypes\float_field;
use mod_customcert\export\contracts\subplugin_exportable;
use mod_customcert\export\datatypes\i_field;

/**
 * Handles import and export of user picture elements for custom certificates.
 *
 * @package    customcertelement_userpicture
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Defines the custom data fields
     *
     * @return i_field[] plugin-specific custom data fields
     */
    protected function get_fields(): array {
        return [
            'width' => new float_field(0),
            'height' => new float_field(0),
        ];
    }
}

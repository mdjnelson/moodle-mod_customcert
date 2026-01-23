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

namespace customcertelement_bgimage;

use core\di;
use Exception;
use mod_customcert\classes\export\datatypes\file_field;
use mod_customcert\classes\export\datatypes\float_field;
use mod_customcert\export\contracts\i_template_appendix_manager;
use mod_customcert\export\contracts\subplugin_exportable;
use stored_file;

/**
 * Handles import and export of background image elements for custom certificates.
 *
 * This exporter deals with serialization and deserialization of image references and properties.
 * It ensures referenced images are available, correctly mapped, and included in import/export
 * operations using the appendix manager service.
 *
 * @package    customcertelement_bgimage
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    protected function get_fields(): array {
        return [
            'width' => new float_field(0),
            'height' => new float_field(0),
            'alphachannel' => new float_field(0, 1),
            '$' => new file_field('mod_customcert'),
        ];
    }
}

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

use mod_customcert\classes\export\datatypes\enum_field;
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
    protected function get_fields(): array {
        return [
            'dateitem' => new enum_field($this->get_valid_dateitems()),
            'dateformat' => new enum_field($this->get_valid_dateformats())
        ];
    }

    private function get_valid_dateitems(): array {
        return [
            CUSTOMCERT_DATE_ISSUE,
            CUSTOMCERT_DATE_CURRENT_DATE,
            CUSTOMCERT_DATE_COMPLETION,
            CUSTOMCERT_DATE_ENROLMENT_START,
            CUSTOMCERT_DATE_ENROLMENT_END,
            CUSTOMCERT_DATE_COURSE_START,
            CUSTOMCERT_DATE_COURSE_END,
            CUSTOMCERT_DATE_COURSE_GRADE,
        ];
    }

    private function get_valid_dateformats(): array {
        return array_keys(element_helper::get_date_formats());
    }
}

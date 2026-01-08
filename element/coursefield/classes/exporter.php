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

namespace customcertelement_coursefield;

use core_course\customfield\course_handler;
use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $customfieldname = $data['customfieldname'];

        if (in_array($customfieldname,  ['fullname', 'shortname', 'idnumber'])) {
            return $data;
        }

        // Get the course custom fields.
        $handler = course_handler::create();
        $customfields = $handler->get_fields();
        $arrcustomfields = array_map(fn ($field) => $field->get('id'), $customfields);

        if (in_array($customfieldname, $arrcustomfields)) {
            return $data;
        }

        // Course field not found, do not create element.
        $this->logger->warning("Course field $customfieldname not found");
        return false;
    }

    public function convert_for_import(array $data): ?string {
        return $data['customfieldname'];
    }

    public function export(int $elementid, string $customdata): array {
        $arrtosave = [];
        $arrtosave['customfieldname'] = $customdata;
        return $arrtosave;
    }
}

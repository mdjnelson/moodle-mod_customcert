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

namespace customcertelement_teachername;

use core\di;
use mod_customcert\export\contracts\subplugin_exportable;
use moodle_database;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        if (empty($data)) {
            return [];
        }

        $userid = $data['userid'];
        $db = di::get(moodle_database::class);
        $teacher = $db->get_record('user', ['id' => $userid]);

        if (!$teacher) {
            $this->logger->info("Teacher name: Teacher with $userid does not exist");
            return [];
        }

        $issame = fullname($teacher) == $data['fullname'];
        if (!$issame) {
            $this->logger->info("Teacher name: Teacher with $userid is not the same as in backup.");
            return [];
        }

        return $data;
    }

    public function convert_for_import(array $data): ?string {
        if (empty($data)) {
            return null;
        }

        return $data['userid'];
    }

    public function export(int $elementid, string $customdata): array {
        $db = di::get(moodle_database::class);
        $teacher = $db->get_record('user', ['id' => intval($customdata)]);

        if (!$teacher) {
            return [];
        }

        return [
            'userid' => $customdata,
            'fullname' => $teacher->fullname,
        ];
    }
}

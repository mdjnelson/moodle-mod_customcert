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

namespace mod_customcert\export;

use core\di;
use moodle_database;

class table_exporter {
    public function __construct(
        public string $tablename
    ) {
        $this->db = di::get(moodle_database::class);
    }

    public function export(int $id, array $fields): array {
        $data = (array) $this->db->get_record(
            $this->tablename,
            ['id' => $id],
            implode(', ', $fields)
        );

        return $data;
    }
}

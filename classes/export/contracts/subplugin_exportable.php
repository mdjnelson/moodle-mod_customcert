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

use core\di;

abstract class subplugin_exportable {
    protected i_backup_logger $logger;

    public function __construct() {
        $this->logger = di::get(i_backup_logger::class);
    }

    public function validate(array $data): array|false {
        return $data;
    }
    public abstract function convert_for_import(array $data): ?string;
    public abstract function export(int $elementid, string $customdata): array;
    public function get_used_files(int $id, string $customdata): array {
        return [];
    }
}

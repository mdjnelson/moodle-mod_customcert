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

use mod_customcert\export\contracts\subplugin_exportable;

class element_null_exporter extends subplugin_exportable {
    public function __construct(
        private string $pluginname
    ) {
    }

    public function convert_for_import(array $data): ?string {
        mtrace('Couldn\'t import element from plugin ' . $this->pluginname);
        return null;
    }

    public function export(int $elementid, ?string $customdata): array {
        die('Couldn\'t export element from plugin ' . $this->pluginname);
        mtrace('Couldn\'t export element from plugin ' . $this->pluginname);
        return [];
    }
}
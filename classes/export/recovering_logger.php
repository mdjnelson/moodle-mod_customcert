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

use core\notification;
use mod_customcert\export\contracts\i_backup_logger;

class recovering_logger implements i_backup_logger {
    private array $warnings = [];
    private array $infos = [];

    public function warning($message): void {
        if (CLI_SCRIPT) {
            mtrace($message);
            return;
        }

        $this->warnings[] = $message;
    }

    public function info($message): void {
        if (CLI_SCRIPT) {
            mtrace($message);
            return;
        }

        $this->infos[] = $message;
    }

    public function print_notification(): void {
        foreach ($this->warnings as $message) {
            notification::warning($message);
        }

        foreach ($this->infos as $info) {
            notification::info($info);
        }
    }
}

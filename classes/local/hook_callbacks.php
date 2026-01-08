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

namespace mod_customcert\local;

use core\hook\di_configuration;
use mod_customcert\export\backup_manager;
use mod_customcert\export\contracts\i_backup_logger;
use mod_customcert\export\contracts\i_backup_manager;
use mod_customcert\export\contracts\i_file_manager;
use mod_customcert\export\file_manager;
use mod_customcert\export\recovering_logger;

class hook_callbacks {
    public static function di_configuration(di_configuration $config) {
        $config->add_definition(
            id: i_file_manager::class,
            definition: function (): i_file_manager {
                return new file_manager();
            }
        );

        $config->add_definition(
            id: i_backup_manager::class,
            definition: function (
                i_file_manager $filemng
            ): i_backup_manager {
                return new backup_manager($filemng);
            }
        );

        $config->add_definition(
            id: i_backup_logger::class,
            definition: function (): i_backup_logger {
                return new recovering_logger();
            }
        );
    }
}

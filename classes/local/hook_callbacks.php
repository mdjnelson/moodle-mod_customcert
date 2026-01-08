<?php

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

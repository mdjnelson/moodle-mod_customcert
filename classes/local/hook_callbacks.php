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
use mod_customcert\export\template_file_manager;
use mod_customcert\export\contracts\i_template_import_logger;
use mod_customcert\export\contracts\i_template_file_manager;
use mod_customcert\export\contracts\i_template_appendix_manager;
use mod_customcert\export\template_appendix_manager;
use mod_customcert\export\template_logger;

/**
 * Registers dependency injection definitions for custom certificate export services.
 *
 * This class defines service bindings for appendix manager, file manager, and logger,
 * integrating them into Moodle's core DI container during plugin initialization.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Configures the dependency injection container with service definitions.
     *
     * Binds interfaces to their concrete implementations so that components
     * such as file managers and loggers can be automatically injected.
     *
     * @param di_configuration $config The DI configuration instance to register definitions in.
     */
    public static function di_configuration(di_configuration $config) {
        $config->add_definition(
            id: i_template_appendix_manager::class,
            definition: function (): i_template_appendix_manager {
                return new template_appendix_manager();
            }
        );

        $config->add_definition(
            id: i_template_file_manager::class,
            definition: function (
                i_template_appendix_manager $filemng
            ): i_template_file_manager {
                return new template_file_manager($filemng);
            }
        );

        $config->add_definition(
            id: i_template_import_logger::class,
            definition: function (): i_template_import_logger {
                return new template_logger();
            }
        );
    }
}

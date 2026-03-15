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

declare(strict_types=1);

namespace mod_customcert\local;

use core\hook\di_configuration;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_file_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager;
use mod_customcert\export\template_file_manager;
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
    public static function di_configuration(di_configuration $config): void {
        $config->add_definition(
            id: template_appendix_manager_interface::class,
            definition: function (): template_appendix_manager_interface {
                return new template_appendix_manager();
            }
        );

        $config->add_definition(
            id: template_file_manager_interface::class,
            definition: function (
                template_appendix_manager_interface $filemng
            ): template_file_manager_interface {
                return new template_file_manager($filemng);
            }
        );

        $config->add_definition(
            id: template_import_logger_interface::class,
            definition: function (): template_import_logger_interface {
                return new template_logger();
            }
        );
    }
}

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

/**
 * Minimal subplugin exportable fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\export\subplugin_exportable;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\datatypes\field_interface;

/**
 * A minimal concrete subplugin_exportable that accepts an injected field list.
 *
 * Used in export_subplugin_exportable_test to exercise the base class logic
 * without requiring a real plugin implementation.
 */
final class minimal_subplugin_exportable extends subplugin_exportable {
    /** @var field_interface[] Test fields for this exportable. */
    private array $testfields;

    /**
     * Constructor.
     *
     * @param string $pluginname Plugin name.
     * @param template_import_logger_interface $logger Logger.
     * @param template_appendix_manager_interface $filemng File manager.
     * @param field_interface[] $fields Fields to use.
     */
    public function __construct(
        string $pluginname,
        template_import_logger_interface $logger,
        template_appendix_manager_interface $filemng,
        array $fields,
    ) {
        parent::__construct($pluginname, $logger, $filemng);
        $this->testfields = $fields;
    }

    /**
     * Returns the injected test fields.
     *
     * @return field_interface[]
     */
    protected function get_fields(): array {
        return $this->testfields;
    }
}

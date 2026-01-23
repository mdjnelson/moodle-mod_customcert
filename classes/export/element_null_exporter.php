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
use mod_customcert\export\datatypes\i_field;

/**
 * Handles unsupported or unrecognized subplugin types during export and import.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_null_exporter extends subplugin_exportable {
    /** @var string The name of the unknown or unsupported plugin */
    private readonly string $pluginname;

    /**
     * Initializes the null exporter with the name of the unrecognized plugin.
     *
     * @param string $pluginname The name of the unknown or unsupported plugin.
     */
    public function __construct(
        string $pluginname
    ) {
        $this->pluginname = $pluginname;
        parent::__construct();
    }

    /**
     * Logs a warning that import for the given plugin type is not supported.
     *
     * @param array $data The data intended for import.
     * @return string|null Always returns null as the conversion is not possible.
     */
    public function convert_for_import(array $data): ?string {
        $this->logger->warning('Couldn\'t import element of type ' . $this->pluginname);
        return null;
    }

    /**
     * Logs a message to CLI that export for the given plugin type is not supported.
     *
     * @param string|null $customdata Custom data associated with the element.
     * @return array Always returns an empty array since export is not supported.
     */
    public function export(?string $customdata): array {
        if (CLI_SCRIPT) {
            mtrace('Couldn\'t export element from plugin ' . $this->pluginname);
        }
        return [];
    }

    /**
     * Non-existent subplugin dont have fields
     *
     * @return i_field[] empty array
     */
    protected function get_fields(): array {
        return [];
    }
}

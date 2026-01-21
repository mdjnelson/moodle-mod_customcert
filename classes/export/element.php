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

use core\clock;
use core\di;
use mod_customcert\export\contracts\import_exception;
use mod_customcert\export\contracts\subplugin_exportable;
use moodle_database;

/**
 * Manages the import, export, and file referencing of certificate elements.
 *
 * This class handles the lifecycle of individual certificate elements, including their
 * import from and export to data arrays, integration with plugin-specific exporters,
 * and lookup of associated files. It supports extensibility via subplugin-based export logic.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element {
    /**
     * @var clock Clock instance used to retrieve current timestamps.
     */
    private clock $clock;

    /**
     * @var table_exporter Table exporter responsible for retrieving element data from the database.
     */
    private table_exporter $exporter;

    /**
     * @var string Name of the database table containing certificate elements.
     */
    protected static string $dbtable = 'customcert_elements';

    /**
     * @var array List of database fields to be used in import/export operations.
     */
    protected array $fields = [
        'name',
        'element',
        'data',
        'posx',
        'posy',
        'refpoint',
        'alignment',
        'sequence',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    /**
     * Retrieves all element IDs associated with a specific certificate page.
     *
     * @param int $pageid The ID of the page to query.
     * @return array List of element IDs.
     */
    public static function get_elementids_from_page(int $pageid): array {
        $db = di::get(moodle_database::class);
        $elementids = $db->get_fieldset(static::$dbtable, 'id', ['pageid' => $pageid]);
        return $elementids;
    }

    /**
     * Imports an element into the certificate from a provided data array.
     *
     * Validates the input data, converts it using a plugin-specific exporter, and inserts it
     * into the database along with position and styling details.
     *
     * @param int $pageid The ID of the page to which the element will be added.
     * @param array $data The element data to import.
     * @throws import_exception If required fields like 'name' are missing.
     */
    public function import(int $pageid, array $data): void {
        if (($data['name'] ?? null) == null) {
            throw new import_exception('Certificate missing the attribute name');
        }

        $specificexporter = $this->get_plugin_specific_exporter($data['element']);
        $subplugindata = $specificexporter->validate($data['data']);
        if ($subplugindata === false) {
            return;
        }

        $db = di::get(moodle_database::class);
        $db->insert_record(static::$dbtable, [
            'pageid' => $pageid,
            'name' => $data['name'],
            'element' => $data['element'],
            'data' => $specificexporter->convert_for_import($subplugindata),
            'font' => $data['font'],
            'fontsize' => $data['fontsize'],
            'colour' => $data['colour'],
            'posx' => (int) $data['posx'],
            'posy' => (int) $data['posy'],
            'width' => (int) $data['width'],
            'refpoint' => (int) $data['refpoint'],
            'alignment' => $data['alignment'],
            'sequence' => (int) $data['sequence'],
            'timecreated' => $this->clock->time(),
            'timemodified' => $this->clock->time(),
        ]);
    }

    /**
     * Exports a certificate element into an array, including subplugin data.
     *
     * Retrieves the core element data and augments it using a plugin-specific exporter.
     *
     * @param int $elementid The ID of the element to export.
     * @return array The exported element data.
     */
    public function export(int $elementid): array {
        $data = $this->exporter->export($elementid, $this->fields);

        $specificexporter = $this->get_plugin_specific_exporter($data['element']);
        $data['data'] = $specificexporter->export($elementid, $data['data'] ?? "");
        return $data;
    }

    /**
     * Retrieves files used by a specific certificate element.
     *
     * Delegates the file collection to the corresponding subplugin exporter.
     *
     * @param int $elementid The ID of the element.
     * @return array List of file references.
     */
    public function get_files(int $elementid): array {
        $data = $this->exporter->export($elementid, ['element', 'data']);

        $specificexporter = $this->get_plugin_specific_exporter($data['element']);
        return $specificexporter->get_used_files($elementid, $data['data'] ?? "");
    }

    /**
     * Resolves and returns the subplugin exporter instance for a given plugin name.
     *
     * Returns a fallback null exporter if the specific exporter class does not exist
     * or does not implement the expected interface.
     *
     * @param string $pluginname The name of the subplugin.
     * @return subplugin_exportable The resolved exporter.
     */
    private function get_plugin_specific_exporter(string $pluginname): subplugin_exportable {
        $classname = '\\customcertelement_' . $pluginname . '\\exporter';

        if (!class_exists($classname)) {
            return new element_null_exporter($pluginname);
        }

        $exporter = new $classname();
        if (!$exporter instanceof subplugin_exportable) {
            return new element_null_exporter($pluginname);
        }

        return $exporter;
    }
}

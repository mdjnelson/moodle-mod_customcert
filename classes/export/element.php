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

class element {
    private clock $clock;
    private table_exporter $exporter;
    protected static string $dbtable = 'customcert_elements';
    protected array $fields = [
        'name',
        'element',
        'data',
        'font',
        'fontsize',
        'colour',
        'posx',
        'posy',
        'width',
        'refpoint',
        'alignment',
        'sequence',
    ];

    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    public static function get_elementids_from_page(int $pageid): array {
        $db = di::get(moodle_database::class);
        $elementids = $db->get_fieldset(static::$dbtable, 'id', ['pageid' => $pageid]);
        return $elementids;
    }

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

    public function export(int $elementid): array {
        $data = $this->exporter->export($elementid, $this->fields);

        $specificexporter = $this->get_plugin_specific_exporter($data['element']);
        $data['data'] = $specificexporter->export($elementid, $data['data'] ?? "");
        return $data;
    }

    public function get_files(int $elementid): array {
        $data = $this->exporter->export($elementid, ['element', 'data']);

        $specificexporter = $this->get_plugin_specific_exporter($data['element']);
        return $specificexporter->get_used_files($elementid, $data['data'] ?? "");
    }

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

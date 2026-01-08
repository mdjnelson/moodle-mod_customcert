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
use moodle_database;

class template {
    private clock $clock;
    private table_exporter $exporter;
    protected static string $dbtable = 'customcert_templates';
    protected array $fields = ['name'];

    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    public function import(int $contextid, array $templatedata): void {
        if (($templatedata['name'] ?? null) == null) {
            throw new import_exception('Certificate missing the attribute name');
        }

        $db = di::get(moodle_database::class);
        $db->transactions_forbidden();
        $transaction = $db->start_delegated_transaction();

        $tid = $db->insert_record(static::$dbtable, [
            'name' => $templatedata['name'],
            'contextid' => $contextid,
            'timecreated' => $this->clock->time(),
            'timemodified' => $this->clock->time(),
        ]);

        $page = new page();
        foreach ($templatedata['pages'] as $pagedata) {
            $page->import($tid, $pagedata);
        }
        $transaction->allow_commit();
    }

    public function export(int $templateid): array {
        $data = $this->exporter->export($templateid, $this->fields);

        $pageids = page::get_pageids_from_template($templateid);
        $page = new page();
        $data['pages'] = array_map(function ($pageid) use ($page) {
            return $page->export($pageid);
        }, $pageids);
        return $data;
    }
}

<?php

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

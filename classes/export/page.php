<?php

namespace mod_customcert\export;

use core\clock;
use core\di;
use moodle_database;

class page {
    private clock $clock;
    private table_exporter $exporter;
    protected static string $dbtable = 'customcert_pages';
    protected array $fields = [
        'width',
        'height',
        'leftmargin',
        'rightmargin',
        'sequence',
    ];

    public function __construct() {
        $this->exporter = new table_exporter(self::$dbtable);
        $this->clock = di::get(clock::class);
    }

    public static function get_pageids_from_template(int $templateid): array {
        $db = di::get(moodle_database::class);
        $pageids = $db->get_fieldset(static::$dbtable, 'id', ['templateid' => $templateid]);
        return $pageids;
    }

    public function import(int $templateid, array $pagedata): void {
        $db = di::get(moodle_database::class);
        $pageid = $db->insert_record(static::$dbtable, [
            'templateid' => $templateid,
            'width' => (int) $pagedata['width'],
            'height' => (int) $pagedata['height'],
            'leftmargin' => (int) $pagedata['leftmargin'],
            'rightmargin' => (int) $pagedata['rightmargin'],
            'sequence' => (int) $pagedata['sequence'],
            'timecreated' => $this->clock->time(),
            'timemodified' => $this->clock->time(),
        ]);

        $element = new element();
        foreach ($pagedata['elements'] as $elementdata) {
            $element->import($pageid, $elementdata);
        }
    }

    public function export(int $pageid): array {
        $data = $this->exporter->export($pageid, $this->fields);

        $elementids = element::get_elementids_from_page($pageid);
        $element = new element();
        $data['elements'] = array_map(function ($elementid) use ($element) {
            return $element->export($elementid);
        }, $elementids);
        return $data;
    }
}

<?php

namespace mod_customcert\export;

use core\di;
use moodle_database;

class table_exporter {
    public function __construct(
        public string $tablename
    ) {
        $this->db = di::get(moodle_database::class);
    }

    public function export(int $id, array $fields): array {
        $data = (array) $this->db->get_record(
            $this->tablename,
            ['id' => $id],
            implode(', ', $fields)
        );

        return $data;
    }
}

<?php

namespace mod_customcert\export\contracts;

use core\di;

abstract class subplugin_exportable {
    protected i_backup_logger $logger;

    public function __construct() {
        $this->logger = di::get(i_backup_logger::class);
    }

    public function validate(array $data): array|false {
        return $data;
    }
    public abstract function convert_for_import(array $data): ?string;
    public abstract function export(int $elementid, string $customdata): array;
    public function get_used_files(int $id, string $customdata): array {
        return [];
    }
}

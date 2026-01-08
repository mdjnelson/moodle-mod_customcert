<?php

namespace mod_customcert\export\contracts;

interface i_backup_manager {
    public function export(int $templateid): void;
    public function import(int $contextid, string $tempdir): void;
}

<?php

namespace mod_customcert\export\contracts;

use stored_file;

interface i_file_manager {
    public function export(int $templateid, string $storepath): void;
    public function import(int $contextid, string $importpath): void;
    public function find($identifier): stored_file|false;
    public function get_identifier(stored_file $file): string;
}

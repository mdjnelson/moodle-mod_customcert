<?php

namespace mod_customcert\classes\export\datatypes;

class unimported_field implements i_field {
    public function import(array $data) {
        throw new format_exception('can not be imported');
    }

    public function export($value): array {
        return [];
    }
}

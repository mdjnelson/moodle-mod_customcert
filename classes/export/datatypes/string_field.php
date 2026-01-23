<?php

namespace mod_customcert\export\datatypes;

class string_field implements i_field {
    public function __construct(
        private string $emptyallowed
    ) {

    }

    public function import(array $data) {
        if ($data['value'] == "" && !$this->emptyallowed) {
            throw new format_exception('is empty');
        }

        return $data['value'];
    }

    public function export($value): array {
        return [
            'value' => $value,
        ];
    }
}

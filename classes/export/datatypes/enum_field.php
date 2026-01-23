<?php

namespace mod_customcert\export\datatypes;

class enum_field implements i_field {
    public function __construct(
        private array $options
    ) {
    }

    public function import(array $data) {
        $option = $data['value'];

        if (!in_array($option, $this->options)) {
            throw new format_exception("$option is not an valid option");
        }

        return $option;
    }

    public function export($value): array {
        return [
            'value' => $value,
        ];
    }
}

<?php

namespace mod_customcert\classes\export\datatypes;

class enum_field implements i_field {
    public function __construct(
        private array $options
    ) {
    }

    public function import(array $data) {
        $option = $data['option'];

        if (!in_array($this->options, $option)) {
            throw new format_exception("$option is not an valid option");
        }

        return $option;
    }

    public function export($value): array {
        return [
            'option' => $value,
        ];
    }
}

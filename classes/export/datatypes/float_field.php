<?php

namespace mod_customcert\classes\export\datatypes;

class float_field implements i_field {
    public function __construct(
        private ?float $min = null,
        private ?float $max = null,
    ) {
    }

    public function import(array $data) {
        $value = $data['value'];

        if ($this->min != null && $value < $this->min) {
            throw new format_exception("Value should be less than $this->min");
        }

        if ($this->min != null && $value > $this->max) {
            throw new format_exception("Value should be higher than $this->max");
        }

        return $value;
    }

    public function export($value): array {
        return [
            'value' => $value,
        ];
    }
}

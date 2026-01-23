<?php

namespace mod_customcert\export\datatypes;

class int_field extends float_field {
    public function import(array $data) {
        $validated = parent::import($data);
        return (int) $validated;
    }
}

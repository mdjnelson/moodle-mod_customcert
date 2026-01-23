<?php

namespace mod_customcert\classes\export\datatypes;

interface i_field {
    /**
     * @param array $data
     * @return mixed
     * @throws format_exception Unexpected value, can be handled
     * @throws format_error Fatal format exception
     */
    public function import(array $data);
    public function export($value): array;
}

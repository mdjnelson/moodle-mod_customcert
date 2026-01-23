<?php

namespace mod_customcert\export\datatypes;

interface i_file_field {
    public function get_file(array $data);
}

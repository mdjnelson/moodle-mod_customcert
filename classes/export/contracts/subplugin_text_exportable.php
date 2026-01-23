<?php

namespace mod_customcert\export\contracts;

use mod_customcert\certificate;
use mod_customcert\export\datatypes\int_field;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\string_field;

class subplugin_text_exportable extends subplugin_exportable {
    protected function get_fields(): array {
        return [
            'font' => new enum_field(array_keys(certificate::get_fonts())),
            'fontsize' => new enum_field(array_keys(certificate::get_font_sizes())),
            'colour' => new string_field(true),
            'width' => new int_field(0),
        ];
    }
}

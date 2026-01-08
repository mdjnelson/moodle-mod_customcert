<?php

namespace mod_customcert\export;

use mod_customcert\export\contracts\subplugin_exportable;

class element_null_exporter extends subplugin_exportable {
    public function __construct(
        private string $pluginname
    ) {
    }

    public function convert_for_import(array $data): ?string {
        mtrace('Couldn\'t import element from plugin ' . $this->pluginname);
        return null;
    }

    public function export(int $elementid, ?string $customdata): array {
        die('Couldn\'t export element from plugin ' . $this->pluginname);
        mtrace('Couldn\'t export element from plugin ' . $this->pluginname);
        return [];
    }
}
<?php

namespace customcertelement_text;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function validate(array $data): array|false {
        $text = $data['text'] ?? "";

        if ($text == "") {
            $this->logger->info("Empty text element found.");
        }

        return [
            "text" => $text,
        ];
    }

    public function convert_for_import(array $data): ?string {
        return $data['text'];
    }

    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['text'] = $customdata;
        return $data;
    }
}

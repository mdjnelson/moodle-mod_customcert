<?php

namespace customcertelement_digitalsignature;

use mod_customcert\export\contracts\subplugin_exportable;

class exporter extends subplugin_exportable {
    public function convert_for_import(array $data): ?string {
        $arrtostore = [
            'signaturename' => $data["signaturename"],
            'signaturepassword' => $data["signaturepassword"],
            'signaturelocation' => $data["signaturelocation"],
            'signaturereason' => $data["signaturereason"],
            'signaturecontactinfo' => $data["signaturecontactinfo"],
            'width' => $data["width"],
            'height' => $data["height"],
        ];
        return json_encode($arrtostore);
    }

    public function export(int $elementid, string $customdata): array {
        $data = json_decode($customdata);
        $arrtostore = [
            'signaturename' => $data->signaturename,
            'signaturepassword' => $data->signaturepassword,
            'signaturelocation' => $data->signaturelocation,
            'signaturereason' => $data->signaturereason,
            'signaturecontactinfo' => $data->signaturecontactinfo,
            'width' => $data->width,
            'height' => $data->height,
        ];
        return $arrtostore;
    }
}

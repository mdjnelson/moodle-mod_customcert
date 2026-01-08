<?php

namespace mod_customcert\export;
use coding_exception;
use mod_customcert\export\contracts\i_backup_manager;
use mod_customcert\export\contracts\i_file_manager;
use moodle_exception;
use zip_packer;

class backup_manager implements i_backup_manager {
    public function __construct(
        private i_file_manager $filemng
    ) {
    }

    public function export(int $templateid): void {
        $jsondata = (new template())->export($templateid);

        $json = json_encode($jsondata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new coding_exception("Json error during export");
        }

        $tempdir = make_temp_directory('customcert_export/' . uniqid(more_entropy: true));
        file_put_contents($tempdir . '/template.json', $json);

        $this->filemng->export($templateid, $tempdir);

        $zipper = new zip_packer();
        $zipfile = "$tempdir/certificate-template-$templateid.zip";

        $files = [];
        foreach (glob("$tempdir/*") as $path) {
            $files[basename($path)] = $path;
        }

        $zipper->archive_to_pathname(
            $files,
            $zipfile
        );

        @ob_clean();
        send_temp_file($zipfile, "certificate-template-$templateid.zip");
    }

    public function import(int $contextid, string $tempdir): void {
        if (!$packer = get_file_packer()) {
            throw new coding_exception('ZIP packer not available');
        }

        $unpackdir = "$tempdir/unzipped";
        check_dir_exists($unpackdir);

        if (!$packer->extract_to_pathname("$tempdir/import.zip", $unpackdir)) {
            throw new moodle_exception('errorunzippingfile', 'error');
        }

        $jsonpath = $unpackdir . '/template.json';
        if (!file_exists($jsonpath)) {
            throw new \moodle_exception('filenotfound', 'error', '', 'template.json');
        }
        
        $this->filemng->import($contextid, $unpackdir);

        $json = file_get_contents($jsonpath);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \coding_exception('Invalid template.json (not valid JSON)');
        }
        (new template())->import($contextid, $data);
    }
}

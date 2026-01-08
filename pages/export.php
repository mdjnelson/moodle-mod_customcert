<?php

require_once(__DIR__ . '/../../../config.php');

use core\di;
use mod_customcert\export\contracts\i_backup_manager;

require_login();

$tid = required_param("tid", PARAM_INT);

$exporter = di::get(i_backup_manager::class);
$exporter->export($tid);

/*
$template = new template(0);
$json = json_encode($template->export($tid), JSON_PRETTY_PRINT);

@ob_clean();

$filename = "customcert-template-{$tid}.json";

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');*/

//echo $json;
exit;

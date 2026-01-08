<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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

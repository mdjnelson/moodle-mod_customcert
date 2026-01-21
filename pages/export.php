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

/**
 * Triggers the export of a custom certificate template as a downloadable ZIP file.
 *
 * This script is executed via HTTP, requiring a logged-in user and a template ID (`tid`)
 * as a parameter. It retrieves the template export service from Moodle's DI container
 * and initiates the export and download.
 *
 * @package    mod_customcert
 * @copyright  2025, oncampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core\di;
use mod_customcert\export\contracts\i_template_file_manager;

require_login();

$tid = required_param("tid", PARAM_INT);

$exporter = di::get(i_template_file_manager::class);
$zippath = $exporter->export($tid);

@ob_clean();
send_temp_file($zippath, basename($zippath));

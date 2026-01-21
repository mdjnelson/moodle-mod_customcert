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
 * Exports a custom cert template.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use mod_customcert\export\contracts\i_template_file_manager;

define('WORKING_DIR', getcwd());
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/pathlib.php');

$usage = "
 Exports a template to a give path

 Usage:
     # php mod/customcert/cli/export_template.php [--help|-h]
     # php mod/customcert/cli/export_template.php [--template=<templateid>|-t] [--output=<savepath>|-o]

 Options:
     -h --help                     Print this help.
     -t --template                 Template id.
     -o --output=<savepath>        Path to save the zip file.
 ";

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'output' => "",
        'template' => -1,
    ],
    [
        'h' => 'help',
        'o' => 'output',
        't' => 'template',
    ]
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    mtrace(get_string('cliunknowoption', 'core_admin', $unrecognised));
    exit(2);
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

$tid = $options['template'];
if ($tid < 1) {
    cli_writeln("Template ID must be set as a positive integer");
    exit(2);
}

$exporter = di::get(i_template_file_manager::class);
$zippath = $exporter->export($tid);
$filename = basename($zippath);
$exportpath = make_filepath_absolute($options['output'], $filename, WORKING_DIR);

$destdir = dirname($exportpath);
if (!is_dir($destdir)) {
    cli_error("Destination directory does not exist: {$destdir}");
}

if (!copy($zippath, $exportpath)) {
    cli_error("Failed to copy zip to: {$exportpath}");
}

cli_writeln("Wrote: {$exportpath}");

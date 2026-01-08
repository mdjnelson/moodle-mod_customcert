<?php /** @noinspection PhpCSValidationInspection */
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
 * Imports a custom cert template.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use core\session\manager;
use mod_customcert\export\contracts\i_template_file_manager;

$cwd = getcwd();

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/pathlib.php');

$usage = "
 Imports a template to the moodle

 Usage:
     # php mod/customcert/cli/export_template.php [--help|-h]
     # php mod/customcert/cli/export_template.php [--input=<file>|-i]

 Options:
     -h --help                     Print this help.
     -i --input=<file>             Path to save the zip file. (required)
 ";

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'input' => false,
    ],
    [
        'h' => 'help',
        'i' => 'input',
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

$input = $options['input'] ?? '';

if (empty($input)) {
    cli_error("Missing --input", 2);
}

$input = make_filepath_absolute($input, '', $cwd);

if (!is_readable($input) || !is_file($input)) {
    cli_error("Input file not found or not readable: {$input}", 2);
}

manager::set_user(get_admin());
$contextid = context_system::instance()->id;

$tempdir = make_temp_directory('customcert_import/' . uniqid(more_entropy: true));
$zippath = $tempdir . '/import.zip';

if (!copy($input, $zippath)) {
    cli_error("Failed to copy ZIP to temp location: {$zippath}", 1);
}

cli_writeln("Importing template...");
cli_writeln("  Context ID: {$contextid}");
cli_writeln("  ZIP:        {$input}");
cli_writeln("  Temp dir:   {$tempdir}");

try {
    $backupmng = di::get(i_template_file_manager::class);
    $backupmng->import($contextid, $tempdir);

    cli_writeln("Successfully imported template.");
} catch (Throwable $e) {
    cli_writeln("Import failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
} finally {
    if (is_dir($tempdir)) {
        $it = new RecursiveDirectoryIterator($tempdir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($tempdir);
    }
}

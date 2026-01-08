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
 * Contains functions for file paths.
 *
 * @package    mod_customcert
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Makes a filepath absolute by using the current working directory
 *
 * @param string $path
 * @param string $defaultfilename
 * @return string
 */
function make_filepath_absolute(string $path, string $defaultfilename, string $cwd): string {
    if ($path === '') {
        $path = $defaultfilename;
    } else if (is_dir($path)) {
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $defaultfilename;
    }

    if (is_absolute_path($path)) {
        return $path;
    }

    if ($cwd === false) {
        throw new coding_exception('Cannot determine current working directory');
    }

    return $cwd . DIRECTORY_SEPARATOR . $path;
}

/**
 * Checks if the path is absolute
 *
 * @param string $path
 * @return bool
 */
function is_absolute_path(string $path): bool {
    // Unix: /path.
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return true;
    }

    // Windows: C:\path or C:/path.
    if (preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
        return true;
    }

    // Windows UNC: \\server\share.
    if (str_starts_with($path, '\\\\')) {
        return true;
    }

    return false;
}

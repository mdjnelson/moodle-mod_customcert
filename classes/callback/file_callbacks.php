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
 * File-serving callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

/**
 * Handles file-serving callbacks for customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_callbacks {
    /**
     * Serves certificate issues and other files.
     *
     * @param \stdClass $course
     * @param \stdClass $cm
     * @param \context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @return bool|null false if file not found, does not return anything if found - just send the file
     */
    public static function pluginfile(
        \stdClass $course,
        \stdClass $cm,
        \context $context,
        string $filearea,
        array $args,
        bool $forcedownload
    ) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        // We are positioning the elements.
        if ($filearea === 'image') {
            if ($context->contextlevel == CONTEXT_MODULE) {
                require_login($course, false, $cm);
            } else if ($context->contextlevel == CONTEXT_SYSTEM && !has_capability('mod/customcert:manage', $context)) {
                return false;
            }

            $relativepath = implode('/', $args);
            $fullpath = '/' . $context->id . '/mod_customcert/image/' . $relativepath;

            $fs = get_file_storage();
            $file = $fs->get_file_by_hash(sha1($fullpath));
            if (!$file || $file->is_directory()) {
                return false;
            }

            send_stored_file($file, 0, 0, $forcedownload);
        }
    }
}

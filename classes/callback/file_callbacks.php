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

use mod_customcert\service\certificate_download_service;
use mod_customcert\service\issue_repository;

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
     * $course and $cm may be null when serving files from the system context
     * (file_pluginfile() obtains them via get_context_info_array()).
     *
     * @param \stdClass|null $course
     * @param \stdClass|null $cm
     * @param \context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @return bool|null false if file not found, does not return anything if found - just send the file
     */
    public static function pluginfile(
        ?\stdClass $course,
        ?\stdClass $cm,
        \context $context,
        string $filearea,
        array $args,
        bool $forcedownload
    ) {
        global $CFG, $USER;

        require_once($CFG->libdir . '/filelib.php');

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
        } else if ($filearea === certificate_download_service::SITE_DOWNLOAD_FILEAREA) {
            // The generated site-wide "download all certificates" archive, only ever served to the
            // user it was generated for.
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                return false;
            }

            require_login();

            if (!has_capability('mod/customcert:viewallcertificates', $context)) {
                return false;
            }

            $itemid = (int) array_shift($args);
            if ($itemid !== (int) $USER->id) {
                return false;
            }

            $filename = array_pop($args);
            $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

            $fs = get_file_storage();
            $file = $fs->get_file($context->id, 'mod_customcert', $filearea, $itemid, $filepath, $filename);
            if (!$file || $file->is_directory()) {
                return false;
            }

            send_stored_file($file, 0, 0, true);
        } else if ($filearea === certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA) {
            // The generated "download all issued certificates" archive for an activity, only ever
            // served to the user it was generated for.
            if ($context->contextlevel != CONTEXT_MODULE) {
                return false;
            }

            require_login($course, false, $cm);

            if (!has_capability('mod/customcert:viewreport', $context)) {
                return false;
            }

            $itemid = (int) array_shift($args);
            if ($itemid !== (int) $USER->id) {
                return false;
            }

            $filename = array_pop($args);
            $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

            $fs = get_file_storage();
            $file = $fs->get_file($context->id, 'mod_customcert', $filearea, $itemid, $filepath, $filename);
            if (!$file || $file->is_directory()) {
                return false;
            }

            if (!$cm || !self::is_instance_archive_authorised($cm, $file)) {
                return false;
            }

            send_stored_file($file, 0, 0, true);
        }
    }
    /**
     * Whether the current user may still download an activity's "download all issued certificates" archive.
     *
     * The archive's manifest records the group and the users it was generated for. Every one of those users
     * must still be visible to the current user under the report visibility rules, so access that was lost
     * since the archive was generated (a group change, a deleted issue, ...) is respected. If the manifest is
     * missing or invalid the archive is refused.
     *
     * @param \stdClass $cm The certificate course module.
     * @param \stored_file $archive The archive being requested.
     * @return bool
     */
    public static function is_instance_archive_authorised(\stdClass $cm, \stored_file $archive): bool {
        if ($archive->get_filename() === certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST) {
            return false;
        }

        $manifestfile = get_file_storage()->get_file(
            $archive->get_contextid(),
            $archive->get_component(),
            $archive->get_filearea(),
            $archive->get_itemid(),
            $archive->get_filepath(),
            certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST
        );
        $manifest = $manifestfile ? json_decode($manifestfile->get_content()) : null;
        if (
            !is_object($manifest)
            || !isset($manifest->groupid) || !is_int($manifest->groupid)
            || !isset($manifest->userids) || !is_array($manifest->userids) || !$manifest->userids
        ) {
            return false;
        }
        foreach ($manifest->userids as $userid) {
            if (!is_int($userid) || $userid < 1) {
                return false;
            }
        }

        $visible = (new issue_repository())->get_visible_user_ids((int) $cm->instance, $cm, $manifest->groupid);

        return !array_diff_key(array_flip($manifest->userids), array_flip($visible));
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
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
 * A scheduled task for cleaning up old site-wide certificate download archives.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\task;

use context_system;
use core\task\scheduled_task;
use mod_customcert\service\certificate_download_service;

/**
 * A scheduled task for cleaning up old site-wide certificate download archives.
 *
 * Archives older than certificate_download_service::SITE_DOWNLOAD_FILE_LIFETIME are removed
 * by this cleanup task, including the activity-level "download all issued certificates" archives.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_site_certificates_downloads_task extends scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanupsitecertificatesdownloads', 'customcert');
    }

    /**
     * Execute.
     *
     * Removes site certificate download archives older than
     * {@see certificate_download_service::SITE_DOWNLOAD_FILE_LIFETIME}.
     */
    public function execute(): void {
        global $DB;

        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            false,
            'itemid',
            false
        );

        $expirybefore = time() - certificate_download_service::SITE_DOWNLOAD_FILE_LIFETIME;
        foreach ($files as $file) {
            if ($file->get_timecreated() < $expirybefore) {
                $file->delete();
            }
        }

        $this->cleanup_instance_archives($expirybefore);
    }

    /**
     * Remove expired activity-level archives, which are stored against each activity's module context.
     *
     * The directory records the file API creates for each request are removed once they are empty, as
     * deleting a file does not remove them.
     *
     * @param int $expirybefore Archives created before this time are removed.
     */
    private function cleanup_instance_archives(int $expirybefore): void {
        global $DB;

        $fs = get_file_storage();
        $params = [
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
            'expirybefore' => $expirybefore,
        ];

        $files = $DB->get_recordset_select(
            'files',
            "component = :component AND filearea = :filearea AND filename <> '.' AND timecreated < :expirybefore",
            $params
        );
        try {
            foreach ($files as $record) {
                $fs->get_file_instance($record)->delete();
            }
        } finally {
            $files->close();
        }

        // Deepest directories first, so a parent is only considered once its children are gone.
        $directories = $DB->get_recordset_select(
            'files',
            "component = :component AND filearea = :filearea AND filename = '.' AND timecreated < :expirybefore",
            $params,
            'filepath DESC'
        );
        try {
            foreach ($directories as $directory) {
                $haschildren = $DB->record_exists_select(
                    'files',
                    "contextid = :contextid AND component = :component AND filearea = :filearea AND itemid = :itemid
                     AND id <> :id AND " . $DB->sql_like('filepath', ':path'),
                    [
                        'contextid' => $directory->contextid,
                        'component' => $directory->component,
                        'filearea' => $directory->filearea,
                        'itemid' => $directory->itemid,
                        'id' => $directory->id,
                        'path' => $DB->sql_like_escape($directory->filepath) . '%',
                    ]
                );
                if (!$haschildren) {
                    $fs->get_file_instance($directory)->delete();
                }
            }
        } finally {
            $directories->close();
        }
    }
}

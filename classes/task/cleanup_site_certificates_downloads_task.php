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
use mod_customcert\certificate;

/**
 * A scheduled task for cleaning up old site-wide certificate download archives.
 *
 * Archives older than certificate::SITE_DOWNLOAD_FILE_LIFETIME are removed
 * by this cleanup task.
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
     * {@see certificate::SITE_DOWNLOAD_FILE_LIFETIME}.
     */
    public function execute(): void {
        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'mod_customcert',
            certificate::SITE_DOWNLOAD_FILEAREA,
            false,
            'itemid',
            false
        );

        $expirybefore = time() - certificate::SITE_DOWNLOAD_FILE_LIFETIME;
        foreach ($files as $file) {
            if ($file->get_timecreated() < $expirybefore) {
                $file->delete();
            }
        }
    }
}

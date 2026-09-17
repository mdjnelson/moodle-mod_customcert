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
 * An adhoc task for generating the site-wide "download all certificates" zip archive.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\task;

use context_system;
use core\message\message;
use core\task\adhoc_task;
use core_user;
use mod_customcert\certificate;
use moodle_url;
use stored_file;

/**
 * An adhoc task for generating the site-wide "download all certificates" zip archive.
 *
 * The archive is generated in the background and stored using the file API against the
 * requesting user, who is notified via a Moodle message once it is ready (or if there is
 * nothing to download). Unexpected generation failures are allowed to propagate so Moodle's
 * adhoc task runner can retry them.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_site_certificates_download_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskgeneratesitecertificatesdownload', 'customcert');
    }

    /**
     * Execute.
     *
     * Unexpected exceptions are intentionally not caught here so the adhoc task runner can
     * log the failure and retry according to its fail-delay schedule.
     */
    public function execute(): void {
        // The adhoc task runner sets up $USER to match the userid set on this task, so the
        // capability check below is evaluated against the user who requested the download.
        $userid = $this->get_userid();
        if (!$userid) {
            return;
        }

        $user = core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $context = context_system::instance();
        if (!has_capability('mod/customcert:viewallcertificates', $context, $user)) {
            return;
        }

        $zip = $this->generate_site_certificates_zip();
        if ($zip === null) {
            $this->send_notification($user, 'none');
            return;
        }

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_customcert', certificate::SITE_DOWNLOAD_FILEAREA, $userid);
        $storedfile = $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => $zip['filename'],
        ], $zip['path']);

        $this->send_notification($user, 'ready', $storedfile);
    }

    /**
     * Generate the site-wide certificates zip archive.
     *
     * Exposed for tests so failure paths can be exercised without catching exceptions.
     *
     * @param callable|null $zipfactory
     * @param callable|null $requestdirfactory
     * @return array{path: string, filename: string}|null
     */
    protected function generate_site_certificates_zip(
        ?callable $zipfactory = null,
        ?callable $requestdirfactory = null
    ): ?array {
        return certificate::generate_all_for_site_zip($zipfactory, $requestdirfactory);
    }

    /**
     * Notify the requesting user of the outcome of the download generation.
     *
     * @param \stdClass $user
     * @param string $status One of 'ready' or 'none'.
     * @param stored_file|null $file The generated archive, when $status is 'ready'.
     * @return void
     */
    private function send_notification(\stdClass $user, string $status, ?stored_file $file = null): void {
        $message = new message();
        $message->component = 'mod_customcert';
        $message->name = 'sitecertificatesdownloadready';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->notification = 1;

        if ($status === 'ready' && $file !== null) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'mod_customcert',
                certificate::SITE_DOWNLOAD_FILEAREA,
                $file->get_itemid(),
                '/',
                $file->get_filename(),
                true
            );
            $message->subject = get_string('downloadallsitecertificatesreadysubject', 'customcert');
            $message->fullmessage = get_string('downloadallsitecertificatesreadybody', 'customcert', $url->out(false));
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('downloadallsitecertificates', 'customcert');
        } else {
            $message->subject = get_string('downloadallsitecertificatesnonefoundsubject', 'customcert');
            $message->fullmessage = get_string('downloadallsitecertificatesnonefoundbody', 'customcert');
        }

        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = format_text($message->fullmessage, FORMAT_PLAIN);
        $message->smallmessage = $message->subject;

        message_send($message);
    }
}

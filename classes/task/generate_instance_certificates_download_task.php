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
 * An adhoc task for generating the "download all issued certificates" zip archive of an activity.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\task;

use context_module;
use core\message\message;
use core\task\adhoc_task;
use core_user;
use mod_customcert\service\certificate_download_service;
use mod_customcert\service\issue_repository;
use mod_customcert\service\template_repository;
use mod_customcert\template;
use moodle_url;
use stored_file;

/**
 * An adhoc task for generating the "download all issued certificates" zip archive of an activity.
 *
 * The custom data holds the course module id, the group the requester was viewing and the ids of
 * the users whose certificates they requested. When the task runs the requester's access is
 * checked again, so the archive only contains the requested users who are still visible to them in
 * that group. The archive is stored using the file API against the requesting user, who is notified
 * via a Moodle message once it is ready. Unexpected failures propagate so the task runner can retry.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_instance_certificates_download_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskgenerateinstancecertificatesdownload', 'customcert');
    }

    /**
     * Create a task for a download request.
     *
     * The user ids are sorted and de-duplicated so equivalent requests are recognised as duplicates.
     *
     * @param int $userid The user requesting the download.
     * @param int $cmid The certificate course module id.
     * @param int $groupid The group the requester is viewing, 0 for all participants.
     * @param int[] $userids The ids of the users whose certificates are requested.
     * @return self
     */
    public static function create_request(int $userid, int $cmid, int $groupid, array $userids): self {
        $userids = array_values(array_unique(array_map('intval', $userids)));
        sort($userids);

        $task = new self();
        $task->set_userid($userid);
        $task->set_custom_data(['cmid' => $cmid, 'groupid' => $groupid, 'userids' => $userids]);

        return $task;
    }

    /**
     * Execute.
     *
     * The adhoc task runner sets up $USER to match the userid set on this task, and no interactive
     * session exists, so the group is taken from the custom data rather than the session.
     */
    public function execute(): void {
        global $CFG, $DB;

        $userid = $this->get_userid();
        $data = $this->get_custom_data();
        if (!$userid || empty($data->cmid)) {
            return;
        }

        $user = core_user::get_user($userid);
        $cm = get_coursemodule_from_id('customcert', (int) $data->cmid);
        if (!$user || $user->deleted || !$cm || !empty($cm->deletioninprogress)) {
            return;
        }

        $context = context_module::instance($cm->id);
        if (!has_capability('mod/customcert:viewreport', $context, $user)) {
            return;
        }

        require_once($CFG->libdir . '/filestorage/zip_archive.php');

        // Only include requested users whose issue the requester can currently see in the requested group.
        $groupid = (int) ($data->groupid ?? 0);
        $requested = array_flip(array_map('intval', (array) ($data->userids ?? [])));
        $users = array_filter(
            (new issue_repository())->get_issues((int) $cm->instance, $cm, 0, 0, 'u.id', $groupid),
            static fn(\stdClass $user): bool => isset($requested[(int) $user->id])
        );
        if (!$users) {
            $this->send_notification($user, 'none');
            return;
        }

        $customcert = $DB->get_record('customcert', ['id' => $cm->instance], 'id, templateid', MUST_EXIST);
        $template = template::from_record((new template_repository())->get_by_id_or_fail((int) $customcert->templateid));
        $zip = certificate_download_service::create()->generate_issues_zip($template, $users);

        // Each request is stored in its own directory so requests never replace each other. The manifest of
        // who the archive was generated for is stored first, so the archive is never downloadable without it.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/' . bin2hex(random_bytes(8)) . '/',
        ];
        $fs->create_file_from_string(
            $filerecord + ['filename' => certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST],
            json_encode(['groupid' => $groupid, 'userids' => array_map('intval', array_column($users, 'id'))])
        );
        $storedfile = $fs->create_file_from_pathname($filerecord + ['filename' => $zip['filename']], $zip['path']);

        $this->send_notification($user, 'ready', $storedfile);
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
        // Reuses the existing download notification provider so no new message provider is required.
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
                certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            );
            $message->subject = get_string('downloadallissuedcertificatesreadysubject', 'customcert');
            $message->fullmessage = get_string('downloadallissuedcertificatesreadybody', 'customcert', $url->out(false));
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('downloadallissuedcertificates', 'customcert');
        } else {
            $message->subject = get_string('downloadallissuedcertificatesnonefoundsubject', 'customcert');
            $message->fullmessage = get_string('downloadallissuedcertificatesnonefoundbody', 'customcert');
        }

        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = format_text($message->fullmessage, FORMAT_PLAIN);
        $message->smallmessage = $message->subject;

        message_send($message);
    }
}

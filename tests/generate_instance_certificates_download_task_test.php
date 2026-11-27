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

namespace mod_customcert;

use advanced_testcase;
use context_module;
use core\task\manager;
use mod_customcert\callback\file_callbacks;
use mod_customcert\privacy\provider;
use mod_customcert\service\certificate_download_service;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\issue_repository;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\task\cleanup_site_certificates_downloads_task;
use mod_customcert\task\generate_instance_certificates_download_task;
use stdClass;
use ZipArchive;

/**
 * Tests for the generate_instance_certificates_download_task and related activity download flow.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\task\generate_instance_certificates_download_task
 * @covers     \mod_customcert\task\cleanup_site_certificates_downloads_task
 * @covers     \mod_customcert\callback\file_callbacks::pluginfile
 * @covers     \mod_customcert\privacy\provider
 */
final class generate_instance_certificates_download_task_test extends advanced_testcase {
    /**
     * The task stores a zip of the requested users' certificates and notifies the requester.
     */
    public function test_execute_stores_archive_and_notifies_user(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$learners[0]->id])->execute();

        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame(['ada_lovelace/certificate.pdf'], $this->list_zip_entries($file));

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame((string) $teacher->id, (string) $messages[0]->useridto);
        $this->assertStringContainsString(
            get_string('downloadallissuedcertificatesreadysubject', 'customcert'),
            $messages[0]->subject
        );
        $this->assertStringContainsString($file->get_filename(), $messages[0]->fullmessage);
        $this->assertStringContainsString($file->get_filepath() . $file->get_filename(), $messages[0]->fullmessage);
        $sink->close();
    }

    /**
     * Each certificate in the archive is generated for the learner it is filed under.
     */
    public function test_archive_certificates_belong_to_the_right_learners(): void {
        global $DB;

        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $DB->set_field('customcert', 'usecustomfilename', 1, ['id' => $cm->instance]);
        $DB->set_field('customcert', 'customfilenamepattern', '{FIRST_NAME} {LAST_NAME}', ['id' => $cm->instance]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            0,
            [$learners[0]->id, $learners[1]->id]
        )->execute();

        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $entries = $this->read_zip_entries(reset($files));
        $this->assertCount(2, $entries);
        foreach (['ada_lovelace' => 'Ada Lovelace', 'grace_hopper' => 'Grace Hopper'] as $directory => $name) {
            // The PDF title is the learner's name, as UTF-16BE or plain text.
            $pdf = $entries[$directory . '/certificate.pdf'];
            $this->assertTrue(
                str_contains($pdf, mb_convert_encoding($name, 'UTF-16BE', 'UTF-8')) || str_contains($pdf, $name),
                "The certificate in $directory is not for $name"
            );
        }
    }

    /**
     * Only users holding an issue for the activity are included, whatever ids were queued.
     */
    public function test_execute_ignores_users_without_an_issue(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $other = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$other->id])->execute();

        $this->assertCount(0, $this->get_stored_files($cm, (int) $teacher->id));
        $this->assertStringContainsString(
            get_string('downloadallissuedcertificatesnonefoundsubject', 'customcert'),
            $sink->get_messages()[0]->subject
        );
        $sink->close();
    }

    /**
     * A user who no longer has the viewreport capability gets no archive and no message.
     */
    public function test_execute_does_nothing_without_capability(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        generate_instance_certificates_download_task::create_request($student->id, $cm->id, 0, [$learners[0]->id])->execute();

        $this->assertCount(0, $this->get_stored_files($cm, (int) $student->id));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * In separate groups mode the archive only contains learners from the requested group.
     */
    public function test_execute_respects_separate_groups(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        $this->setUser($teacher);

        // The teacher asks for both learners but can only see the one in their group.
        generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            (int) $groups[0]->id,
            [$learners[0]->id, $learners[1]->id]
        )->execute();

        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $this->assertSame(['ada_lovelace/certificate.pdf'], $this->list_zip_entries(reset($files)));
    }

    /**
     * A user cannot request another group's certificates by passing a group they do not belong to.
     */
    public function test_execute_refuses_a_group_the_requester_is_not_in(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        $this->setUser($teacher);

        generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            (int) $groups[1]->id,
            [$learners[1]->id]
        )->execute();

        $this->assertCount(0, $this->get_stored_files($cm, (int) $teacher->id));
        $this->assertStringContainsString(
            get_string('downloadallissuedcertificatesnonefoundsubject', 'customcert'),
            $sink->get_messages()[0]->subject
        );
        $sink->close();
    }

    /**
     * Losing access to the group between queueing and running produces no archive.
     */
    public function test_execute_after_group_access_is_lost(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        $this->setUser($teacher);

        $task = generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            (int) $groups[0]->id,
            [$learners[0]->id]
        );
        groups_remove_member($groups[0]->id, $teacher->id);
        $task->execute();

        $this->assertCount(0, $this->get_stored_files($cm, (int) $teacher->id));
        $this->assertStringContainsString(
            get_string('downloadallissuedcertificatesnonefoundsubject', 'customcert'),
            $sink->get_messages()[0]->subject
        );
        $sink->close();
    }

    /**
     * Losing access to the group after the archive was generated stops it being downloaded.
     */
    public function test_pluginfile_refuses_download_after_group_access_is_lost(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        $this->setUser($teacher);
        generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            (int) $groups[0]->id,
            [$learners[0]->id]
        )->execute();
        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $file = reset($files);

        // The access check passes while the teacher is in the group, and fails once they leave.
        $repository = new issue_repository();
        $this->assertNotEmpty($repository->get_conditional_issues_sql($cm, (int) $groups[0]->id)[1]);
        groups_remove_member($groups[0]->id, $teacher->id);
        $this->assertEmpty($repository->get_conditional_issues_sql($cm, (int) $groups[0]->id)[1]);

        $this->assertFalse($this->request_file($course, $cm, $file));
    }

    /**
     * The archive is only served to its owner, and only while they hold the capability.
     */
    public function test_pluginfile_refuses_other_users_and_lost_capability(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $otherteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$learners[0]->id])->execute();
        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $file = reset($files);

        $this->setUser($otherteacher);
        $this->assertFalse($this->request_file($course, $cm, $file));

        $this->setUser($teacher);
        $context = context_module::instance($cm->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('mod/customcert:viewreport', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $teacher->id, $context->id);
        $this->assertFalse($this->request_file($course, $cm, $file));
    }

    /**
     * The manifest records the group and exactly the users included in the archive.
     */
    public function test_manifest_records_group_and_included_users(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->create_group_teacher($course, $groups[0]);

        // Both learners are requested, but only the one in the teacher's group is included.
        $archive = $this->generate_archive($cm, $teacher, (int) $groups[0]->id, $learners);

        $manifest = json_decode($this->get_manifest($archive)->get_content(), true);
        $this->assertSame(['groupid' => (int) $groups[0]->id, 'userids' => [(int) $learners[0]->id]], $manifest);
    }

    /**
     * Access continues while every included learner is still visible to the requester.
     */
    public function test_archive_is_authorised_while_all_recipients_remain_visible(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        $archive = $this->generate_archive($cm, $teacher, (int) $groups[0]->id, [$learners[0]]);

        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));

        // Changes elsewhere do not matter: another learner joining or leaving other groups.
        groups_add_member($groups[0]->id, $learners[1]->id);
        groups_remove_member($groups[1]->id, $learners[1]->id);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));
    }

    /**
     * A learner moving to another group after generation stops the archive being served, even though the
     * requester keeps access to the original group.
     */
    public function test_archive_is_refused_when_a_recipient_moves_group(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->create_group_teacher($course, $groups[0]);
        groups_add_member($groups[0]->id, $learners[1]->id);
        $archive = $this->generate_archive($cm, $teacher, (int) $groups[0]->id, $learners);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));

        groups_remove_member($groups[0]->id, $learners[1]->id);

        $this->assertNotEmpty((new issue_repository())->get_visible_user_ids((int) $cm->instance, $cm, (int) $groups[0]->id));
        $this->assertFalse(file_callbacks::is_instance_archive_authorised($cm, $archive));
        $this->assertFalse($this->request_file($course, $cm, $archive));
    }

    /**
     * A recipient losing visibility for another reason stops the archive being served.
     *
     * Deleting the issue is also what privacy deletion does, so an archive generated while personal data
     * was being deleted cannot be downloaded.
     */
    public function test_archive_is_refused_when_a_recipient_is_no_longer_visible(): void {
        global $DB;

        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace', 'Grace Hopper', 'Alan Turing']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $archive = $this->generate_archive($cm, $teacher, 0, $learners);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));
        $DB->delete_records('customcert_issues', ['customcertid' => $cm->instance, 'userid' => $learners[0]->id]);
        $this->assertFalse(file_callbacks::is_instance_archive_authorised($cm, $archive));

        $archive = $this->generate_archive($cm, $teacher, 0, [$learners[1], $learners[2]]);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));
        delete_user($learners[2]);
        $this->assertFalse(file_callbacks::is_instance_archive_authorised($cm, $archive));
    }

    /**
     * Archives without a usable manifest, and the manifest itself, are never served.
     *
     * @dataProvider invalid_manifest_provider
     * @param string|null $contents The manifest contents, or null to delete the manifest.
     */
    public function test_archive_is_refused_without_a_valid_manifest(?string $contents): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $archive = $this->generate_archive($cm, $teacher, 0, $learners);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $archive));

        $manifest = $this->get_manifest($archive);
        $record = [
            'contextid' => $manifest->get_contextid(),
            'component' => $manifest->get_component(),
            'filearea' => $manifest->get_filearea(),
            'itemid' => $manifest->get_itemid(),
            'filepath' => $manifest->get_filepath(),
            'filename' => $manifest->get_filename(),
        ];
        $manifest->delete();
        if ($contents !== null) {
            get_file_storage()->create_file_from_string($record, $contents);
        }

        $this->assertFalse(file_callbacks::is_instance_archive_authorised($cm, $archive));
        $this->assertFalse($this->request_file($course, $cm, $archive));
    }

    /**
     * Missing and malformed manifests.
     *
     * @return array
     */
    public static function invalid_manifest_provider(): array {
        return [
            'missing' => [null],
            'empty' => [''],
            'not json' => ['not json'],
            'not an object' => ['[1,2]'],
            'no group' => ['{"userids":[1]}'],
            'group not an int' => ['{"groupid":"0","userids":[1]}'],
            'no users' => ['{"groupid":0}'],
            'empty users' => ['{"groupid":0,"userids":[]}'],
            'users not a list' => ['{"groupid":0,"userids":"1"}'],
            'user not an int' => ['{"groupid":0,"userids":["1"]}'],
            'user not positive' => ['{"groupid":0,"userids":[0]}'],
        ];
    }

    /**
     * The manifest cannot be downloaded in place of the archive.
     */
    public function test_manifest_cannot_be_downloaded(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $manifest = $this->get_manifest($this->generate_archive($cm, $teacher, 0, $learners));

        $this->assertFalse(file_callbacks::is_instance_archive_authorised($cm, $manifest));
        $this->assertFalse($this->request_file($course, $cm, $manifest));
    }

    /**
     * Separate requests from one user are stored independently and keep their links working.
     */
    public function test_distinct_requests_do_not_replace_each_other(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        [$course, $cm, $learners, $groups] = $this->create_activity(SEPARATEGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        foreach ([0, 1] as $index) {
            generate_instance_certificates_download_task::create_request(
                $teacher->id,
                $cm->id,
                (int) $groups[$index]->id,
                [$learners[$index]->id]
            )->execute();
        }

        $files = $this->get_stored_files($cm, (int) $teacher->id);
        $this->assertCount(2, $files);
        $this->assertCount(2, array_unique(array_map(static fn($f) => $f->get_pathnamehash(), $files)));
        $this->assertCount(2, $sink->get_messages());
        $sink->close();
    }

    /**
     * Equivalent requests are recognised as duplicates, whatever the order of the user ids.
     */
    public function test_equivalent_requests_are_deduplicated(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace', 'Grace Hopper']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        [$first, $second] = [$learners[0]->id, $learners[1]->id];

        manager::queue_adhoc_task(
            generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$second, $first, $first]),
            true
        );
        manager::queue_adhoc_task(
            generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$first, $second]),
            true
        );
        $this->assertCount(1, $DB->get_records('task_adhoc', ['userid' => $teacher->id]));

        // A request for a different group is distinct.
        manager::queue_adhoc_task(
            generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 5, [$first, $second]),
            true
        );
        $this->assertCount(2, $DB->get_records('task_adhoc', ['userid' => $teacher->id]));
    }

    /**
     * The cleanup task removes expired activity archives, with their manifest and directory records,
     * and keeps recent ones.
     */
    public function test_cleanup_removes_expired_instance_archives(): void {
        global $DB;

        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $old = $this->generate_archive($cm, $teacher, 0, $learners);
        $recent = $this->generate_archive($cm, $teacher, 0, $learners);
        $this->assertNotSame($old->get_filepath(), $recent->get_filepath());

        $expired = time() - certificate_download_service::SITE_DOWNLOAD_FILE_LIFETIME - 10;
        $DB->set_field('files', 'timecreated', $expired, [
            'contextid' => $old->get_contextid(),
            'filearea' => $old->get_filearea(),
            'filepath' => $old->get_filepath(),
        ]);
        $DB->set_field('files', 'timecreated', $expired, [
            'contextid' => $old->get_contextid(),
            'filearea' => $old->get_filearea(),
            'filepath' => '/',
            'filename' => '.',
        ]);

        (new cleanup_site_certificates_downloads_task())->execute();

        $remaining = $DB->get_records('files', [
            'contextid' => $old->get_contextid(),
            'filearea' => certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
        ]);
        $paths = array_map(static fn($record): string => $record->filepath . $record->filename, $remaining);
        $this->assertNotContains($old->get_filepath() . $old->get_filename(), $paths);
        $this->assertNotContains($old->get_filepath() . certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST, $paths);
        $this->assertNotContains($old->get_filepath() . '.', $paths);
        // The recent request is untouched, including its directory and the shared root directory.
        $this->assertContains($recent->get_filepath() . $recent->get_filename(), $paths);
        $this->assertContains($recent->get_filepath() . certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST, $paths);
        $this->assertContains($recent->get_filepath() . '.', $paths);
        $this->assertContains('/.', $paths);
        $this->assertTrue(file_callbacks::is_instance_archive_authorised($cm, $recent));

        // Once everything has expired nothing is left behind.
        $DB->set_field('files', 'timecreated', $expired, [
            'contextid' => $old->get_contextid(),
            'filearea' => certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
        ]);
        (new cleanup_site_certificates_downloads_task())->execute();
        $this->assertSame(0, $DB->count_records('files', [
            'contextid' => $old->get_contextid(),
            'filearea' => certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
        ]));
    }

    /**
     * Privacy deletion removes the activity's temporary archives, which contain copies of the certificates.
     */
    public function test_privacy_deletion_removes_instance_archives(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        [$course, $cm, $learners] = $this->create_activity(NOGROUPS, ['Ada Lovelace']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        generate_instance_certificates_download_task::create_request($teacher->id, $cm->id, 0, [$learners[0]->id])->execute();
        $this->assertCount(1, $this->get_stored_files($cm, (int) $teacher->id));

        provider::delete_data_for_all_users_in_context(context_module::instance($cm->id));

        $this->assertCount(0, $this->get_stored_files($cm, (int) $teacher->id));
    }

    /**
     * Ask the file callback for an archive, as the current user, using the archive's own item id.
     *
     * @param stdClass $course
     * @param stdClass $cm
     * @param \stored_file $file
     * @return bool|null
     */
    private function request_file(stdClass $course, stdClass $cm, \stored_file $file) {
        return file_callbacks::pluginfile(
            $course,
            $cm,
            context_module::instance($cm->id),
            certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
            array_values(array_filter([$file->get_itemid(), trim($file->get_filepath(), '/'), $file->get_filename()], 'strlen')),
            true
        );
    }

    /**
     * Get the stored archives for an activity and item id.
     *
     * @param stdClass $cm
     * @param int $itemid
     * @return \stored_file[]
     */
    private function get_stored_files(stdClass $cm, int $itemid): array {
        $files = get_file_storage()->get_area_files(
            context_module::instance($cm->id)->id,
            'mod_customcert',
            certificate_download_service::INSTANCE_DOWNLOAD_FILEAREA,
            $itemid,
            'itemid',
            false
        );

        return array_filter(
            $files,
            static fn($file): bool => $file->get_filename() !== certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST
        );
    }

    /**
     * Get the manifest stored next to an archive.
     *
     * @param \stored_file $archive
     * @return \stored_file
     */
    private function get_manifest(\stored_file $archive): \stored_file {
        return get_file_storage()->get_file(
            $archive->get_contextid(),
            $archive->get_component(),
            $archive->get_filearea(),
            $archive->get_itemid(),
            $archive->get_filepath(),
            certificate_download_service::INSTANCE_DOWNLOAD_MANIFEST
        );
    }

    /**
     * Generate an archive for a teacher and return it.
     *
     * @param stdClass $cm
     * @param stdClass $teacher
     * @param int $groupid
     * @param stdClass[] $learners The learners to request.
     * @return \stored_file
     */
    private function generate_archive(stdClass $cm, stdClass $teacher, int $groupid, array $learners): \stored_file {
        $this->setUser($teacher);
        $existing = array_keys($this->get_stored_files($cm, (int) $teacher->id));
        generate_instance_certificates_download_task::create_request(
            $teacher->id,
            $cm->id,
            $groupid,
            array_column($learners, 'id')
        )->execute();
        $created = array_diff_key($this->get_stored_files($cm, (int) $teacher->id), array_flip($existing));
        $this->assertCount(1, $created);

        return reset($created);
    }

    /**
     * Read the contents of the entries of a stored zip archive.
     *
     * @param \stored_file $file
     * @return array<string, string> The entry contents, keyed by path.
     */
    private function read_zip_entries(\stored_file $file): array {
        $path = make_request_directory() . '/check.zip';
        $file->copy_content_to($path);
        $zip = new ZipArchive();
        $zip->open($path);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $entries;
    }

    /**
     * List the entry paths of a stored zip archive.
     *
     * @param \stored_file $file
     * @return string[]
     */
    private function list_zip_entries(\stored_file $file): array {
        return array_keys($this->read_zip_entries($file));
    }

    /**
     * Create a non-editing teacher who belongs to a group and so cannot access all groups.
     *
     * @param stdClass $course
     * @param stdClass $group
     * @return stdClass
     */
    private function create_group_teacher(stdClass $course, stdClass $group): stdClass {
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        groups_add_member($group->id, $teacher->id);

        return $teacher;
    }

    /**
     * Create a course with a certificate activity and an issued certificate for each named learner.
     *
     * With a group mode, a group is created for each learner and the learner is placed in it.
     *
     * @param int $groupmode The activity group mode.
     * @param string[] $names Learner full names, as "First Last".
     * @return array{0: stdClass, 1: stdClass, 2: stdClass[], 3: stdClass[]} Course, course module, learners and groups.
     */
    private function create_activity(int $groupmode, array $names): array {
        global $DB;

        set_config('sendcoursewelcomemessage', 0, 'enrol_manual');
        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'groupmode' => $groupmode,
        ]);

        $template = template::from_record((new template_repository())->get_by_id_or_fail((int) $customcert->templateid));
        $pageid = template_service::create()->add_page($template);
        $DB->insert_record('customcert_elements', (object) ['pageid' => $pageid, 'name' => 'Image']);

        $learners = [];
        $groups = [];
        foreach ($names as $index => $name) {
            [$firstname, $lastname] = explode(' ', $name, 2);
            $learners[$index] = $this->getDataGenerator()->create_and_enrol($course, 'student', [
                'firstname' => $firstname,
                'lastname' => $lastname,
            ]);
            (new certificate_issue_service($DB, static fn(): int => time()))
                ->issue_certificate((int) $customcert->id, (int) $learners[$index]->id);
            if ($groupmode !== NOGROUPS) {
                $groups[$index] = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
                groups_add_member($groups[$index]->id, $learners[$index]->id);
            }
        }

        return [$course, get_coursemodule_from_instance('customcert', $customcert->id), $learners, $groups];
    }
}

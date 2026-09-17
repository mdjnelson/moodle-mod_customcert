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
use context_system;
use core\task\manager;
use file_archive;
use mod_customcert\callback\file_callbacks;
use mod_customcert\service\certificate_download_service;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\task\cleanup_site_certificates_downloads_task;
use mod_customcert\task\generate_site_certificates_download_task;
use mod_customcert\tests\fixtures\testable_generate_site_certificates_download_task;
use moodle_exception;
use stdClass;
use zip_archive;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_generate_site_certificates_download_task.php');

/**
 * Tests for the generate_site_certificates_download_task and related site download flow.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\task\generate_site_certificates_download_task
 * @covers     \mod_customcert\task\cleanup_site_certificates_downloads_task
 * @covers     \mod_customcert\callback\file_callbacks::pluginfile
 */
final class generate_site_certificates_download_task_test extends advanced_testcase {
    /**
     * The message provider must use valid Moodle message default constants.
     */
    public function test_message_provider_uses_valid_default_constants(): void {
        global $CFG;
        require_once($CFG->dirroot . '/message/lib.php');

        $this->assertTrue(defined('MESSAGE_PERMITTED'));
        $this->assertTrue(defined('MESSAGE_DEFAULT_ENABLED'));
        $this->assertFalse(defined('MESSAGE_DEFAULT_LOGGEDIN'));
        $this->assertFalse(defined('MESSAGE_DEFAULT_LOGGEDOFF'));

        // Load this plugin's messages.php directly so the test works regardless of install path.
        $messageproviders = [];
        include(__DIR__ . '/../db/messages.php');
        $this->assertArrayHasKey('sitecertificatesdownloadready', $messageproviders);
        $defaults = $messageproviders['sitecertificatesdownloadready']['defaults'];
        $expected = MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED;
        $this->assertSame($expected, $defaults['popup']);
        $this->assertSame($expected, $defaults['email']);
    }

    /**
     * Queueing the download task stores it for the requesting user and prevents duplicates.
     */
    public function test_queue_adhoc_task_for_user_and_prevents_duplicates(): void {
        global $DB;

        $this->resetAfterTest();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $task = new generate_site_certificates_download_task();
        $task->set_userid($manageruser->id);
        manager::queue_adhoc_task($task, true);

        $records = $this->get_queued_site_download_tasks((int) $manageruser->id);
        $this->assertCount(1, $records);
        $originaltaskid = (int) key($records);

        $duplicatetask = new generate_site_certificates_download_task();
        $duplicatetask->set_userid($manageruser->id);
        manager::queue_adhoc_task($duplicatetask, true);

        $records = $this->get_queued_site_download_tasks((int) $manageruser->id);
        $this->assertCount(1, $records);
        $this->assertArrayHasKey($originaltaskid, $records);
    }

    /**
     * Ensure the task stores a zip archive and notifies the user when certificates exist.
     */
    public function test_execute_stores_archive_and_notifies_user_when_certificates_exist(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        $manageruser = $this->create_manager_user();
        $context = context_system::instance();
        $this->create_issued_certificate('Ada', 'Lovelace', 'Site Template');

        $task = new generate_site_certificates_download_task();
        $task->set_userid($manageruser->id);
        $this->setUser($manageruser);
        $task->execute();

        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $manageruser->id,
            'itemid',
            false
        );
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('mod_customcert', $file->get_component());
        $this->assertSame(certificate_download_service::SITE_DOWNLOAD_FILEAREA, $file->get_filearea());
        $this->assertSame((int) $manageruser->id, (int) $file->get_itemid());
        $this->assertSame('/', $file->get_filepath());
        $this->assertMatchesRegularExpression('/_all_certificates\.zip$/', $file->get_filename());

        // Verify the stored zip contains the expected PDF path.
        $tmpdir = make_request_directory();
        $zippath = $tmpdir . '/check.zip';
        $file->copy_content_to($zippath);
        $zip = new zip_archive();
        $zip->open($zippath, file_archive::OPEN);
        $zipfiles = $zip->list_files();
        $zip->close();
        $this->assertCount(1, $zipfiles);
        $this->assertSame('ada_lovelace/site_template_certificate.pdf', $zipfiles[0]->pathname);

        $messages = $sink->get_messages();
        $this->assertNotEmpty($messages);
        $this->assertSame('sitecertificatesdownloadready', $messages[0]->eventtype);
        $this->assertSame((string) $manageruser->id, (string) $messages[0]->useridto);
        $this->assertStringContainsString(
            get_string('downloadallsitecertificatesreadysubject', 'customcert'),
            $messages[0]->subject
        );
        $sink->close();
    }

    /**
     * Ensure the task notifies the user without storing a file when there are no certificates.
     */
    public function test_execute_notifies_user_when_no_certificates_exist(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        $manageruser = $this->create_manager_user();
        $context = context_system::instance();

        $task = new generate_site_certificates_download_task();
        $task->set_userid($manageruser->id);
        $this->setUser($manageruser);
        $task->execute();

        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $manageruser->id,
            'itemid',
            false
        );
        $this->assertCount(0, $files);

        $messages = $sink->get_messages();
        $this->assertNotEmpty($messages);
        $this->assertSame((string) $manageruser->id, (string) $messages[0]->useridto);
        $this->assertStringContainsString(
            get_string('downloadallsitecertificatesnonefoundsubject', 'customcert'),
            $messages[0]->subject
        );
        $sink->close();
    }

    /**
     * Ensure the task does nothing if the requesting user no longer has the required capability.
     */
    public function test_execute_does_nothing_without_capability(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        $user = $this->getDataGenerator()->create_user();

        $task = new generate_site_certificates_download_task();
        $task->set_userid($user->id);
        $this->setUser($user);
        $task->execute();

        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $user->id,
            'itemid',
            false
        );
        $this->assertCount(0, $files);
        $this->assertEmpty($sink->get_messages());
        $sink->close();
    }

    /**
     * Generation failures must propagate so Moodle's adhoc task runner can retry them.
     *
     * No permanent-failure notification is sent for an attempt that will be retried.
     */
    public function test_execute_propagates_generation_failures_for_retry(): void {
        global $DB;

        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(false);

        $failingservice = new certificate_download_service(
            new template_repository(),
            pdf_generation_service::create(),
            $DB,
            static fn() => $failingzip
        );

        $task = new testable_generate_site_certificates_download_task();
        $task->set_download_service($failingservice);
        $task->set_userid($manageruser->id);

        try {
            $task->execute();
            $this->fail('Expected moodle_exception to propagate for retry');
        } catch (moodle_exception $exception) {
            $this->assertSame('errorcreatezip', $exception->errorcode);
        }

        $this->assertEmpty($sink->get_messages());
        $sink->close();
    }

    /**
     * A failure adding a certificate PDF to the zip must not expose a partial archive.
     */
    public function test_execute_propagates_zip_entry_failure(): void {
        global $DB;

        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(true);
        $failingzip->method('add_file_from_string')->willReturn(false);

        $failingservice = new certificate_download_service(
            new template_repository(),
            pdf_generation_service::create(),
            $DB,
            static fn() => $failingzip
        );

        $task = new testable_generate_site_certificates_download_task();
        $task->set_download_service($failingservice);
        $task->set_userid($manageruser->id);

        try {
            $task->execute();
            $this->fail('Expected moodle_exception to propagate for retry');
        } catch (moodle_exception $exception) {
            $this->assertSame('errorcreatezip', $exception->errorcode);
        }

        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $manageruser->id,
            'itemid',
            false
        );
        $this->assertCount(0, $files);
        $this->assertEmpty($sink->get_messages());
        $sink->close();
    }

    /**
     * A failure finalising the zip must not expose a partial or invalid archive.
     */
    public function test_execute_propagates_zip_finalisation_failure(): void {
        global $DB;

        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(true);
        $failingzip->method('add_file_from_string')->willReturn(true);
        $failingzip->method('close')->willReturn(false);

        $failingservice = new certificate_download_service(
            new template_repository(),
            pdf_generation_service::create(),
            $DB,
            static fn() => $failingzip
        );

        $task = new testable_generate_site_certificates_download_task();
        $task->set_download_service($failingservice);
        $task->set_userid($manageruser->id);

        try {
            $task->execute();
            $this->fail('Expected moodle_exception to propagate for retry');
        } catch (moodle_exception $exception) {
            $this->assertSame('errorcreatezip', $exception->errorcode);
        }

        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $manageruser->id,
            'itemid',
            false
        );
        $this->assertCount(0, $files);
        $this->assertEmpty($sink->get_messages());
        $sink->close();
    }

    /**
     * System-context pluginfile must accept null course/cm without TypeError, and enforce access.
     */
    public function test_pluginfile_site_download_with_null_course_cm_and_access_control(): void {
        $this->resetAfterTest();

        $owner = $this->create_manager_user();
        $other = $this->create_manager_user();
        $context = context_system::instance();

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $owner->id,
            'filepath' => '/',
            'filename' => 'all_certificates.zip',
        ], 'zip-content');

        // Null course/cm must not TypeError (system context from file_pluginfile).
        $this->setUser($owner);
        try {
            // Use a missing file so the callback returns false instead of sending output.
            $result = file_callbacks::pluginfile(
                null,
                null,
                $context,
                certificate_download_service::SITE_DOWNLOAD_FILEAREA,
                [$owner->id, 'missing.zip'],
                true
            );
            $this->assertFalse($result);
        } catch (\TypeError $e) {
            $this->fail('pluginfile must accept null course/cm for system context: ' . $e->getMessage());
        }

        // Other user (even with capability) cannot access owner's itemid.
        $this->setUser($other);
        $result = file_callbacks::pluginfile(
            null,
            null,
            $context,
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            [$owner->id, 'all_certificates.zip'],
            true
        );
        $this->assertFalse($result);

        // User without capability is denied even for their own itemid.
        $plain = $this->getDataGenerator()->create_user();
        $this->setUser($plain);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $plain->id,
            'filepath' => '/',
            'filename' => 'all_certificates.zip',
        ], 'zip-content');
        $result = file_callbacks::pluginfile(
            null,
            null,
            $context,
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            [$plain->id, 'all_certificates.zip'],
            true
        );
        $this->assertFalse($result);
    }

    /**
     * Cleanup removes archives older than the lifetime and preserves newer ones.
     */
    public function test_cleanup_removes_expired_archives_and_preserves_recent(): void {
        global $DB;

        $this->resetAfterTest();

        $context = context_system::instance();
        $fs = get_file_storage();
        $userid = 12345;

        $oldfile = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => 'old.zip',
        ], 'old');

        $newfile = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => 'new.zip',
        ], 'new');

        $DB->set_field('files', 'timecreated', time() - certificate_download_service::SITE_DOWNLOAD_FILE_LIFETIME - 10, [
            'id' => $oldfile->get_id(),
        ]);
        $DB->set_field('files', 'timecreated', time() - 60, [
            'id' => $newfile->get_id(),
        ]);

        (new cleanup_site_certificates_downloads_task())->execute();

        $files = $fs->get_area_files(
            $context->id,
            'mod_customcert',
            certificate_download_service::SITE_DOWNLOAD_FILEAREA,
            $userid,
            'filename',
            false
        );
        $filenames = array_map(static fn($f) => $f->get_filename(), $files);
        $this->assertNotContains('old.zip', $filenames);
        $this->assertContains('new.zip', $filenames);
    }

    /**
     * Fetch queued generate_site_certificates_download_task rows for a user.
     *
     * @param int $userid
     * @return array
     */
    private function get_queued_site_download_tasks(int $userid): array {
        global $DB;

        $records = $DB->get_records('task_adhoc', ['userid' => $userid]);
        return array_filter(
            $records,
            static fn($record): bool => str_contains($record->classname, 'generate_site_certificates_download_task')
        );
    }

    /**
     * Create a user with mod/customcert:viewallcertificates at system context.
     *
     * @return stdClass
     */
    private function create_manager_user(): stdClass {
        $manager = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('mod/customcert:viewallcertificates', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $manager->id, $context->id);
        return $manager;
    }

    /**
     * Create a course certificate activity with one issued certificate.
     *
     * @param string $firstname
     * @param string $lastname
     * @param string $templatename
     * @return void
     */
    private function create_issued_certificate(
        string $firstname = 'Ada',
        string $lastname = 'Lovelace',
        string $templatename = 'Site Template'
    ): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => $firstname,
            'lastname' => $lastname,
        ]);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::from_record((new template_repository())->get_by_id_or_fail((int) $customcert->templateid));
        $templateservice = template_service::create();
        $templateservice->update($template, (object) ['name' => $templatename]);
        $pageid = $templateservice->add_page($template);
        $element = new stdClass();
        $element->pageid = $pageid;
        $element->name = 'Image';
        $DB->insert_record('customcert_elements', $element);

        $issuer = new certificate_issue_service($DB, static fn(): int => time());
        $issuer->issue_certificate((int) $customcert->id, (int) $user->id);
    }
}

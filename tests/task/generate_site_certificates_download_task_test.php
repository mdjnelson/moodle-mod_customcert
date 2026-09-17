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
 * Unit tests for asynchronous site certificate downloads.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\task;

use advanced_testcase;
use context_system;
use core\task\manager;
use mod_customcert\certificate;
use moodle_exception;
use ReflectionMethod;
use stdClass;
use zip_archive;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_generate_site_certificates_download_task.php');

/**
 * Unit tests for asynchronous site certificate downloads.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\task\generate_site_certificates_download_task
 */
final class generate_site_certificates_download_task_test extends advanced_testcase {
    /**
     * Ensure an adhoc task can be queued against a user and is not duplicated while pending.
     *
     * @covers ::execute
     */
    public function test_queue_adhoc_task_sets_user_and_suppresses_duplicates(): void {
        $this->resetAfterTest();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $task = new generate_site_certificates_download_task();
        $task->set_userid($manageruser->id);
        manager::queue_adhoc_task($task, true);

        $queued = $this->get_queued_site_download_tasks($manageruser->id);
        $this->assertCount(1, $queued);
        $queuedtask = reset($queued);
        $this->assertSame((string) $manageruser->id, (string) $queuedtask->userid);
        $originaltaskid = (int) key($queued);

        // Queuing again while the first is still pending must be suppressed.
        $task2 = new generate_site_certificates_download_task();
        $task2->set_userid($manageruser->id);
        manager::queue_adhoc_task($task2, true);

        $queued = $this->get_queued_site_download_tasks($manageruser->id);
        $this->assertCount(1, $queued);
        $this->assertArrayHasKey($originaltaskid, $queued);
    }

    /**
     * Ensure the task generates a zip, stores it via the file API, and notifies the user.
     *
     * @covers ::execute
     */
    public function test_execute_stores_zip_and_notifies_user(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        $this->create_issued_certificate();
        $manageruser = $this->create_manager_user();
        $context = context_system::instance();

        $task = new generate_site_certificates_download_task();
        $task->set_userid($manageruser->id);
        $this->setUser($manageruser);
        $task->execute();

        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_customcert',
            certificate::SITE_DOWNLOAD_FILEAREA,
            $manageruser->id,
            'itemid',
            false
        );
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('mod_customcert', $file->get_component());
        $this->assertSame(certificate::SITE_DOWNLOAD_FILEAREA, $file->get_filearea());
        $this->assertSame((int) $manageruser->id, (int) $file->get_itemid());
        $this->assertSame('/', $file->get_filepath());
        $this->assertMatchesRegularExpression('/_all_certificates\.zip$/', $file->get_filename());

        // Verify the stored zip contains the expected PDF path.
        $tmpdir = make_request_directory();
        $zippath = $tmpdir . '/check.zip';
        $file->copy_content_to($zippath);
        $zip = new zip_archive();
        $zip->open($zippath, \file_archive::OPEN);
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
     *
     * @covers ::execute
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
            certificate::SITE_DOWNLOAD_FILEAREA,
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
     *
     * @covers ::execute
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
            certificate::SITE_DOWNLOAD_FILEAREA,
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
     * @covers ::execute
     */
    public function test_execute_propagates_generation_failures_for_retry(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(false);

        $task = new testable_generate_site_certificates_download_task();
        $task->zipfactory = static fn() => $failingzip;
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
     *
     * @covers ::execute
     */
    public function test_execute_propagates_zip_entry_failure(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(true);
        $failingzip->method('add_file_from_string')->willReturn(false);

        $task = new testable_generate_site_certificates_download_task();
        $task->zipfactory = static fn() => $failingzip;
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
            certificate::SITE_DOWNLOAD_FILEAREA,
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
     *
     * @covers ::execute
     */
    public function test_execute_propagates_zip_finalisation_failure(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();
        $this->create_issued_certificate();

        $manageruser = $this->create_manager_user();
        $this->setUser($manageruser);

        $failingzip = $this->createStub(zip_archive::class);
        $failingzip->method('open')->willReturn(true);
        $failingzip->method('add_file_from_string')->willReturn(true);
        $failingzip->method('close')->willReturn(false);

        $task = new testable_generate_site_certificates_download_task();
        $task->zipfactory = static fn() => $failingzip;
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
            certificate::SITE_DOWNLOAD_FILEAREA,
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
     *
     * @covers \customcert_pluginfile
     */
    public function test_pluginfile_site_download_with_null_course_cm_and_access_control(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $this->resetAfterTest();

        $owner = $this->create_manager_user();
        $other = $this->create_manager_user();
        $context = context_system::instance();

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $owner->id,
            'filepath' => '/',
            'filename' => 'all_certificates.zip',
        ], 'zip-content');

        // Null course/cm must not TypeError (system context from file_pluginfile).
        $this->setUser($owner);
        try {
            // Use a missing file so the callback returns false instead of sending output.
            $result = customcert_pluginfile(
                null,
                null,
                $context,
                certificate::SITE_DOWNLOAD_FILEAREA,
                [$owner->id, 'missing.zip'],
                true
            );
            $this->assertFalse($result);
        } catch (\TypeError $e) {
            $this->fail('pluginfile must accept null course/cm for system context: ' . $e->getMessage());
        }

        // Other user (even with capability) cannot access owner's itemid.
        $this->setUser($other);
        $result = customcert_pluginfile(
            null,
            null,
            $context,
            certificate::SITE_DOWNLOAD_FILEAREA,
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
            'filearea' => certificate::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $plain->id,
            'filepath' => '/',
            'filename' => 'all_certificates.zip',
        ], 'zip-content');
        $result = customcert_pluginfile(
            null,
            null,
            $context,
            certificate::SITE_DOWNLOAD_FILEAREA,
            [$plain->id, 'all_certificates.zip'],
            true
        );
        $this->assertFalse($result);
    }

    /**
     * Cleanup removes archives older than the lifetime and preserves newer ones.
     *
     * @covers \mod_customcert\task\cleanup_site_certificates_downloads_task::execute
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
            'filearea' => certificate::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => 'old.zip',
        ], 'old');

        $newfile = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_customcert',
            'filearea' => certificate::SITE_DOWNLOAD_FILEAREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => 'new.zip',
        ], 'new');

        $DB->set_field('files', 'timecreated', time() - certificate::SITE_DOWNLOAD_FILE_LIFETIME - 10, [
            'id' => $oldfile->get_id(),
        ]);
        $DB->set_field('files', 'timecreated', time() - 60, [
            'id' => $newfile->get_id(),
        ]);

        (new cleanup_site_certificates_downloads_task())->execute();

        $files = $fs->get_area_files(
            $context->id,
            'mod_customcert',
            certificate::SITE_DOWNLOAD_FILEAREA,
            $userid,
            'filename',
            false
        );
        $filenames = array_map(static function ($f) {
            return $f->get_filename();
        }, $files);
        $this->assertNotContains('old.zip', $filenames);
        $this->assertContains('new.zip', $filenames);
    }

    /**
     * Regression: instance-level bulk download must remain independent of site-wide async generation.
     *
     * @covers \mod_customcert\certificate::download_all_issues_for_instance
     */
    public function test_instance_level_download_remains_synchronous_and_independent(): void {
        $ref = new ReflectionMethod(certificate::class, 'download_all_issues_for_instance');
        $filename = $ref->getFileName();
        $start = $ref->getStartLine();
        $end = $ref->getEndLine();
        $source = implode('', array_slice(file($filename), $start - 1, $end - $start + 1));

        $this->assertStringNotContainsString('generate_all_for_site_zip', $source);
        $this->assertStringContainsString('send_file', $source);
        $this->assertStringContainsString('make_request_directory', $source);
        $this->assertStringContainsString('exit()', $source);
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
            static function ($record): bool {
                return strpos($record->classname, 'generate_site_certificates_download_task') !== false;
            }
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
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'name' => $templatename,
        ]);

        // The module generator already adds a page; add a simple element so PDF generation has content.
        $page = $DB->get_record('customcert_pages', ['templateid' => $customcert->templateid], '*', MUST_EXIST);
        $element = new stdClass();
        $element->pageid = $page->id;
        $element->name = 'Image';
        $DB->insert_record('customcert_elements', $element);

        certificate::issue_certificate((int) $customcert->id, (int) $user->id);
    }
}

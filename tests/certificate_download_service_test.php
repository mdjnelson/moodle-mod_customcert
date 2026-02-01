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
use mod_customcert\service\certificate_download_service;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\service\template_service;

/**
 * Tests for the certificate_download_service.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\certificate_download_service
 */
final class certificate_download_service_test extends advanced_testcase {
    /**
     * Ensure instance download builds a ZIP archive of certificate PDFs.
     * @covers ::download_all_issues_for_instance
     */
    public function test_download_all_issues_for_instance_creates_zip(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'firstnamephonetic' => 'Ada',
            'lastnamephonetic' => 'Lovelace',
            'middlename' => 'A',
            'alternatename' => 'Analyst',
        ]);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);
        $templateservice = new template_service();
        $pdfservice = new pdf_generation_service();
        $pageid = $templateservice->add_page($template);
        $this->assertDebuggingNotCalled();
        $element = new \stdClass();
        $element->pageid = $pageid;
        $element->name = 'Image';
        $DB->insert_record('customcert_elements', $element);

        $issues = [
            (object) [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'firstnamephonetic' => $user->firstnamephonetic,
                'lastnamephonetic' => $user->lastnamephonetic,
                'middlename' => $user->middlename,
                'alternatename' => $user->alternatename,
            ],
        ];

        $sent = [];
        $service = new certificate_download_service(
            $pdfservice,
            null,
            null,
            static function (string $path, string $name) use (&$sent): void {
                $sent = ['path' => $path, 'name' => $name];
            }
        );

        $service->download_all_issues_for_instance($template, $issues);

        $this->assertNotEmpty($sent);
        $this->assertFileExists($sent['path']);

        $zip = new \zip_archive();
        $zip->open($sent['path'], \file_archive::OPEN);
        $files = $zip->list_files();
        $zip->close();

        $this->assertCount(1, $files);
        $this->assertSame('ada_lovelace/certificate.pdf', $files[0]->pathname);
    }

    /**
     * Ensure site-wide download builds a ZIP archive of certificate PDFs.
     * @covers ::download_all_for_site
     */
    public function test_download_all_for_site_creates_zip(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
            'firstnamephonetic' => 'Grace',
            'lastnamephonetic' => 'Hopper',
            'middlename' => 'B',
            'alternatename' => 'Coder',
        ]);
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);

        $template = template::load((int)$customcert->templateid);
        $templateservice = new template_service();
        $pdfservice = new pdf_generation_service();
        $templateservice->update($template, (object) ['name' => 'Site Template']);
        $pageid = $templateservice->add_page($template);
        $this->assertDebuggingNotCalled();
        $element = new \stdClass();
        $element->pageid = $pageid;
        $element->name = 'Image';
        $DB->insert_record('customcert_elements', $element);

        $issuer = new certificate_issue_service(null, static fn(): int => 1_700_000_000);
        $issuer->issue_certificate((int)$customcert->id, (int)$user->id);

        $sent = [];
        $service = new certificate_download_service(
            $pdfservice,
            null,
            null,
            static function (string $path, string $name) use (&$sent): void {
                $sent = ['path' => $path, 'name' => $name];
            }
        );

        $service->download_all_for_site();

        $this->assertNotEmpty($sent);
        $this->assertFileExists($sent['path']);

        $zip = new \zip_archive();
        $zip->open($sent['path'], \file_archive::OPEN);
        $files = $zip->list_files();
        $zip->close();

        $this->assertCount(1, $files);
        $this->assertSame('grace_hopper/site_template_certificate.pdf', $files[0]->pathname);
    }
}

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
use mod_customcert\event\issue_created;
use mod_customcert\service\certificate_issue_service;

/**
 * Tests for the certificate_issue_service.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_customcert\service\certificate_issue_service
 */
final class certificate_issue_service_test extends advanced_testcase {
    /**
     * Issues an entry and confirms the issue_created event fires with correct data.
     * @covers ::issue_certificate
     */
    public function test_issue_certificate_creates_issue_and_triggers_event(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        global $DB;
        $service = new certificate_issue_service($DB, static fn(): int => 1_609_459_200);

        $sink = $this->redirectEvents();
        $issueid = $service->issue_certificate((int)$customcert->id, (int)$user->id);
        $events = $sink->get_events();
        $sink->close();

        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $this->assertSame((int)$customcert->id, (int)$issue->customcertid);
        $this->assertSame((int)$user->id, (int)$issue->userid);
        $this->assertSame(1_609_459_200, (int)$issue->timecreated);
        $this->assertSame(0, (int)$issue->emailed);
        $this->assertNotEmpty($issue->code);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(issue_created::class, $events[0]);
        $this->assertSame($issueid, $events[0]->objectid);
        $this->assertSame((int)$user->id, (int)$events[0]->relateduserid);
        $this->assertSame(context_module::instance($customcert->cmid)->id, $events[0]->get_context()->id);
    }

    /**
     * Generates a code using digit-only hyphenated strategy.
     * @covers ::generate_code
     */
    public function test_generate_code_digits_with_hyphens(): void {
        $this->resetAfterTest();

        set_config('codegenerationmethod', '1', 'customcert');

        $service = certificate_issue_service::create();
        $code = $service->generate_code();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $code);
    }

    /**
     * Generates a default alphanumeric code when config uses default strategy.
     * @covers ::generate_code
     */
    public function test_generate_code_upper_lower_digits_default(): void {
        $this->resetAfterTest();

        set_config('codegenerationmethod', '0', 'customcert');

        $service = certificate_issue_service::create();
        $code = $service->generate_code();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $code);
    }

    /**
     * generate_code() throws a moodle_exception after 10 failed attempts when every candidate collides.
     * @covers ::generate_code
     */
    public function test_generate_code_throws_after_exhausting_attempts(): void {
        $this->resetAfterTest();

        // Mock DB that always reports the code as already existing.
        $mockdb = $this->createMock(\moodle_database::class);
        $mockdb->method('record_exists')->willReturn(true);

        $service = new certificate_issue_service($mockdb);

        $this->expectException(\moodle_exception::class);
        $service->generate_code();
    }
}

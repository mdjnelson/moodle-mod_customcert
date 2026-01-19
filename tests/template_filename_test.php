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
 * Unit tests for template filename computation helper.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use context_system;
use mod_customcert\service\template_service;

/**
 * Unit test class for testing filename generation in templates.
 *
 * This class extends the advanced_testcase and focuses on verifying the logic
 * applied to determine the filename for users when generating files based on a template.
 */
final class template_filename_test extends advanced_testcase {
    /**
     * Test default name simply uses template name.
     *
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_default_filename_uses_template_name(): void {
        $this->resetAfterTest();

        $template = template::create('My Fancy Template.', context_system::instance()->id);
        $service = new template_service();

        $user = (object) ['id' => 123, 'firstname' => 'Ada', 'lastname' => 'Lovelace'];
        $customcert = (object) [
            'id' => 77,
            'course' => 1,
            'usecustomfilename' => 0,
            'customfilenamepattern' => '',
        ];

        $filename = $service->compute_filename_for_user($template, $user, $customcert);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertSame(clean_filename('My Fancy Template.pdf'), $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test placeholders are applied when a custom pattern is added.
     *
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_custom_pattern_placeholders_are_applied(): void {
        $this->resetAfterTest();

        $template = template::create('Ignored When Custom', context_system::instance()->id);
        $service = new template_service();

        $course = $this->getDataGenerator()->create_course();

        $user = (object) ['id' => 456, 'firstname' => 'Grace', 'lastname' => 'Hopper'];
        $customcert = (object) [
            'id' => 88,
            'course' => $course->id,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}_{LAST_NAME}_{ISSUE_DATE}',
        ];

        $filename = $service->compute_filename_for_user($template, $user, $customcert);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringContainsString('Grace_Hopper_', $filename);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Custom patterns that already include ".pdf" should not produce double extensions.
     *
     * @covers \mod_customcert\service\template_service::compute_filename_for_user
     */
    public function test_custom_pattern_with_pdf_suffix_is_not_duplicated(): void {
        $this->resetAfterTest();

        $template = template::create('PDF Name', context_system::instance()->id);
        $service = new template_service();

        $user = (object) ['id' => 456, 'firstname' => 'Grace', 'lastname' => 'Hopper'];
        $customcert = (object) [
            'id' => 99,
            'course' => 0,
            'usecustomfilename' => 1,
            'customfilenamepattern' => '{FIRST_NAME}.pdf',
        ];

        $filename = $service->compute_filename_for_user($template, $user, $customcert);
        $this->assertSame('Grace.pdf', $filename);
        $this->assertDebuggingNotCalled();
    }
}

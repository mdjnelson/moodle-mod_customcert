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
 * Unit tests for mod_customcert template.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use mod_customcert\tests\fixtures\throwing_template;

/**
 * Unit tests for mod_customcert template.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_test extends \advanced_testcase {
    /**
     * Test set up.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Test that the runtime language is restored even if an exception occurs during PDF generation.
     *
     * @covers \mod_customcert\template::generate_pdf
     */
    public function test_generate_pdf_restores_language_on_exception(): void {
        global $CFG, $DB, $USER;

        require_once(__DIR__ . '/fixtures/throwing_template.php');

        $this->setAdminUser();

        // Mimic an installed language pack without requiring the real pack in CI.
        $langdir = $CFG->dataroot . '/lang/fr';
        $createdlangdir = !is_dir($langdir);
        if ($createdlangdir) {
            mkdir($langdir, 0777, true);
        }
        get_string_manager()->reset_caches(true);

        $course = $this->getDataGenerator()->create_course();
        // Force English on the certificate so generation switches away from 'fr'.
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id,
            'language' => 'en',
        ]);

        $baserecord = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);

        // Throw after the runtime language switch (filename computation calls get_context()).
        // The named fixture verifies the switch actually happened before the failure point.
        $template = new throwing_template($baserecord);

        $originallanguage = current_language();

        try {
            force_current_language('fr');
            $this->assertSame('fr', current_language());

            $exceptionthrown = false;
            try {
                $template->generate_pdf(true, (int)$USER->id, true);
            } catch (\RuntimeException $exception) {
                $exceptionthrown = true;
                $this->assertSame('Intentional test exception', $exception->getMessage());
                // Production finally must restore the pre-generation language.
                $this->assertSame('fr', current_language());
            }

            $this->assertTrue($exceptionthrown, 'Expected RuntimeException was not thrown during PDF generation.');
        } finally {
            force_current_language($originallanguage);

            if ($createdlangdir && is_dir($langdir)) {
                rmdir($langdir);
            }

            get_string_manager()->reset_caches(true);
        }
    }
}

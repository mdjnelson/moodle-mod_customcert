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
 * Tests for form handling.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\element_factory;
use mod_customcert\service\template_service;
use mod_customcert\service\validation_service;

/**
 * Tests for form handling.
 *
 * @group mod_customcert
 */
final class form_handling_test extends advanced_testcase {
    /**
     * Test validation service.
     *
     * @covers \mod_customcert\service\validation_service
     */
    public function test_validation_service(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid]);
        $template = template::load((int)$templatedata->id);
        $templateservice = template_service::create();
        $pageid = $templateservice->add_page($template);

        $elementdata = (object) [
            'element' => 'text',
            'pageid' => $pageid,
        ];
        set_config('showposxy', 1, 'customcert');
        $factory = element_factory::build_with_defaults();
        $element = $factory->create_from_legacy_record($elementdata);
        $this->assertNotNull($element);

        $validationservice = new validation_service();

        // Test with valid data.
        $data = [
            'name' => 'Test element',
            'width' => 100,
            'posx' => 10,
            'posy' => 10,
            'colour' => '#000000',
        ];
        $errors = $validationservice->validate($element, $data);
        $this->assertEmpty($errors);

        // Test with invalid data.
        $data = [
            'name' => 'Test element',
            'width' => -1,
            'posx' => -1,
            'posy' => -1,
            'colour' => 'invalid',
        ];
        $errors = $validationservice->validate($element, $data);
        $this->assertArrayHasKey('width', $errors);
        $this->assertArrayHasKey('posx', $errors);
        $this->assertArrayHasKey('posy', $errors);
        $this->assertArrayHasKey('colour', $errors);
    }
}

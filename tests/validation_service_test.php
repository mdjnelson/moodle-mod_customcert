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
 * Tests for validation_service when element implements validatable_element_interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\validation_service;
use mod_customcert\tests\fixtures\dummy_validatable_element;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/dummy_validatable_element.php');

/**
 * Tests for the validation_service behaviour with validatable elements.
 *
 * @group mod_customcert
 * @covers \mod_customcert\service\validation_service
 */
final class validation_service_test extends advanced_testcase {
    /**
     * Ensure standard field validation still runs for validatable elements.
     */
    public function test_standard_validation_still_runs(): void {
        $this->resetAfterTest();

        set_config('showposxy', 1, 'customcert');

        $record = (object) [
            'name' => 'Dummy',
            'element' => 'dummy',
            'pageid' => 1,
        ];

        $element = new dummy_validatable_element($record);

        $svc = new validation_service();
        $errors = $svc->validate($element, [
            'name' => 'X',
            'width' => -1, // Invalid.
            'posx' => -1, // Invalid.
            'posy' => -1, // Invalid.
            'colour' => 'nope', // Invalid.
        ]);

        $this->assertArrayHasKey('width', $errors);
        $this->assertArrayHasKey('posx', $errors);
        $this->assertArrayHasKey('posy', $errors);
        $this->assertArrayHasKey('colour', $errors);
    }

    /**
     * Ensure custom validatable errors are merged with standard ones.
     */
    public function test_merges_custom_errors(): void {
        $this->resetAfterTest();

        set_config('showposxy', 0, 'customcert');

        $record = (object) [
            'name' => 'Dummy',
            'element' => 'dummy',
            'pageid' => 1,
        ];

        $element = new dummy_validatable_element($record);
        $element->set_validation_result([
            'width' => 'Width is odd',
            'customfield' => 'Custom problem',
        ]);

        $svc = new validation_service();
        $errors = $svc->validate($element, [
            'name' => 'X',
            'width' => 100, // Valid for standard checks.
            'colour' => '#000000',
        ]);

        // Should include custom errors.
        $this->assertArrayHasKey('customfield', $errors);
        $this->assertEquals('Custom problem', $errors['customfield']);
        $this->assertArrayHasKey('width', $errors); // From element-specific validation.
    }

    /**
     * Ensure exceptions from element->validate() are caught and mapped to a 'name' error.
     */
    public function test_exception_from_element_validate_is_caught(): void {
        $this->resetAfterTest();

        $record = (object) [
            'name' => 'Dummy',
            'element' => 'dummy',
            'pageid' => 1,
        ];

        $element = new dummy_validatable_element($record);
        $element->set_throw_on_validate(true);

        $svc = new validation_service();
        $errors = $svc->validate($element, ['name' => 'X']);

        $this->assertArrayHasKey('name', $errors);
        $this->assertNotEmpty($errors['name']);
    }
}

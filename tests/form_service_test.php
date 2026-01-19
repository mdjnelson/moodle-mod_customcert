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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\form_service;
use mod_customcert\tests\fixtures\form_buildable_test_element;
use mod_customcert\tests\fixtures\legacy_invokable_test_element;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/legacy_only_test_element.php');
require_once(__DIR__ . '/fixtures/form_buildable_test_element.php');
require_once(__DIR__ . '/fixtures/legacy_invokable_test_element.php');

/**
 * Tests for form_service.
 *
 * @package    mod_customcert
 * @category   test
 * @group      mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\service\form_service
 */
final class form_service_test extends advanced_testcase {
    /**
     * Ensure elements implementing form_buildable_interface receive the raw mform.
     */
    public function test_build_form_uses_buildable_interface(): void {
        $this->resetAfterTest();

        $calls = [];
        $mform = $this->get_form_double($calls);
        $service = new form_service();

        $record = (object) [
            'id' => 2,
            'pageid' => 3,
            'name' => 'Buildable element',
            'data' => json_encode([]),
        ];

        $element = new form_buildable_test_element($record);

        $service->build_form($mform, $element);

        $this->assertTrue($element->called);
        $this->assertCount(1, $calls['addElement']);
        $this->assertSame(['text', 'customfield', 'Custom field', []], $calls['addElement'][0]);
        $this->assertEmpty($calls['addHelpButton']);
        $this->assertEmpty($calls['setType']);
        $this->assertEmpty($calls['setDefault']);
        $this->assertEmpty($calls['addRule']);
        $this->assertEmpty($calls['setAdvanced']);
        $this->assertEmpty($calls['disabledIf']);
        $this->assertEmpty($calls['hideIf']);
    }

    /**
     * Ensure legacy elements still use render_form_elements fallback when not form-definable.
     */
    public function test_build_form_falls_back_for_legacy_elements(): void {
        $this->resetAfterTest();

        $calls = [];
        $mform = $this->get_form_double($calls);
        $service = new form_service();

        $element = new legacy_invokable_test_element((object) ['id' => 0, 'pageid' => 0, 'name' => 'Legacy']);

        $service->build_form($mform, $element);

        $this->assertTrue($element->called);
    }

    /**
     * Assert a specific call was recorded with the provided arguments.
     *
     * @param array $calls
     * @param string $method
     * @param array $expectedargs
     */
    private function assert_call_with_args(array $calls, string $method, array $expectedargs): void {
        $recorded = $this->get_calls($calls, $method);
        $this->assertNotEmpty($recorded, "Expected {$method} to be called");
        $found = false;
        foreach ($recorded as $call) {
            if ($call === $expectedargs) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Expected {$method} to be called with args: " . json_encode($expectedargs));
    }

    /**
     * Build a PHPUnit MoodleQuickForm double that records method invocations.
     *
     * @param array $calls
     * @return MoodleQuickForm
     */
    private function get_form_double(array &$calls): MoodleQuickForm {
        $calls = [
            'addElement' => [],
            'addHelpButton' => [],
            'setType' => [],
            'setDefault' => [],
            'addRule' => [],
            'setAdvanced' => [],
            'disabledIf' => [],
            'hideIf' => [],
        ];

        $mform = $this->getMockBuilder(MoodleQuickForm::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_keys($calls))
            ->getMock();

        foreach (array_keys($calls) as $method) {
            $mform->method($method)->willReturnCallback(function (...$args) use (&$calls, $method) {
                $calls[$method][] = $args;
            });
        }

        return $mform;
    }

    /**
     * Retrieve recorded calls for a given method.
     *
     * @param array $calls
     * @param string $method
     * @return array
     */
    private function get_calls(array $calls, string $method): array {
        return $calls[$method] ?? [];
    }
}

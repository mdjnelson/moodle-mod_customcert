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
 * Tests for the deprecated \mod_customcert\element_factory BC shim.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;

/**
 * Tests for the deprecated \mod_customcert\element_factory BC shim.
 *
 * @covers \mod_customcert\element_factory
 */
final class element_factory_shim_test extends advanced_testcase {
    /**
     * The shim method must exist with the original name.
     */
    public function test_method_exists(): void {
        $this->assertTrue(method_exists(\mod_customcert\element_factory::class, 'get_element_instance'));
    }

    /**
     * The shim returns a concrete element instance for a known element type.
     */
    public function test_returns_instance_for_known_element(): void {
        $record = new \stdClass();
        $record->element = 'text';
        $record->id = 1;
        $record->pageid = 1;
        $record->name = 'Test';
        $record->data = null;

        $instance = \mod_customcert\element_factory::get_element_instance($record);

        $this->assertDebuggingCalled();
        $this->assertInstanceOf(\customcertelement_text\element::class, $instance);
    }

    /**
     * The shim returns false when the element class does not exist.
     */
    public function test_returns_false_for_missing_element(): void {
        $record = new \stdClass();
        $record->element = 'nonexistentelementtype_xyz';
        $record->id = 1;
        $record->pageid = 1;
        $record->name = 'Test';
        $record->data = null;

        $result = \mod_customcert\element_factory::get_element_instance($record);

        $this->assertDebuggingCalled();
        $this->assertFalse($result);
    }

    /**
     * The shim emits a developer debugging message on every call.
     */
    public function test_emits_developer_debugging(): void {
        $record = new \stdClass();
        $record->element = 'text';
        $record->id = 1;
        $record->pageid = 1;
        $record->name = 'Test';
        $record->data = null;

        \mod_customcert\element_factory::get_element_instance($record);

        $this->assertDebuggingCalled(
            '\mod_customcert\element_factory::get_element_instance() is deprecated since Moodle 5.2. '
            . 'Use \mod_customcert\service\element_factory::build_with_defaults()->create_from_legacy_record() '
            . 'or inject \mod_customcert\service\element_factory and call create() / create_from_legacy_record().',
            DEBUG_DEVELOPER
        );
    }
}

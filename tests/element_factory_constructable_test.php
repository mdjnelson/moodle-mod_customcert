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
 * Tests for the factory preference of constructable interface.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\service\element_factory
 * @covers     \mod_customcert\service\element_registry
 */

declare(strict_types=1);

namespace mod_customcert;

use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;
use mod_customcert\tests\fixtures\constructable_test_element;
use mod_customcert\tests\fixtures\legacy_only_test_element;

defined('MOODLE_INTERNAL') || die();

// Load local fixtures explicitly to keep this test deterministic and satisfy codechecker.
require_once(__DIR__ . '/fixtures/constructable_test_element.php');
require_once(__DIR__ . '/fixtures/legacy_only_test_element.php');

/**
 * Verifies that element_factory prefers the constructable interface when available.
 *
 * @covers \mod_customcert\service\element_factory
 */
final class element_factory_constructable_test extends \advanced_testcase {
    public function test_factory_prefers_constructable_interface(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('test-constructable', constructable_test_element::class);
        $factory = new element_factory($registry);

        $record = (object)[
            'id' => 101,
            'pageid' => 7,
            'name' => 'T1',
            'data' => '{}',
        ];

        constructable_test_element::$called = false;
        $el = $factory->create('test-constructable', $record);

        $this->assertTrue(constructable_test_element::$called, 'Factory did not call from_record() on constructable element.');

        // We expect either the instance or an adapter. Either way, the creation succeeded.
        $this->assertNotNull($el);
    }

    public function test_factory_legacy_constructor_path_still_works(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        $registry->register('test-legacy', legacy_only_test_element::class);
        $factory = new element_factory($registry);

        $record = (object)[
            'id' => 202,
            'pageid' => 8,
            'name' => 'L1',
            'data' => null,
        ];

        $el = $factory->create('test-legacy', $record);
        $this->assertNotNull($el, 'Factory failed to construct legacy element via constructor path.');
    }
}

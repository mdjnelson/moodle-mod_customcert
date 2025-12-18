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
 * Unit tests for element registry and default bootstrap.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\service\element_registry;
use mod_customcert\service\element_factory;
use mod_customcert\element\element_bootstrap;

/**
 * Tests for the element registry + bootstrap default registrations.
 */
final class element_registry_test extends advanced_testcase {
    /**
     * Ensure default registrations register all expected keys.
     *
     * @covers \mod_customcert\element\element_bootstrap::register_defaults
     * @covers \mod_customcert\service\element_registry::has
     * @covers \mod_customcert\service\element_registry::all
     */
    public function test_register_defaults_registers_all_expected_keys(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);

        $expected = [
            'text', 'image', 'date', 'grade', 'coursename', 'code',
            'bgimage', 'border', 'categoryname', 'coursefield', 'daterange',
            'digitalsignature', 'expiry', 'gradeitemname', 'qrcode',
            'studentname', 'teachername', 'userfield', 'userpicture',
        ];

        foreach ($expected as $key) {
            $this->assertTrue($registry->has($key), "Missing expected registration for '{$key}'");
        }

        $all = $registry->all();
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $all);
            $this->assertIsString($all[$key]);
            $this->assertNotEmpty($all[$key]);
        }
    }

    /**
     * Smoke-test factory create() with a minimal stdClass record for a few types.
     * This ensures the resolved classes accept the legacy constructor signature.
     *
     * @covers \mod_customcert\service\element_registry::create
     * @covers \mod_customcert\service\element_registry::get
     */
    public function test_factory_create_instantiates_expected_classes(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);
        $factory = new element_factory($registry);

        // Minimal record with required fields used by legacy element constructors.
        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'Example',
            'data' => '',
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => null,
            'posy' => null,
            'width' => null,
            'refpoint' => null,
            'alignment' => 'L',
        ];

        // Test a small representative subset to avoid heavy dependencies.
        $map = [
            'text' => \customcertelement_text\element::class,
            'image' => \customcertelement_image\element::class,
            'date' => \customcertelement_date\element::class,
        ];

        foreach ($map as $type => $expectedclass) {
            $instance = $factory->create($type, $record);
            $this->assertInstanceOf($expectedclass, $instance, "Factory did not create expected class for '{$type}'");
        }
    }
}

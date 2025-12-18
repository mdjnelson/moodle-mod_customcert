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
 * Unit tests for legacy_element_adapter.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use customcertelement_text\element as text_element;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_registry;

/**
 * Tests for the legacy adapter mapping of getters.
 */
final class legacy_element_adapter_test extends advanced_testcase {
    /**
     * Ensure adapter delegates all getters to the wrapped legacy element.
     *
     * @covers \mod_customcert\element\legacy_element_adapter::get_inner
     * @covers \mod_customcert\element\legacy_element_adapter::get_id
     * @covers \mod_customcert\element\legacy_element_adapter::get_pageid
     * @covers \mod_customcert\element\legacy_element_adapter::get_name
     * @covers \mod_customcert\element\legacy_element_adapter::get_data
     * @covers \mod_customcert\element\legacy_element_adapter::get_font
     * @covers \mod_customcert\element\legacy_element_adapter::get_fontsize
     * @covers \mod_customcert\element\legacy_element_adapter::get_colour
     * @covers \mod_customcert\element\legacy_element_adapter::get_posx
     * @covers \mod_customcert\element\legacy_element_adapter::get_posy
     * @covers \mod_customcert\element\legacy_element_adapter::get_width
     * @covers \mod_customcert\element\legacy_element_adapter::get_refpoint
     * @covers \mod_customcert\element\legacy_element_adapter::get_alignment
     */
    public function test_adapter_mirrors_legacy_getters(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 42,
            'pageid' => 7,
            'name' => 'Legacy Text',
            'data' => 'hello',
            'font' => 'helvetica',
            'fontsize' => 12,
            'colour' => '#112233',
            'posx' => 10,
            'posy' => 20,
            'width' => 100,
            'refpoint' => 0,
            'alignment' => 'L',
        ];

        $legacy = new text_element($record);
        $adapter = new legacy_element_adapter($legacy);

        $this->assertSame(42, $adapter->get_id());
        $this->assertSame(7, $adapter->get_pageid());
        $this->assertSame('Legacy Text', $adapter->get_name());
        $this->assertSame('hello', $adapter->get_data());
        $this->assertSame('helvetica', $adapter->get_font());
        $this->assertSame(12, $adapter->get_fontsize());
        $this->assertSame('#112233', $adapter->get_colour());
        $this->assertSame(10, $adapter->get_posx());
        $this->assertSame(20, $adapter->get_posy());
        $this->assertSame(100, $adapter->get_width());
        $this->assertSame(0, $adapter->get_refpoint());
        $this->assertSame('L', $adapter->get_alignment());

        // Ensure get_inner returns the original instance.
        $this->assertSame($legacy, $adapter->get_inner());
    }

    /**
     * Ensure factory's helper wraps a legacy element in the adapter.
     *
     * @covers \mod_customcert\service\element_factory::wrap_legacy
     */
    public function test_factory_wraps_legacy(): void {
        $this->resetAfterTest();

        $record = (object) [
            'id' => 1,
            'pageid' => 1,
            'name' => 'X',
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

        $legacy = new text_element($record);
        $factory = new element_factory(new element_registry());
        $adapter = $factory->wrap_legacy($legacy);
        $this->assertInstanceOf(legacy_element_adapter::class, $adapter);
        $this->assertSame($legacy, $adapter->get_inner());
    }
}

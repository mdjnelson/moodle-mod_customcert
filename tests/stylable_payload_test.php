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
use mod_customcert\element\stylable_payload;
use stdClass;

/**
 * Tests for stylable_payload helper and that bundled text-like elements preserve style fields.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_customcert\element\stylable_payload
 */
final class stylable_payload_test extends advanced_testcase {
    /**
     * stylable_payload::from_form() returns all four fields with correct types.
     */
    public function test_from_form_returns_all_fields(): void {
        $form = new stdClass();
        $form->font     = 'Times';
        $form->fontsize = '14';
        $form->colour   = 'FF0000';
        $form->width    = '80';

        $result = stylable_payload::from_form($form);
        $this->assertInstanceOf(stylable_payload::class, $result);
        $this->assertSame('Times', $result->font);
        $this->assertSame(14, $result->fontsize);
        $this->assertSame('FF0000', $result->colour);
        $this->assertSame(80, $result->width);
    }

    /**
     * stylable_payload::from_form() uses safe defaults when fields are absent.
     */
    public function test_from_form_defaults_when_fields_missing(): void {
        $result = stylable_payload::from_form(new stdClass());
        $this->assertInstanceOf(stylable_payload::class, $result);
        $this->assertSame('', $result->font);
        $this->assertSame(0, $result->fontsize);
        $this->assertSame('', $result->colour);
        $this->assertSame(0, $result->width);
    }

    /**
     * stylable_payload::from_form() returns exactly the four expected keys.
     */
    public function test_from_form_returns_exactly_four_keys(): void {
        $result = stylable_payload::from_form(new stdClass());
        $this->assertSame(['font', 'fontsize', 'colour', 'width'], array_keys($result->to_array()));
    }

    /**
     * stylable_payload::from_array() populates all four fields with correct types.
     */
    public function test_from_array_returns_all_fields(): void {
        $result = stylable_payload::from_array([
            'font'     => 'Times',
            'fontsize' => '14',
            'colour'   => 'FF0000',
            'width'    => '80',
        ]);
        $this->assertInstanceOf(stylable_payload::class, $result);
        $this->assertSame('Times', $result->font);
        $this->assertSame(14, $result->fontsize);
        $this->assertSame('FF0000', $result->colour);
        $this->assertSame(80, $result->width);
    }

    /**
     * stylable_payload::from_array() uses safe defaults when the array is empty.
     */
    public function test_from_array_defaults_on_empty(): void {
        $result = stylable_payload::from_array([]);
        $this->assertInstanceOf(stylable_payload::class, $result);
        $this->assertSame('', $result->font);
        $this->assertSame(0, $result->fontsize);
        $this->assertSame('', $result->colour);
        $this->assertSame(0, $result->width);
    }

    /**
     * stylable_payload round-trips cleanly through from_array() and to_array().
     */
    public function test_round_trip(): void {
        $original = [
            'font'     => 'Helvetica',
            'fontsize' => 12,
            'colour'   => '000000',
            'width'    => 100,
        ];
        $result = stylable_payload::from_array($original);
        $this->assertSame($original, $result->to_array());
    }

    /**
     * Data provider: element class => extra field name(s) expected in payload.
     *
     * @return array<string, array{class: string, extra: array<string,mixed>, form: array<string,mixed>}>
     */
    public static function bundled_elements_provider(): array {
        return [
            'text' => [
                'customcertelement_text\element',
                ['text' => 'Hello'],
                ['text' => 'Hello'],
            ],
            'code' => [
                'customcertelement_code\element',
                [],
                [],
            ],
            'studentname' => [
                'customcertelement_studentname\element',
                [],
                [],
            ],
            'teachername' => [
                'customcertelement_teachername\element',
                ['teacher' => '2'],
                ['teacher' => '2'],
            ],
            'coursename' => [
                'customcertelement_coursename\element',
                ['coursenamedisplay' => 1],
                ['coursenamedisplay' => '1'],
            ],
            'categoryname' => [
                'customcertelement_categoryname\element',
                [],
                [],
            ],
            'userfield' => [
                'customcertelement_userfield\element',
                ['userfield' => 'email'],
                ['userfield' => 'email'],
            ],
            'coursefield' => [
                'customcertelement_coursefield\element',
                ['coursefield' => 'fullname'],
                ['coursefield' => 'fullname'],
            ],
            'grade' => [
                'customcertelement_grade\element',
                ['gradeitem' => '3', 'gradeformat' => 'percentage'],
                ['gradeitem' => '3', 'gradeformat' => 'percentage'],
            ],
            'gradeitemname' => [
                'customcertelement_gradeitemname\element',
                ['gradeitem' => '5'],
                ['gradeitem' => '5'],
            ],
            'date' => [
                'customcertelement_date\element',
                ['dateitem' => '1', 'dateformat' => 'strftimedate'],
                ['dateitem' => '1', 'dateformat' => 'strftimedate'],
            ],
            'expiry' => [
                'customcertelement_expiry\element',
                ['dateitem' => '1', 'dateformat' => 'strftimedate', 'startfrom' => 'timecreated'],
                ['dateitem' => '1', 'dateformat' => 'strftimedate', 'startfrom' => 'timecreated'],
            ],
        ];
    }

    /**
     * Each bundled text-like element preserves all four style fields in normalise_data().
     *
     * @dataProvider bundled_elements_provider
     * @param string $class Fully-qualified element class name.
     * @param array $extra Expected element-specific keys in the payload.
     * @param array $formextra Extra form fields to set.
     */
    public function test_element_normalise_data_preserves_style_fields(
        string $class,
        array $extra,
        array $formextra
    ): void {
        // Build a minimal element record (no DB needed).
        $record = (object)[
            'id'           => 1,
            'pageid'       => 1,
            'name'         => 'Test',
            'element'      => 'test',
            'data'         => null,
            'font'         => null,
            'fontsize'     => null,
            'colour'       => null,
            'width'        => null,
            'posx'         => 0,
            'posy'         => 0,
            'refpoint'     => 0,
            'alignment'    => 'L',
            'sequence'     => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        $el = new $class($record);

        $form = new stdClass();
        $form->font     = 'Helvetica';
        $form->fontsize = 12;
        $form->colour   = '000000';
        $form->width    = 100;
        foreach ($formextra as $k => $v) {
            $form->$k = $v;
        }

        $payload = $el->normalise_data($form);

        $this->assertSame('Helvetica', $payload['font'], "$class: font not preserved");
        $this->assertSame(12, $payload['fontsize'], "$class: fontsize not preserved");
        $this->assertSame('000000', $payload['colour'], "$class: colour not preserved");
        $this->assertSame(100, $payload['width'], "$class: width not preserved");

        foreach ($extra as $key => $expected) {
            $this->assertArrayHasKey($key, $payload, "$class: missing key '$key'");
            $this->assertSame($expected, $payload[$key], "$class: incorrect value for '$key'");
        }
    }
}

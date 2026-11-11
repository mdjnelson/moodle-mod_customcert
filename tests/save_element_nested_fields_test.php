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

namespace mod_customcert;

use advanced_testcase;
use core_component;
use invalid_parameter_exception;
use mod_customcert\service\element_factory;
use mod_customcert\tests\fixtures\browser_form_submission;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/browser_form_submission.php');
require_once(__DIR__ . '/fixtures/customcertelement_nestedform/element.php');

/**
 * End-to-end tests for saving nested and repeated form controls through mod_customcert_save_element.
 *
 * The fixture element is registered as a discoverable customcertelement plugin. Element discovery is memoised per
 * process, so each test runs in its own process.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
final class save_element_nested_fields_test extends advanced_testcase {
    use browser_form_submission;

    /** @var stdClass Course containing the certificate. */
    private stdClass $course;

    /** @var stdClass Teacher who manages the certificate. */
    private stdClass $teacher;

    /** @var int Template id. */
    private int $templateid;

    /** @var stdClass The stored fixture element. */
    private stdClass $element;

    /**
     * Register the fixture plugin and create a certificate with one fixture element.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $plugins = new ReflectionProperty(core_component::class, 'plugins');
        $list = $plugins->getValue();
        $list['customcertelement']['nestedform'] = __DIR__ . '/fixtures/customcertelement_nestedform';
        $plugins->setValue(null, $list);

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->course->id]);
        $this->templateid = (int)$DB->get_field('customcert', 'templateid', ['id' => $customcert->id], MUST_EXIST);
        $pageid = (int)$DB->insert_record('customcert_pages', (object)[
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->element = (object)[
            'pageid' => $pageid,
            'element' => 'nestedform',
            'name' => 'Nested',
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode(['rows' => [
                ['startdate' => 100, 'enddate' => 200, 'label' => 'Old one', 'recurring' => 0],
                ['startdate' => 300, 'enddate' => 400, 'label' => 'Old two', 'recurring' => 0],
            ]]),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $this->element->id = (int)$DB->insert_record('customcert_elements', $this->element);
        $this->setUser($this->teacher);
    }

    /**
     * Name/value pairs the browser posts for a valid edit of both rows.
     *
     * @param array $overrides Pairs appended after the defaults (later duplicates win, as in PHP).
     * @return array
     */
    private function valid_pairs(array $overrides = []): array {
        $pairs = [
            ['name' => 'name', 'value' => 'Renamed'],
            ['name' => 'startdate[0][day]', 'value' => '1'],
            ['name' => 'startdate[0][month]', 'value' => '6'],
            ['name' => 'startdate[0][year]', 'value' => '2026'],
            ['name' => 'enddate[0][day]', 'value' => '30'],
            ['name' => 'enddate[0][month]', 'value' => '6'],
            ['name' => 'enddate[0][year]', 'value' => '2026'],
            ['name' => 'label[0]', 'value' => 'Summer'],
            ['name' => 'recurring[0]', 'value' => '0'],
            ['name' => 'recurring[0]', 'value' => '1'],
            ['name' => 'startdate[1][day]', 'value' => '1'],
            ['name' => 'startdate[1][month]', 'value' => '12'],
            ['name' => 'startdate[1][year]', 'value' => '2026'],
            ['name' => 'enddate[1][day]', 'value' => '24'],
            ['name' => 'enddate[1][month]', 'value' => '12'],
            ['name' => 'enddate[1][year]', 'value' => '2026'],
            ['name' => 'label[1]', 'value' => 'Winter'],
            ['name' => 'recurring[1]', 'value' => '0'],
        ];

        return array_merge($pairs, $overrides);
    }

    /**
     * Save the given pairs as the browser would.
     *
     * @param array $pairs Name/value pairs.
     * @param bool $marker Whether the form's submission marker is posted.
     * @return array The web service result.
     */
    private function save(array $pairs, bool $marker = true): array {
        return external::save_element(
            $this->templateid,
            $this->element->id,
            $this->browser_submission($pairs, $marker)
        );
    }

    /**
     * Returns the stored element row.
     *
     * @return stdClass
     */
    private function stored(): stdClass {
        global $DB;

        return $DB->get_record('customcert_elements', ['id' => $this->element->id], '*', MUST_EXIST);
    }

    /**
     * Nested and repeated fields are rebuilt, validated by the real form, stored, and shown when the form reopens.
     * @covers \mod_customcert\external::save_element
     */
    public function test_nested_and_repeated_fields_are_saved_and_reopened(): void {
        $result = $this->save($this->valid_pairs());
        \core_external\external_api::clean_returnvalue(external::save_element_returns(), $result);

        $row = $this->stored();
        $this->assertSame('Renamed', $row->name);
        $this->assertEquals(['rows' => [
            [
                'startdate' => make_timestamp(2026, 6, 1),
                'enddate' => make_timestamp(2026, 6, 30),
                'label' => 'Summer',
                'recurring' => 1,
            ],
            [
                'startdate' => make_timestamp(2026, 12, 1),
                'enddate' => make_timestamp(2026, 12, 24),
                'label' => 'Winter',
                'recurring' => 0,
            ],
        ]], json_decode($row->data, true));
        $this->assertSame('Summer, Winter', $result['html']);

        $form = new edit_element_form(null, ['element' => $row, 'factory' => element_factory::build_with_defaults()]);
        $html = $form->render();
        $this->assertMatchesRegularExpression('/<input[^>]*name="label\[0\]"[^>]*value="Summer"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="label\[1\]"[^>]*value="Winter"/', $html);
        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="startdate\[1\]\[month\]"[^>]*>.*?<option value="12" selected/s',
            $html
        );
    }

    /**
     * Values containing characters that are special in query strings survive unchanged.
     * @covers \mod_customcert\external::save_element
     */
    public function test_special_characters_are_preserved(): void {
        $label = 'a&b=c+d%20e [x] <y> "z"';
        $this->save($this->valid_pairs([['name' => 'label[0]', 'value' => $label]]));

        $this->assertSame($label, json_decode($this->stored()->data, true)['rows'][0]['label']);
    }

    /**
     * The form's own validation still rejects an invalid date range and nothing is persisted.
     * @covers \mod_customcert\external::save_element
     */
    public function test_invalid_repeated_row_is_rejected_without_saving(): void {
        $before = $this->stored();

        try {
            $this->save($this->valid_pairs([['name' => 'enddate[1][year]', 'value' => '2020']]));
            $this->fail('An end date before the start date must be rejected.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * A submission without the form's submission marker is not treated as a form submission.
     * @covers \mod_customcert\external::save_element
     */
    public function test_missing_form_marker_is_rejected(): void {
        $before = $this->stored();

        try {
            $this->save($this->valid_pairs(), false);
            $this->fail('A submission without the form marker must be rejected.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * The form still checks the sesskey of the request.
     * @covers \mod_customcert\external::save_element
     */
    public function test_invalid_sesskey_is_rejected(): void {
        $before = $this->stored();
        $pairs = $this->browser_submission($this->valid_pairs());
        $_GET['sesskey'] = 'invalid';

        try {
            external::save_element($this->templateid, $this->element->id, $pairs);
            $this->fail('An invalid sesskey must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsesskey', $e->errorcode);
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * Valid Moodle field names are accepted, including bracketed and empty-bracket forms.
     *
     * @dataProvider valid_name_provider
     * @param string $name
     * @covers \mod_customcert\external::save_element
     */
    public function test_valid_field_names_are_accepted(string $name): void {
        $this->save($this->valid_pairs([['name' => $name, 'value' => 'x']]));

        $this->assertSame('Renamed', $this->stored()->name);
    }

    /**
     * Provider for valid field names.
     *
     * @return array
     */
    public static function valid_name_provider(): array {
        return [
            'flat' => ['extra'],
            'underscore' => ['_extra_1'],
            'hyphen' => ['my-field'],
            'leading digit' => ['123item'],
            'hyphenated key' => ['extra[item-key]'],
            'leading digit indexed' => ['123item[0]'],
            'indexed' => ['extra[0]'],
            'keyed' => ['extra[key]'],
            'empty brackets' => ['extra[]'],
            'nested' => ['extra[0][day]'],
            'maximum depth' => ['extra[1][2][3][4][5]'],
        ];
    }

    /**
     * Malformed, ambiguous or oversized field names are rejected, not silently dropped.
     *
     * @dataProvider invalid_name_provider
     * @param string $name
     * @covers \mod_customcert\external::save_element
     */
    public function test_invalid_field_names_are_rejected(string $name): void {
        $before = $this->stored();

        try {
            $this->save($this->valid_pairs([['name' => $name, 'value' => 'x']]));
            $this->fail('The field name must be rejected.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * Provider for invalid field names.
     *
     * @return array
     */
    public static function invalid_name_provider(): array {
        return [
            'empty' => [''],
            'unclosed bracket' => ['extra[0'],
            'unopened bracket' => ['extra0]'],
            'extra closing bracket' => ['extra[0]]'],
            'trailing characters' => ['extra[0]x'],
            'trailing newline' => ["extra[0]\n"],
            'leading bracket' => ['[0]'],
            'space' => ['ext ra'],
            'leading space' => [' extra'],
            'dot' => ['ext.ra'],
            'space in key' => ['extra[a b]'],
            'nul byte' => ["extra\0"],
            'non-ascii' => ['extré'],
            'too long' => [str_repeat('a', 256)],
            'too deep' => ['extra[1][2][3][4][5][6]'],
        ];
    }

    /**
     * Server-controlled fields are rejected in every representation PHP could normalise them to.
     *
     * @dataProvider protected_name_provider
     * @param string $name
     * @covers \mod_customcert\external::save_element
     */
    public function test_protected_fields_are_rejected(string $name): void {
        $before = $this->stored();

        try {
            $this->save($this->valid_pairs([['name' => $name, 'value' => '999999']]));
            $this->fail('The protected field must be rejected.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * Provider for protected field names.
     *
     * @return array
     */
    public static function protected_name_provider(): array {
        return [
            'id' => ['id'],
            'id[0]' => ['id[0]'],
            'pageid[x][y]' => ['pageid[x][y]'],
            'element[]' => ['element[]'],
            'sequence[0]' => ['sequence[0]'],
            'timecreated[0]' => ['timecreated[0]'],
            'timemodified[a]' => ['timemodified[a]'],
            'leading space' => [' id'],
            'trailing space' => ['id '],
            'dotted' => ['id.x'],
        ];
    }

    /**
     * Too many submitted fields are rejected rather than truncated by PHP's input limits.
     * @covers \mod_customcert\external::save_element
     */
    public function test_too_many_fields_are_rejected(): void {
        $before = $this->stored();
        $pairs = $this->valid_pairs();
        for ($i = 0; $i < 600; $i++) {
            $pairs[] = ['name' => "extra[$i]", 'value' => 'x'];
        }

        try {
            $this->save($pairs);
            $this->fail('An oversized submission must be rejected.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * A user who cannot manage the certificate cannot save through the nested-field path.
     * @covers \mod_customcert\external::save_element
     */
    public function test_user_without_manage_capability_is_rejected(): void {
        $before = $this->stored();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));

        try {
            $this->save($this->valid_pairs());
            $this->fail('A student must not be able to save an element.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals($before, $this->stored());
        }
    }

    /**
     * Pairs are rebuilt like a normal PHP form POST: later duplicates win and "field[]" appends.
     * @covers \mod_customcert\external::save_element
     */
    public function test_posted_values_follow_php_form_semantics(): void {
        $method = new ReflectionMethod(external::class, 'get_posted_values');
        $posted = $method->invoke(null, [
            ['name' => 'duplicate', 'value' => 'first'],
            ['name' => 'duplicate', 'value' => 'second'],
            ['name' => 'tag[]', 'value' => 'a'],
            ['name' => 'tag[]', 'value' => 'b'],
            ['name' => 'rows[1][day]', 'value' => '2'],
            ['name' => 'text', 'value' => 'a&b=c+d%20'],
            ['name' => 'my-field', 'value' => 'h'],
            ['name' => '123item', 'value' => 'd'],
            ['name' => 'field[item-key]', 'value' => 'k'],
            ['name' => '9lives[0]', 'value' => 'di'],
        ]);

        $this->assertSame([
            'duplicate' => 'second',
            'tag' => ['a', 'b'],
            'rows' => [1 => ['day' => '2']],
            'text' => 'a&b=c+d%20',
            'my-field' => 'h',
            '123item' => 'd',
            'field' => ['item-key' => 'k'],
            '9lives' => ['di'],
        ], $posted);
    }
}

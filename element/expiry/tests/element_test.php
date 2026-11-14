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
 * Tests for the expiry element.
 *
 * @package    customcertelement_expiry
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_expiry;

use advanced_testcase;
use mod_customcert\certificate;
use mod_customcert\element_factory;
use mod_customcert\element_helper;
use mod_customcert\external;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Tests for the expiry element.
 *
 * @package    customcertelement_expiry
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element_test extends advanced_testcase {
    /** @var stdClass The course. */
    private stdClass $course;

    /** @var stdClass The customcert instance. */
    private stdClass $customcert;

    /** @var stdClass The student who is issued the certificate. */
    private stdClass $student;

    /** @var int The template id. */
    private int $templateid;

    /** @var int The page id. */
    private int $pageid;

    /** @var int Fixed issue time, 2025-01-31 12:00 UTC. */
    private int $issuetime;

    /**
     * Create a course, a certificate with a page, and an issued certificate for a student.
     */
    protected function setUp(): void {
        global $DB, $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        $CFG->enablecompletion = 1;

        $this->issuetime = gmmktime(12, 0, 0, 1, 31, 2025);
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->course->id]);
        $this->templateid = (int) $DB->get_field('customcert', 'templateid', ['id' => $this->customcert->id], MUST_EXIST);
        $this->pageid = (int) $DB->insert_record('customcert_pages', (object) [
            'templateid' => $this->templateid,
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $issueid = certificate::issue_certificate((int) $this->customcert->id, (int) $this->student->id);
        $DB->set_field('customcert_issues', 'timecreated', $this->issuetime, ['id' => $issueid]);
    }

    /**
     * Create an expiry element in the certificate.
     *
     * @param array $data The stored payload.
     * @return stdClass The element record.
     */
    private function create_element(array $data): stdClass {
        global $DB;

        $element = (object) [
            'pageid' => $this->pageid,
            'element' => 'expiry',
            'name' => 'Expiry',
            'posx' => 10,
            'posy' => 10,
            'font' => 'times',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 0,
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode($data),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $element->id = (int) $DB->insert_record('customcert_elements', $element);

        return $element;
    }

    /**
     * Submit values through the element form and return the stored payload.
     *
     * @param stdClass $element The element record.
     * @param array $values The submitted values.
     * @return array|null The decoded stored data, or null if the submission was rejected.
     */
    private function save(stdClass $element, array $values): ?array {
        global $DB;

        $values += [
            'name' => 'Expiry',
            'font' => 'times',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 0,
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 1,
            'alignment' => 'L',
        ];
        $submitted = [];
        foreach ($values as $name => $value) {
            $submitted[] = ['name' => $name, 'value' => (string) $value];
        }
        $this->setAdminUser();
        try {
            external::save_element($this->templateid, $element->id, $submitted);
        } catch (\invalid_parameter_exception $e) {
            return null;
        }

        return json_decode($DB->get_field('customcert_elements', 'data', ['id' => $element->id]), true);
    }

    /**
     * Render an element on a mocked PDF and return the text that was written.
     *
     * @param stdClass $record The element record.
     * @param bool $preview Whether this is a preview.
     * @return string|null The rendered content or null if nothing was written.
     */
    private function render_pdf(stdClass $record, bool $preview = false): ?string {
        $element = element_factory::get_element_instance($record);
        $written = null;
        $pdf = $this->getMockBuilder(\pdf::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['writeHTMLCell', 'setFont', 'SetTextColor', 'GetStringWidth', 'setCellPaddings'])
            ->getMock();
        $pdf->method('GetStringWidth')->willReturn(10.0);
        $pdf->method('writeHTMLCell')->willReturnCallback(function (...$args) use (&$written) {
            $written = $args[4];
            return 0;
        });
        $element->render($pdf, $preview, $this->student);

        return $written;
    }

    /**
     * Legacy options expiry unchanged: identical to strtotime().
     *
     * @covers \customcertelement_expiry\element::calculate_expiry
     */
    public function test_legacy_options_use_strtotime(): void {
        $starts = [$this->issuetime, gmmktime(0, 0, 0, 2, 29, 2024), gmmktime(23, 59, 59, 12, 31, 2023)];
        foreach ($starts as $start) {
            foreach (['-8' => 1, '-9' => 2, '-10' => 3, '-11' => 4, '-12' => 5] as $item => $years) {
                $this->assertSame(
                    strtotime("+{$years} year" . ($years > 1 ? 's' : ''), $start),
                    element::calculate_expiry($start, $item)
                );
            }
        }
        // Legacy behaviour for 29 February is preserved (rolls over to 1 March).
        $this->assertSame(
            gmmktime(0, 0, 0, 3, 1, 2025),
            element::calculate_expiry(gmmktime(0, 0, 0, 2, 29, 2024), '-8')
        );
    }

    /**
     * Data provider for custom interval calculations.
     *
     * @return array
     */
    public static function custom_interval_provider(): array {
        return [
            'days' => [gmmktime(12, 0, 0, 1, 15, 2025), 30, 'days', gmmktime(12, 0, 0, 2, 14, 2025)],
            'days into leap day' => [gmmktime(12, 0, 0, 2, 28, 2024), 1, 'days', gmmktime(12, 0, 0, 2, 29, 2024)],
            'days across leap year' => [gmmktime(12, 0, 0, 3, 1, 2024), 365, 'days', gmmktime(12, 0, 0, 3, 1, 2025)],
            'six months' => [gmmktime(12, 0, 0, 1, 15, 2025), 6, 'months', gmmktime(12, 0, 0, 7, 15, 2025)],
            'six months string' => [gmmktime(12, 0, 0, 1, 15, 2025), '6', 'months', gmmktime(12, 0, 0, 7, 15, 2025)],
            'months crossing year' => [gmmktime(12, 0, 0, 11, 10, 2025), 3, 'months', gmmktime(12, 0, 0, 2, 10, 2026)],
            'years' => [gmmktime(12, 0, 0, 1, 15, 2025), 2, 'years', gmmktime(12, 0, 0, 1, 15, 2027)],
            'end of month clamps to 28 Feb' => [gmmktime(12, 0, 0, 1, 31, 2025), 1, 'months', gmmktime(12, 0, 0, 2, 28, 2025)],
            'end of month clamps to 29 Feb' => [gmmktime(12, 0, 0, 1, 31, 2024), 1, 'months', gmmktime(12, 0, 0, 2, 29, 2024)],
            'end of month 30 day month' => [gmmktime(12, 0, 0, 1, 31, 2025), 3, 'months', gmmktime(12, 0, 0, 4, 30, 2025)],
            'six months from August 31' => [gmmktime(12, 0, 0, 8, 31, 2025), 6, 'months', gmmktime(12, 0, 0, 2, 28, 2026)],
            'December 31 plus one month' => [gmmktime(12, 0, 0, 12, 31, 2025), 1, 'months', gmmktime(12, 0, 0, 1, 31, 2026)],
            'leap day plus one year' => [gmmktime(12, 0, 0, 2, 29, 2024), 1, 'years', gmmktime(12, 0, 0, 2, 28, 2025)],
            'leap day plus four years' => [gmmktime(12, 0, 0, 2, 29, 2024), 4, 'years', gmmktime(12, 0, 0, 2, 29, 2028)],
            'time of day is kept' => [gmmktime(23, 59, 59, 1, 31, 2025), 1, 'months', gmmktime(23, 59, 59, 2, 28, 2025)],
        ];
    }

    /**
     * Custom intervals are calculated predictably.
     *
     * @dataProvider custom_interval_provider
     * @covers \customcertelement_expiry\element::calculate_expiry
     *
     * @param int $start Start time.
     * @param int|string $duration Duration.
     * @param string $unit Unit.
     * @param int $expected Expected expiry.
     */
    public function test_custom_interval_calculation($start, $duration, string $unit, int $expected): void {
        $this->assertSame($expected, element::calculate_expiry($start, 'custom', $duration, $unit));
    }

    /**
     * Calendar calculations use the server timezone.
     *
     * @covers \customcertelement_expiry\element::calculate_expiry
     */
    public function test_custom_interval_uses_local_wall_clock_across_dst(): void {
        $this->setTimezone('Europe/London', 'Europe/London');
        // 2025-03-29 12:00 GMT, daylight saving starts on 30 March.
        $start = gmmktime(12, 0, 0, 3, 29, 2025);
        $expected = gmmktime(11, 0, 0, 4, 1, 2025); // 12:00 BST.
        $this->assertSame($expected, element::calculate_expiry($start, 'custom', 3, 'days'));
    }

    /**
     * Data provider for invalid configurations.
     *
     * @return array
     */
    public static function invalid_interval_provider(): array {
        return [
            'zero' => ['custom', 0, 'days'],
            'negative' => ['custom', -3, 'months'],
            'not a number' => ['custom', 'abc', 'years'],
            'decimal' => ['custom', '1.5', 'years'],
            'float' => ['custom', 1.5, 'years'],
            'leading plus' => ['custom', '+2', 'years'],
            'missing duration' => ['custom', null, 'days'],
            'missing unit' => ['custom', 1, null],
            'unknown unit' => ['custom', 1, 'weeks'],
            'too many days' => ['custom', 36501, 'days'],
            'too many months' => ['custom', 1201, 'months'],
            'too many years' => ['custom', 101, 'years'],
            'unknown date item' => ['-13', 1, 'days'],
            'module reference' => ['5', 1, 'days'],
            'empty' => ['', null, null],
        ];
    }

    /**
     * Invalid or missing configuration results in no expiry rather than an error.
     *
     * @dataProvider invalid_interval_provider
     * @covers \customcertelement_expiry\element::calculate_expiry
     *
     * @param string $dateitem Date item.
     * @param mixed $duration Duration.
     * @param mixed $unit Unit.
     */
    public function test_invalid_configuration_returns_zero(string $dateitem, $duration, $unit): void {
        $this->assertSame(0, element::calculate_expiry($this->issuetime, $dateitem, $duration, $unit));
    }

    /**
     * Expiry for reporting is relative to the issue date, for legacy and custom options.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_expiry_report_relative_to_issue(): void {
        global $DB;

        $element = $this->create_element(['dateitem' => '-9', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->assertTrue(element::has_expiry((int) $this->customcert->id));
        $this->assertSame(
            gmmktime(12, 0, 0, 1, 31, 2027),
            element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id)
        );

        $DB->set_field('customcert_elements', 'data', json_encode([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'award',
            'expiryduration' => 6,
            'expiryunit' => 'months',
        ]), ['id' => $element->id]);
        $this->assertSame(
            gmmktime(12, 0, 0, 7, 31, 2025),
            element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id)
        );
        $this->assertNotEmpty(element::get_expiry_html((int) $this->customcert->id, (int) $this->student->id));
    }

    /**
     * Expiry for reporting is relative to course completion, and empty when not completed.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::get_expiry_html
     */
    public function test_expiry_report_relative_to_completion(): void {
        global $DB;

        $this->create_element([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'coursecomplete',
            'expiryduration' => 1,
            'expiryunit' => 'months',
        ]);

        // No completion record: no expiry.
        $this->assertSame(0, element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id));
        $this->assertSame('', element::get_expiry_html((int) $this->customcert->id, (int) $this->student->id));

        // Record without a completion time: no expiry.
        $DB->insert_record('course_completions', (object) [
            'course' => $this->course->id,
            'userid' => $this->student->id,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => null,
            'reaggregate' => 0,
        ]);
        $this->assertSame(0, element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id));

        // Completed: calculated from the completion date, not the issue date.
        $DB->set_field('course_completions', 'timecompleted', gmmktime(9, 0, 0, 1, 30, 2025), [
            'course' => $this->course->id,
            'userid' => $this->student->id,
        ]);
        $this->assertSame(
            gmmktime(9, 0, 0, 2, 28, 2025),
            element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id)
        );
    }

    /**
     * Invalid stored configuration is handled safely in reporting.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_expiry_report_with_invalid_configuration(): void {
        $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award', 'expiryunit' => 'weeks']);
        $this->assertSame(0, element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id));
        $this->assertSame('', element::get_expiry_html((int) $this->customcert->id, (int) $this->student->id));
    }

    /**
     * Reporting returns nothing when the certificate has no expiry element.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_expiry_report_without_element(): void {
        $this->assertFalse(element::has_expiry((int) $this->customcert->id));
        $this->assertSame(0, element::get_expiry_date((int) $this->customcert->id, (int) $this->student->id));
    }

    /**
     * PDF rendering of legacy options, with a date format and "Valid for".
     *
     * @covers \customcertelement_expiry\element::render
     */
    public function test_render_pdf_legacy(): void {
        $record = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);
        $expected = element_helper::get_date_format_string(gmmktime(12, 0, 0, 1, 31, 2026), '2');
        $this->assertSame($expected, $this->render_pdf($record));

        $years = ['-8' => 1, '-9' => 2, '-10' => 3, '-11' => 4, '-12' => 5];
        foreach ($years as $item => $count) {
            $record->data = json_encode(['dateitem' => $item, 'dateformat' => 'validfor', 'startfrom' => 'award']);
            $this->assertSame('Valid for ' . $count . ' year' . ($count > 1 ? 's' : ''), $this->render_pdf($record));
        }
    }

    /**
     * PDF rendering of a custom interval.
     *
     * @covers \customcertelement_expiry\element::render
     */
    public function test_render_pdf_custom(): void {
        $record = $this->create_element([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'award',
            'expiryduration' => 6,
            'expiryunit' => 'months',
        ]);
        $this->assertSame(
            element_helper::get_date_format_string(gmmktime(12, 0, 0, 7, 31, 2025), '2'),
            $this->render_pdf($record)
        );

        $cases = [
            [1, 'days', 'Valid for 1 day'],
            [10, 'days', 'Valid for 10 days'],
            [1, 'months', 'Valid for 1 month'],
            [6, 'months', 'Valid for 6 months'],
            [1, 'years', 'Valid for 1 year'],
            [7, 'years', 'Valid for 7 years'],
        ];
        foreach ($cases as [$duration, $unit, $expected]) {
            $record->data = json_encode([
                'dateitem' => 'custom',
                'dateformat' => 'validfor',
                'startfrom' => 'award',
                'expiryduration' => $duration,
                'expiryunit' => $unit,
            ]);
            $this->assertSame($expected, $this->render_pdf($record));
        }
    }

    /**
     * PDF rendering when the start date is course completion and there is none, or the configuration is invalid.
     *
     * @covers \customcertelement_expiry\element::render
     */
    public function test_render_pdf_missing_completion_and_invalid(): void {
        $record = $this->create_element([
            'dateitem' => 'custom',
            'dateformat' => 'validfor',
            'startfrom' => 'coursecomplete',
            'expiryduration' => 6,
            'expiryunit' => 'months',
        ]);
        $this->assertNull($this->render_pdf($record));

        $record->data = json_encode([
            'dateitem' => 'custom',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'expiryduration' => 0,
            'expiryunit' => 'months',
        ]);
        $this->assertNull($this->render_pdf($record));

        $record->data = json_encode(['dateitem' => '-99', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->assertNull($this->render_pdf($record));
    }

    /**
     * Preview rendering calculates from now.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_preview_and_html_rendering(): void {
        $record = $this->create_element([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'coursecomplete',
            'expiryduration' => 2,
            'expiryunit' => 'years',
        ]);

        // Even with course completion selected and no completion, a preview shows a date.
        $before = element::calculate_expiry(time(), 'custom', 2, 'years');
        $rendered = $this->render_pdf($record, true);
        $after = element::calculate_expiry(time(), 'custom', 2, 'years');
        $this->assertContains($rendered, [
            element_helper::get_date_format_string($before, '2'),
            element_helper::get_date_format_string($after, '2'),
        ]);

        $html = element_factory::get_element_instance($record)->render_html();
        $this->assertStringContainsString(element_helper::get_date_format_string($after, '2'), $html);

        $record->data = json_encode([
            'dateitem' => 'custom',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'expiryduration' => 3,
            'expiryunit' => 'days',
        ]);
        $this->assertStringContainsString('Valid for 3 days', element_factory::get_element_instance($record)->render_html());

        // Legacy preview.
        $record->data = json_encode(['dateitem' => '-10', 'dateformat' => 'validfor', 'startfrom' => 'award']);
        $this->assertStringContainsString('Valid for 3 years', element_factory::get_element_instance($record)->render_html());

        // Invalid configuration renders nothing.
        $record->data = json_encode([
            'dateitem' => 'custom',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'expiryunit' => 'days',
        ]);
        $this->assertSame('', element_factory::get_element_instance($record)->render_html());
    }

    /**
     * Saving legacy options keeps the original stored format.
     *
     * @covers \customcertelement_expiry\element::save_unique_data
     */
    public function test_save_legacy_keeps_format(): void {
        $record = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);
        $stored = $this->save($record, ['dateitem' => '-12', 'dateformat' => 'validfor', 'startfrom' => 'award']);
        $this->assertSame(['dateitem' => '-12', 'dateformat' => 'validfor', 'startfrom' => 'award'], $stored);
    }

    /**
     * Saving, editing and switching between custom and legacy options.
     *
     * @covers \customcertelement_expiry\element::save_unique_data
     * @covers \customcertelement_expiry\element::validate_form_elements
     */
    public function test_save_and_edit_custom(): void {
        $record = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);

        $stored = $this->save($record, [
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'award',
            'expiryduration' => ' 6 ',
            'expiryunit' => 'months',
        ]);
        $this->assertSame([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'award',
            'expiryduration' => 6,
            'expiryunit' => 'months',
        ], $stored);

        // Editing only the unit keeps the stored duration.
        $stored = $this->save($record, ['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award', 'expiryunit' => 'years']);
        $this->assertSame(6, $stored['expiryduration']);
        $this->assertSame('years', $stored['expiryunit']);

        // Switching back to a legacy option drops the custom settings.
        $stored = $this->save($record, ['dateitem' => '-9', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->assertSame(['dateitem' => '-9', 'dateformat' => '2', 'startfrom' => 'award'], $stored);
    }

    /**
     * Invalid custom values are rejected when saving.
     *
     * @covers \customcertelement_expiry\element::validate_form_elements
     */
    public function test_save_rejects_invalid_values(): void {
        global $DB;

        $record = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);
        $original = $DB->get_field('customcert_elements', 'data', ['id' => $record->id]);

        $invalid = [
            ['expiryduration' => '0', 'expiryunit' => 'days'],
            ['expiryduration' => '-1', 'expiryunit' => 'days'],
            ['expiryduration' => '', 'expiryunit' => 'days'],
            ['expiryduration' => 'abc', 'expiryunit' => 'days'],
            ['expiryduration' => '1.5', 'expiryunit' => 'days'],
            ['expiryduration' => '101', 'expiryunit' => 'years'],
            ['expiryduration' => '1', 'expiryunit' => 'weeks'],
        ];
        foreach ($invalid as $values) {
            $result = $this->save($record, ['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award'] + $values);
            $this->assertNull($result, json_encode($values));
            $this->assertSame($original, $DB->get_field('customcert_elements', 'data', ['id' => $record->id]));
        }
    }

    /**
     * Validation errors are reported against the relevant fields.
     *
     * @covers \customcertelement_expiry\element::validate_form_elements
     */
    public function test_validate_form_elements_errors(): void {
        $record = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);
        $element = element_factory::get_element_instance($record);
        $base = [
            'colour' => '#000000',
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
        ];

        $this->assertSame([], $element->validate_form_elements($base + ['dateitem' => '-8'], []));
        $this->assertSame([], $element->validate_form_elements(
            $base + ['dateitem' => 'custom', 'expiryduration' => '6', 'expiryunit' => 'months'],
            []
        ));

        $errors = $element->validate_form_elements(
            $base + ['dateitem' => 'custom', 'expiryduration' => 'x', 'expiryunit' => 'months'],
            []
        );
        $this->assertArrayHasKey('expiryduration', $errors);

        $errors = $element->validate_form_elements(
            $base + ['dateitem' => 'custom', 'expiryduration' => '1', 'expiryunit' => 'decades'],
            []
        );
        $this->assertArrayHasKey('expiryunit', $errors);

        $errors = $element->validate_form_elements(
            $base + ['dateitem' => 'custom', 'expiryduration' => '37000', 'expiryunit' => 'days'],
            []
        );
        $this->assertArrayHasKey('expiryduration', $errors);
    }

    /**
     * The edit form is populated from existing legacy and custom configurations.
     *
     * @covers \customcertelement_expiry\element::definition_after_data
     */
    public function test_edit_form_is_populated(): void {
        // Loads the form file which registers the colour picker element type.
        class_exists(\mod_customcert\edit_element_form::class);

        $record = $this->create_element([
            'dateitem' => 'custom',
            'dateformat' => '2',
            'startfrom' => 'award',
            'expiryduration' => 18,
            'expiryunit' => 'days',
        ]);
        $element = element_factory::get_element_instance($record);
        $mform = new \MoodleQuickForm('test', 'post', '');
        $element->render_form_elements($mform);
        $element->definition_after_data($mform);
        $this->assertSame('custom', $mform->getElement('dateitem')->getValue()[0]);
        $this->assertEquals(18, $mform->getElement('expiryduration')->getValue());
        $this->assertSame('days', $mform->getElement('expiryunit')->getValue()[0]);

        // A legacy configuration with no custom settings leaves the defaults in place.
        $record = $this->create_element(['dateitem' => '-10', 'dateformat' => '2', 'startfrom' => 'award']);
        $element = element_factory::get_element_instance($record);
        $mform = new \MoodleQuickForm('test2', 'post', '');
        $element->render_form_elements($mform);
        $element->definition_after_data($mform);
        $this->assertSame('-10', $mform->getElement('dateitem')->getValue()[0]);
        $this->assertSame('months', $mform->getElement('expiryunit')->getValue()[0]);
    }
}

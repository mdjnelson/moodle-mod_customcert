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
 * Unit tests for the expiry element.
 *
 * @package    customcertelement_expiry
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_expiry;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use context_module;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\element_renderer;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;
use MoodleQuickForm;
use pdf;
use stdClass;

global $CFG;

require_once($CFG->libdir . '/pdflib.php');

/**
 * Unit tests for the expiry element.
 *
 * @package    customcertelement_expiry
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element_test extends advanced_testcase {
    /**
     * Helper to build a minimal element DB record.
     *
     * @param array $override
     * @return stdClass
     */
    private function make_record(array $override = []): stdClass {
        return (object) array_merge([
            'id' => 1,
            'pageid' => 1,
            'name' => 'Expiry',
            'element' => 'expiry',
            'data' => json_encode([
                'dateitem' => '-8',
                'dateformat' => 'strftimedate',
                'startfrom' => '-1',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
            'font' => null,
            'fontsize' => null,
            'colour' => null,
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $override);
    }

    /**
     * Test that the constructor returns an instance of element.
     *
     * @covers \customcertelement_expiry\element::__construct
     */
    public function test_instantiation(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(element::class, $el);
    }

    /**
     * Test that the element implements all required interfaces.
     *
     * @covers \customcertelement_expiry\element
     */
    public function test_implements_interfaces(): void {
        $el = new element($this->make_record());
        $this->assertInstanceOf(form_element_interface::class, $el);
        $this->assertInstanceOf(persistable_element_interface::class, $el);
        $this->assertInstanceOf(renderable_element_interface::class, $el);
        $this->assertInstanceOf(validatable_element_interface::class, $el);
    }

    /**
     * Test that normalise_data() returns expected keys and values.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_normalise_data_returns_expected_keys(): void {
        $el = new element($this->make_record());
        $formdata = (object) [
            'dateitem' => '-9',
            'dateformat' => 'strftimemonthyear',
            'startfrom' => '-1',
            'font' => 'helvetica',
            'fontsize' => 14,
            'colour' => '#ff0000',
            'width' => 100,
        ];
        $result = $el->normalise_data($formdata);
        $this->assertSame('-9', $result['dateitem']);
        $this->assertSame('strftimemonthyear', $result['dateformat']);
        $this->assertSame('-1', $result['startfrom']);
        $this->assertSame('helvetica', $result['font']);
        $this->assertSame(14, $result['fontsize']);
        $this->assertSame('#ff0000', $result['colour']);
        $this->assertSame(100, $result['width']);
    }

    /**
     * Test that normalise_data() handles missing fields gracefully.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_normalise_data_handles_missing_fields(): void {
        $el = new element($this->make_record());
        $result = $el->normalise_data(new stdClass());
        $this->assertSame('', $result['dateitem']);
        $this->assertSame('', $result['dateformat']);
        $this->assertSame('', $result['startfrom']);
        $this->assertSame('', $result['font']);
        $this->assertSame(0, $result['fontsize']);
        $this->assertSame('', $result['colour']);
        $this->assertSame(0, $result['width']);
    }

    /**
     * Test that render_html() returns empty string when no data is set.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_empty_when_no_data(): void {
        $el = new element($this->make_record(['data' => null]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test that render_html() returns a non-empty string for a date-based expiry.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_returns_date_string(): void {
        $el = new element($this->make_record());
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Test that render_html() returns a 'valid for' string when dateformat is 'validfor'.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_returns_validfor_string(): void {
        $el = new element($this->make_record([
            'data' => json_encode([
                'dateitem' => '-8',
                'dateformat' => 'validfor',
                'startfrom' => '-1',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
        ]));
        $html = $el->render_html();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Helper to create a full customcert setup and return [elementid, customcertid].
     *
     * @return array{int, int}
     */
    private function create_customcert_setup(?array $payload = null): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = $DB->get_record(
            'customcert_templates',
            ['contextid' => context_module::instance($customcert->cmid)->id]
        );

        $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$template->id));
        $service = template_service::create();
        $pageid = $service->add_page($template);

        $rec = new stdClass();
        $rec->name = 'Expiry';
        $rec->element = 'expiry';
        $rec->pageid = $pageid;
        $rec->data = json_encode($payload ?? ['dateitem' => '-8', 'dateformat' => 'strftimedate', 'startfrom' => '-1']);
        $rec->sequence = element_helper::get_element_sequence($pageid);
        $rec->timecreated = time();
        $rec->id = $DB->insert_record('customcert_elements', $rec);

        return [(int)$rec->id, (int)$customcert->id];
    }

    /**
     * Test render_html() does not throw a TypeError when dateformat is stored as a legacy integer.
     *
     * Prior to the fix in cfd41a98, passing an integer dateformat to get_date_format_string()
     * caused a fatal TypeError under PHP 8.3. This test stores dateformat as an integer in the
     * JSON payload and exercises the full render_html() code path to prove the fix holds.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_with_legacy_integer_dateformat(): void {
        $el = new element($this->make_record([
            'data' => json_encode([
                'dateitem' => '-8',
                'dateformat' => 1,
                'startfrom' => '-1',
                'font' => 'times',
                'fontsize' => 12,
                'colour' => '#000000',
                'width' => 0,
            ]),
        ]));

        $html = $el->render_html();

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Test render() does not throw a TypeError when dateformat is stored as a legacy integer.
     *
     * Prior to the fix in cfd41a98, passing an integer dateformat to get_date_format_string()
     * caused a fatal TypeError under PHP 8.3. This test stores dateformat as an integer in the
     * JSON payload and exercises the full render() code path to prove the fix holds.
     *
     * @covers \customcertelement_expiry\element::render
     */
    public function test_render_with_legacy_integer_dateformat(): void {
        global $DB;

        $this->resetAfterTest();

        [$elementid, $customcertid] = $this->create_customcert_setup();

        // Overwrite with an integer dateformat to simulate legacy JSON data.
        $DB->set_field(
            'customcert_elements',
            'data',
            json_encode(['dateitem' => '-8', 'dateformat' => 1, 'startfrom' => '-1']),
            ['id' => $elementid]
        );

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $DB->get_field('customcert', 'course', ['id' => $customcertid]));
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $rec = $DB->get_record('customcert_elements', ['id' => $elementid]);
        $el = new element($rec);
        $user = $DB->get_record('user', ['id' => $student->id]);

        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);

        $this->assertTrue(true);
    }

    /**
     * Build a payload for a custom interval.
     *
     * @param mixed $value
     * @param mixed $unit
     * @param array $extra
     * @return array
     */
    private function custom_payload(mixed $value, mixed $unit, array $extra = []): array {
        return array_merge([
            'dateitem' => 'custom',
            'expiryvalue' => $value,
            'expiryunit' => $unit,
            'dateformat' => 'strftimedate',
            'startfrom' => 'award',
        ], $extra);
    }

    /**
     * Data provider for expiry calculations: payload, start (Y-m-d H:i:s UTC), expected (Y-m-d H:i:s UTC).
     *
     * @return array
     */
    public static function calculation_provider(): array {
        $custom = static fn($value, $unit) => ['dateitem' => 'custom', 'expiryvalue' => $value, 'expiryunit' => $unit];

        return [
            'legacy 1 year' => [['dateitem' => '-8'], '2024-03-15 10:00:00', '2025-03-15 10:00:00'],
            'legacy 2 years' => [['dateitem' => '-9'], '2024-03-15 10:00:00', '2026-03-15 10:00:00'],
            'legacy 3 years' => [['dateitem' => '-10'], '2024-03-15 10:00:00', '2027-03-15 10:00:00'],
            'legacy 4 years' => [['dateitem' => '-11'], '2024-03-15 10:00:00', '2028-03-15 10:00:00'],
            'legacy 5 years' => [['dateitem' => '-12'], '2024-03-15 10:00:00', '2029-03-15 10:00:00'],
            'legacy leap day is unchanged' => [['dateitem' => '-8'], '2024-02-29 10:00:00', '2025-03-01 10:00:00'],
            'custom days' => [$custom(30, 'days'), '2025-01-15 08:30:00', '2025-02-14 08:30:00'],
            'custom days across leap day' => [$custom(1, 'days'), '2024-02-28 08:30:00', '2024-02-29 08:30:00'],
            'custom six months' => [$custom(6, 'months'), '2025-01-15 08:30:00', '2025-07-15 08:30:00'],
            'custom six months across year' => [$custom(6, 'months'), '2025-09-10 08:30:00', '2026-03-10 08:30:00'],
            'custom twelve months' => [$custom(12, 'months'), '2025-09-10 08:30:00', '2026-09-10 08:30:00'],
            'custom years' => [$custom(2, 'years'), '2025-09-10 08:30:00', '2027-09-10 08:30:00'],
            'string values from older JSON' => [$custom('6', 'months'), '2025-01-15 08:30:00', '2025-07-15 08:30:00'],
            'month end clamped' => [$custom(1, 'months'), '2025-01-31 08:30:00', '2025-02-28 08:30:00'],
            'month end clamped in leap year' => [$custom(1, 'months'), '2024-01-31 08:30:00', '2024-02-29 08:30:00'],
            'six months from 31 August' => [$custom(6, 'months'), '2025-08-31 08:30:00', '2026-02-28 08:30:00'],
            'month end kept when it exists' => [$custom(1, 'months'), '2025-03-31 08:30:00', '2025-04-30 08:30:00'],
            'leap day plus one year' => [$custom(1, 'years'), '2024-02-29 08:30:00', '2025-02-28 08:30:00'],
            'leap day plus four years' => [$custom(4, 'years'), '2024-02-29 08:30:00', '2028-02-29 08:30:00'],
            'december plus one month' => [$custom(1, 'months'), '2025-12-31 08:30:00', '2026-01-31 08:30:00'],
        ];
    }

    /**
     * Test the expiry calculation for the legacy and custom options.
     *
     * @dataProvider calculation_provider
     * @covers \customcertelement_expiry\element::calculate_expiry
     * @param array $payload
     * @param string $start
     * @param string $expected
     */
    public function test_calculate_expiry(array $payload, string $start, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $result = element::calculate_expiry($payload, (int)strtotime($start . ' UTC'));

        $this->assertSame($expected, gmdate('Y-m-d H:i:s', $result));
    }

    /**
     * Data provider for invalid configurations.
     *
     * @return array
     */
    public static function invalid_configuration_provider(): array {
        return [
            'missing everything' => [[]],
            'unknown dateitem' => [['dateitem' => '-99']],
            'missing duration' => [['dateitem' => 'custom', 'expiryunit' => 'days']],
            'missing unit' => [['dateitem' => 'custom', 'expiryvalue' => 5]],
            'zero duration' => [['dateitem' => 'custom', 'expiryvalue' => 0, 'expiryunit' => 'days']],
            'negative duration' => [['dateitem' => 'custom', 'expiryvalue' => -3, 'expiryunit' => 'days']],
            'fractional duration' => [['dateitem' => 'custom', 'expiryvalue' => '1.5', 'expiryunit' => 'days']],
            'non numeric duration' => [['dateitem' => 'custom', 'expiryvalue' => 'abc', 'expiryunit' => 'days']],
            'too large duration' => [['dateitem' => 'custom', 'expiryvalue' => 1000, 'expiryunit' => 'years']],
            'unknown unit' => [['dateitem' => 'custom', 'expiryvalue' => 5, 'expiryunit' => 'weeks']],
        ];
    }

    /**
     * Test invalid configurations produce no expiry and render nothing.
     *
     * @dataProvider invalid_configuration_provider
     * @covers \customcertelement_expiry\element::calculate_expiry
     * @covers \customcertelement_expiry\element::render_html
     * @param array $payload
     */
    public function test_invalid_configuration_is_safe(array $payload): void {
        $this->assertSame(0, element::calculate_expiry($payload, 1700000000));

        foreach (['strftimedate', 'validfor'] as $format) {
            $el = new element($this->make_record(['data' => json_encode($payload + ['dateformat' => $format])]));
            $this->assertSame('', $el->render_html());
        }
    }

    /**
     * Test normalise_data() stores the custom interval only for the custom option.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_normalise_data_custom_interval(): void {
        $el = new element($this->make_record());

        $custom = $el->normalise_data((object)['dateitem' => 'custom', 'expiryvalue' => '6', 'expiryunit' => 'months']);
        $this->assertSame(6, $custom['expiryvalue']);
        $this->assertSame('months', $custom['expiryunit']);

        $legacy = $el->normalise_data((object)['dateitem' => '-9', 'expiryvalue' => '6', 'expiryunit' => 'months']);
        $this->assertArrayNotHasKey('expiryvalue', $legacy);
        $this->assertArrayNotHasKey('expiryunit', $legacy);
        $this->assertSame('-9', $legacy['dateitem']);
    }

    /**
     * Test validate() for the custom interval.
     *
     * @covers \customcertelement_expiry\element::validate
     */
    public function test_validate(): void {
        $el = new element($this->make_record());

        $this->assertSame([], $el->validate(['dateitem' => '-8']));
        $this->assertSame([], $el->validate(['dateitem' => '-8', 'expiryvalue' => 'abc', 'expiryunit' => 'x']));
        $this->assertSame([], $el->validate(['dateitem' => 'custom', 'expiryvalue' => 6, 'expiryunit' => 'months']));
        $this->assertSame([], $el->validate(['dateitem' => 'custom', 'expiryvalue' => 999, 'expiryunit' => 'days']));

        foreach ([0, -1, 1000, 'abc', '1.5', '', null] as $value) {
            $errors = $el->validate(['dateitem' => 'custom', 'expiryvalue' => $value, 'expiryunit' => 'days']);
            $this->assertSame(['expiryvalue'], array_keys($errors));
        }

        $errors = $el->validate(['dateitem' => 'custom', 'expiryvalue' => 1, 'expiryunit' => 'weeks']);
        $this->assertSame(['expiryunit'], array_keys($errors));

        $errors = $el->validate(['dateitem' => 'custom']);
        $this->assertEqualsCanonicalizing(['expiryvalue', 'expiryunit'], array_keys($errors));
    }

    /**
     * Data provider for the 'valid for' text.
     *
     * @return array
     */
    public static function validfor_provider(): array {
        return [
            'legacy 1 year' => ['-8', null, null, 'validfor1year', null],
            'legacy 5 years' => ['-12', null, null, 'validfor5years', null],
            'one day' => ['custom', 1, 'days', 'validforcustomday', 1],
            'many days' => ['custom', 10, 'days', 'validforcustomdays', 10],
            'one month' => ['custom', 1, 'months', 'validforcustommonth', 1],
            'six months' => ['custom', 6, 'months', 'validforcustommonths', 6],
            'one year' => ['custom', 1, 'years', 'validforcustomyear', 1],
            'many years' => ['custom', 7, 'years', 'validforcustomyears', 7],
        ];
    }

    /**
     * Test the preview and PDF render the 'valid for' text from language strings.
     *
     * @dataProvider validfor_provider
     * @covers \customcertelement_expiry\element::render_html
     * @covers \customcertelement_expiry\element::render
     * @param string $dateitem
     * @param int|null $value
     * @param string|null $unit
     * @param string $stringid
     * @param int|null $a
     */
    public function test_validfor_text(string $dateitem, ?int $value, ?string $unit, string $stringid, ?int $a): void {
        $expected = get_string($stringid, 'customcertelement_expiry', $a);
        $payload = ['dateitem' => $dateitem, 'dateformat' => 'validfor', 'startfrom' => 'award'];
        if ($value !== null) {
            $payload['expiryvalue'] = $value;
            $payload['expiryunit'] = $unit;
        }
        $el = new element($this->make_record(['data' => json_encode($payload)]));

        $this->assertStringContainsString($expected, $el->render_html());

        $renderer = $this->createMock(element_renderer::class);
        $renderer->expects($this->once())->method('render_content')->with($el, $expected);
        $el->render(new pdf(), true, (object)['id' => 2], $renderer);
    }

    /**
     * Test the preview shows an expiry date for a custom interval.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_custom_interval_date(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $el = new element($this->make_record(['data' => json_encode($this->custom_payload(6, 'months'))]));
        $before = time();
        $html = $el->render_html();
        $expected = element_helper::get_date_format_string(
            element::calculate_expiry($this->custom_payload(6, 'months'), $before),
            'strftimedate'
        );

        $this->assertStringContainsString($expected, $html);
    }

    /**
     * Test the PDF output for a custom interval is calculated from the issue date.
     *
     * @covers \customcertelement_expiry\element::render
     */
    public function test_render_pdf_custom_interval_from_issue_date(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [$elementid, $customcertid] = $this->create_customcert_setup($this->custom_payload(6, 'months'));
        $student = $this->getDataGenerator()->create_user();
        $course = $DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $this->getDataGenerator()->enrol_user($student->id, $course);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        $issued = (int)strtotime('2025-01-31 08:30:00 UTC');
        $DB->set_field('customcert_issues', 'timecreated', $issued, ['customcertid' => $customcertid]);

        $el = new element($DB->get_record('customcert_elements', ['id' => $elementid]));
        $renderer = $this->createMock(element_renderer::class);
        $renderer->expects($this->once())
            ->method('render_content')
            ->with($el, element_helper::get_date_format_string((int)strtotime('2025-07-31 08:30:00 UTC'), 'strftimedate'));
        $el->render(new pdf(), false, $DB->get_record('user', ['id' => $student->id]), $renderer);
    }

    /**
     * Test the expiry report for both start dates, and for a missing completion date.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::get_expiry_html
     */
    public function test_expiry_reporting(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [$elementid, $customcertid] = $this->create_customcert_setup($this->custom_payload(1, 'months'));
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        $DB->set_field('customcert_issues', 'timecreated', strtotime('2025-01-31 08:30:00 UTC'), ['customcertid' => $customcertid]);

        // Relative to the issue date, with the end of month clamped.
        $this->assertSame(
            (int)strtotime('2025-02-28 08:30:00 UTC'),
            element::get_expiry_date($customcertid, (int)$student->id)
        );
        $this->assertStringContainsString('customcertelement-expiry', element::get_expiry_html($customcertid, (int)$student->id));

        // Relative to course completion, which is missing.
        $payload = $this->custom_payload(1, 'months', ['startfrom' => 'coursecomplete']);
        $DB->set_field('customcert_elements', 'data', json_encode($payload), ['id' => $elementid]);
        $this->assertSame(0, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame('', element::get_expiry_html($customcertid, (int)$student->id));

        // Relative to course completion, once completed.
        $DB->insert_record('course_completions', (object)[
            'userid' => $student->id,
            'course' => $courseid,
            'timecompleted' => strtotime('2024-01-31 12:00:00 UTC'),
        ]);
        $this->assertSame(
            (int)strtotime('2024-02-29 12:00:00 UTC'),
            element::get_expiry_date($customcertid, (int)$student->id)
        );
    }

    /**
     * Test the report calculation for existing 1-5 year configurations is unchanged.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_expiry_reporting_legacy_option(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [, $customcertid] = $this->create_customcert_setup(
            ['dateitem' => '-10', 'dateformat' => 'strftimedate', 'startfrom' => 'award']
        );
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $DB->get_field('customcert', 'course', ['id' => $customcertid]));
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        $DB->set_field('customcert_issues', 'timecreated', strtotime('2025-01-31 08:30:00 UTC'), ['customcertid' => $customcertid]);

        $this->assertSame(
            (int)strtotime('2028-01-31 08:30:00 UTC'),
            element::get_expiry_date($customcertid, (int)$student->id)
        );
    }

    /**
     * Test the edit form loads saved custom interval values into its fields.
     *
     * @covers \customcertelement_expiry\element::prepare_form
     */
    public function test_prepare_form_loads_saved_custom_interval(): void {
        $mform = new MoodleQuickForm('expiryform', 'post', '');
        $mform->addElement('select', 'dateitem', 'dateitem', ['-8' => 'one', 'custom' => 'custom']);
        $mform->addElement('select', 'dateformat', 'dateformat', ['strftimedate' => 'date']);
        $mform->addElement('select', 'startfrom', 'startfrom', ['award' => 'award']);
        $mform->addElement('text', 'expiryvalue', 'expiryvalue');
        $mform->addElement('select', 'expiryunit', 'expiryunit', ['months' => 'months', 'years' => 'years']);

        $el = new element($this->make_record(['data' => json_encode($this->custom_payload(6, 'months'))]));
        $el->prepare_form($mform);

        $this->assertEquals(6, $mform->getElement('expiryvalue')->getValue());
        $this->assertSame(['months'], $mform->getElement('expiryunit')->getValue());
        $this->assertSame(['custom'], $mform->getElement('dateitem')->getValue());
    }

    /**
     * Standard visual payload values stored with every element.
     *
     * @return array
     */
    private function visual_payload(): array {
        return ['font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0];
    }

    /**
     * Build an exporter with mocked collaborators.
     *
     * @param template_import_logger_interface|null $logger
     * @return exporter
     */
    private function make_exporter(?template_import_logger_interface $logger = null): exporter {
        return new exporter(
            'expiry',
            $logger ?? $this->createMock(template_import_logger_interface::class),
            $this->createMock(template_appendix_manager_interface::class)
        );
    }

    /**
     * Test a custom interval survives export and import.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_exporter_round_trip_custom_interval(): void {
        $logger = $this->createMock(template_import_logger_interface::class);
        $logger->expects($this->never())->method('warning');
        $exporter = $this->make_exporter($logger);

        $stored = json_encode($this->custom_payload(6, 'months') + $this->visual_payload());
        $exported = $exporter->export($stored);
        $this->assertSame(6, $exported['expiryvalue']['value']);
        $this->assertSame('months', $exported['expiryunit']['value']);

        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame('custom', $imported['dateitem']);
        $this->assertSame(6, $imported['expiryvalue']);
        $this->assertSame('months', $imported['expiryunit']);
    }

    /**
     * Test existing configurations export and import without warnings, and old exports still import.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_exporter_legacy_configuration(): void {
        $logger = $this->createMock(template_import_logger_interface::class);
        $logger->expects($this->never())->method('warning');
        $exporter = $this->make_exporter($logger);

        $stored = json_encode(['dateitem' => '-12', 'dateformat' => 'validfor', 'startfrom' => 'award'] + $this->visual_payload());
        $exported = $exporter->export($stored);
        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame('-12', $imported['dateitem']);

        // An export created before the custom interval existed.
        unset($exported['expiryvalue'], $exported['expiryunit']);
        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame('-12', $imported['dateitem']);
        $this->assertSame('validfor', $imported['dateformat']);
    }

    /**
     * Test an invalid imported duration falls back to a valid value with a warning.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_exporter_invalid_interval_uses_fallback(): void {
        $logger = $this->createMock(template_import_logger_interface::class);
        $logger->expects($this->once())->method('warning');
        $exporter = $this->make_exporter($logger);

        $exported = $exporter->export(json_encode($this->custom_payload(6, 'months') + $this->visual_payload()));
        $exported['expiryvalue'] = ['value' => 0];
        $imported = json_decode($exporter->convert_for_import($exported), true);

        $this->assertSame(1, $imported['expiryvalue']);
    }

    /**
     * Test that get_type() returns 'expiry'.
     *
     * @covers \customcertelement_expiry\element
     */
    public function test_get_type(): void {
        $el = new element($this->make_record());
        $this->assertSame('expiry', $el->get_type());
    }
}

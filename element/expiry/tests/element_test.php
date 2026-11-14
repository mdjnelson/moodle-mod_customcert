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
    private function create_customcert_setup(): array {
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
        $rec->data = json_encode(['dateitem' => '-8', 'dateformat' => 'strftimedate', 'startfrom' => '-1']);
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
     * Create a certificate with an expiry element using the given payload and issue it to a new student.
     *
     * @param array $payload Element payload overrides.
     * @return array{element, int, int, stdClass} Element, customcert id, issue time and student record.
     */
    private function create_issued_expiry(array $payload): array {
        global $DB;

        [$elementid, $customcertid] = $this->create_customcert_setup();
        $DB->set_field('customcert_elements', 'data', json_encode($payload), ['id' => $elementid]);

        $student = $this->getDataGenerator()->create_user();
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        $issue = $DB->get_record('customcert_issues', ['userid' => $student->id, 'customcertid' => $customcertid]);

        $element = new element($DB->get_record('customcert_elements', ['id' => $elementid]));
        return [$element, $customcertid, (int)$issue->timecreated, $DB->get_record('user', ['id' => $student->id])];
    }

    /**
     * Render the element through a capturing renderer and return the content it was asked to render.
     *
     * @param element $element
     * @param stdClass $user
     * @param bool $html True to use render_html(), false for render().
     * @param bool $preview True when rendering a preview.
     * @return string|null Null if nothing was rendered.
     */
    private function capture_content(element $element, stdClass $user, bool $html = false, bool $preview = false): ?string {
        $captured = null;
        $renderer = $this->createMock(element_renderer::class);
        $renderer->method('render_content')->willReturnCallback(function ($el, string $content) use (&$captured) {
            $captured = $content;
            return $content;
        });

        if ($html) {
            $element->render_html($renderer);
        } else {
            $element->render(new pdf(), $preview, $user, $renderer);
        }

        return $captured;
    }

    /**
     * Data provider for add_interval().
     *
     * @return array
     */
    public static function add_interval_provider(): array {
        return [
            'days' => ['2025-01-15 10:30:00', 45, 'days', '2025-03-01 10:30:00'],
            'six months' => ['2025-01-15 10:30:00', 6, 'months', '2025-07-15 10:30:00'],
            'months across year end' => ['2025-11-20 08:00:00', 3, 'months', '2026-02-20 08:00:00'],
            'years' => ['2025-01-15 10:30:00', 2, 'years', '2027-01-15 10:30:00'],
            'end of month clamps to 28 Feb' => ['2025-01-31 12:00:00', 1, 'months', '2025-02-28 12:00:00'],
            'end of month clamps to 29 Feb in leap year' => ['2024-01-31 12:00:00', 1, 'months', '2024-02-29 12:00:00'],
            'end of month clamps to 30 day month' => ['2025-03-31 12:00:00', 1, 'months', '2025-04-30 12:00:00'],
            'leap day plus one year' => ['2024-02-29 12:00:00', 1, 'years', '2025-02-28 12:00:00'],
            'leap day plus four years' => ['2024-02-29 12:00:00', 4, 'years', '2028-02-29 12:00:00'],
            'days over leap day' => ['2024-02-28 12:00:00', 2, 'days', '2024-03-01 12:00:00'],
        ];
    }

    /**
     * Test calendar-based interval calculations.
     *
     * @dataProvider add_interval_provider
     * @covers \customcertelement_expiry\element::add_interval
     * @param string $start Start date and time (UTC).
     * @param int $value Interval value.
     * @param string $unit Interval unit.
     * @param string $expected Expected date and time (UTC).
     */
    public function test_add_interval(string $start, int $value, string $unit, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');

        $result = element::add_interval(strtotime($start . ' UTC'), $value, $unit);

        $this->assertSame($expected, gmdate('Y-m-d H:i:s', $result));
    }

    /**
     * Test the original 1-5 year options keep their existing behaviour for reporting.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_legacy_year_options_regression(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');

        $years = ['-8' => 1, '-9' => 2, '-10' => 3, '-11' => 4, '-12' => 5];
        foreach ($years as $dateitem => $count) {
            [, $customcertid, $issued, $student] = $this->create_issued_expiry([
                'dateitem' => $dateitem,
                'dateformat' => 'strftimedate',
                'startfrom' => 'award',
            ]);
            $this->assertSame(
                strtotime('+' . $count . ' year' . ($count > 1 ? 's' : ''), $issued),
                element::get_expiry_date($customcertid, (int)$student->id)
            );
        }
    }

    /**
     * Test the original options keep their "Valid for" text in both PDF and HTML rendering.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_legacy_validfor_text(): void {
        $this->resetAfterTest();

        [$element, , , $student] = $this->create_issued_expiry([
            'dateitem' => '-9',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
        ]);

        $this->assertSame('Valid for 2 years', $this->capture_content($element, $student));
        $this->assertSame('Valid for two years', $this->capture_content($element, $student, true));
    }

    /**
     * Test custom intervals relative to the certificate issue date, for reporting and PDF rendering.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::render
     */
    public function test_custom_interval_from_award_date(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');

        [$element, $customcertid, $issued, $student] = $this->create_issued_expiry([
            'dateitem' => 'custom',
            'intervalvalue' => 6,
            'intervalunit' => 'months',
            'dateformat' => 'strftimedatefullshort',
            'startfrom' => 'award',
        ]);

        $expected = element::add_interval($issued, 6, 'months');
        $this->assertSame($expected, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame(
            element_helper::get_date_format_string($expected, 'strftimedatefullshort'),
            $this->capture_content($element, $student)
        );
        $this->assertStringContainsString(
            userdate($expected, '%Y'),
            element::get_expiry_html($customcertid, (int)$student->id)
        );
    }

    /**
     * Test custom intervals relative to course completion, including a missing completion date.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::render
     */
    public function test_custom_interval_from_completion_date(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC');

        [$element, $customcertid, , $student] = $this->create_issued_expiry([
            'dateitem' => 'custom',
            'intervalvalue' => 90,
            'intervalunit' => 'days',
            'dateformat' => 'strftimedatefullshort',
            'startfrom' => 'coursecomplete',
        ]);

        // No completion yet: no expiry date and nothing rendered.
        $this->assertSame(0, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame('', element::get_expiry_html($customcertid, (int)$student->id));
        $this->assertNull($this->capture_content($element, $student));

        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $completed = strtotime('2025-01-31 09:00:00 UTC');
        $DB->insert_record('course_completions', (object)[
            'userid' => $student->id,
            'course' => $courseid,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => $completed,
        ]);

        $expected = element::add_interval($completed, 90, 'days');
        $this->assertSame('2025-05-01', gmdate('Y-m-d', $expected));
        $this->assertSame($expected, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame(
            element_helper::get_date_format_string($expected, 'strftimedatefullshort'),
            $this->capture_content($element, $student)
        );
    }

    /**
     * Test previews calculate the custom interval from now.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_custom_interval_preview(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');

        $payload = [
            'dateitem' => 'custom',
            'intervalvalue' => 1,
            'intervalunit' => 'years',
            'dateformat' => 'strftimedatefullshort',
            'startfrom' => 'coursecomplete',
        ];
        $element = new element($this->make_record(['data' => json_encode($payload)]));
        $user = (object)['id' => 0];

        $before = element::add_interval(time(), 1, 'years');
        $expected = element_helper::get_date_format_string($before, 'strftimedatefullshort');

        $this->assertSame($expected, $this->capture_content($element, $user, false, true));
        $this->assertSame($expected, $this->capture_content($element, $user, true));
    }

    /**
     * Data provider for the custom "Valid for" text.
     *
     * @return array
     */
    public static function custom_validfor_provider(): array {
        return [
            'one day' => [1, 'days', 'Valid for 1 day'],
            'many days' => [30, 'days', 'Valid for 30 days'],
            'one month' => [1, 'months', 'Valid for 1 month'],
            'six months' => [6, 'months', 'Valid for 6 months'],
            'one year' => [1, 'years', 'Valid for 1 year'],
            'many years' => [10, 'years', 'Valid for 10 years'],
        ];
    }

    /**
     * Test the "Valid for" text for custom intervals in PDF and HTML rendering.
     *
     * @dataProvider custom_validfor_provider
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     * @param int $value Interval value.
     * @param string $unit Interval unit.
     * @param string $expected Expected text.
     */
    public function test_custom_validfor_text(int $value, string $unit, string $expected): void {
        $this->resetAfterTest();

        [$element, , , $student] = $this->create_issued_expiry([
            'dateitem' => 'custom',
            'intervalvalue' => $value,
            'intervalunit' => $unit,
            'dateformat' => 'validfor',
            'startfrom' => 'award',
        ]);

        $this->assertSame($expected, $this->capture_content($element, $student));
        $this->assertSame($expected, $this->capture_content($element, $student, true));
    }

    /**
     * Data provider for invalid stored configurations.
     *
     * @return array
     */
    public static function invalid_configuration_provider(): array {
        return [
            'missing value' => [['intervalunit' => 'days']],
            'missing unit' => [['intervalvalue' => 3]],
            'zero value' => [['intervalvalue' => 0, 'intervalunit' => 'days']],
            'negative value' => [['intervalvalue' => -2, 'intervalunit' => 'months']],
            'non numeric value' => [['intervalvalue' => 'abc', 'intervalunit' => 'months']],
            'fractional value' => [['intervalvalue' => '1.5', 'intervalunit' => 'years']],
            'too large value' => [['intervalvalue' => 101, 'intervalunit' => 'years']],
            'unknown unit' => [['intervalvalue' => 3, 'intervalunit' => 'weeks']],
        ];
    }

    /**
     * Test invalid configurations are handled safely and render nothing.
     *
     * @dataProvider invalid_configuration_provider
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @param array $interval Invalid interval settings.
     */
    public function test_invalid_configuration_is_safe(array $interval): void {
        $this->resetAfterTest();

        foreach (['strftimedatefullshort', 'validfor'] as $format) {
            [$element, $customcertid, , $student] = $this->create_issued_expiry([
                'dateitem' => 'custom',
                'dateformat' => $format,
                'startfrom' => 'award',
            ] + $interval);

            $this->assertSame(0, element::get_expiry_date($customcertid, (int)$student->id));
            $this->assertSame('', element::get_expiry_html($customcertid, (int)$student->id));
            $this->assertNull($this->capture_content($element, $student));
            $this->assertNull($this->capture_content($element, $student, true));
            $this->assertSame('', $element->render_html());
        }
    }

    /**
     * Test an unrecognised dateitem is handled safely.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_unknown_dateitem_is_safe(): void {
        $element = new element($this->make_record([
            'data' => json_encode(['dateitem' => '-99', 'dateformat' => 'strftimedate', 'startfrom' => 'award']),
        ]));

        $this->assertSame('', $element->render_html());
    }

    /**
     * Test validation of the custom interval.
     *
     * @covers \customcertelement_expiry\element::validate
     */
    public function test_validate(): void {
        $this->resetAfterTest();
        $el = new element($this->make_record());

        $this->assertSame([], $el->validate(['dateitem' => '-8']));
        $this->assertSame([], $el->validate(['dateitem' => 'custom', 'intervalvalue' => '6', 'intervalunit' => 'months']));
        $this->assertSame([], $el->validate(['dateitem' => 'custom', 'intervalvalue' => 100, 'intervalunit' => 'years']));

        $this->assertArrayHasKey('intervalvalue', $el->validate(['dateitem' => 'custom', 'intervalunit' => 'days']));
        $this->assertArrayHasKey(
            'intervalvalue',
            $el->validate(['dateitem' => 'custom', 'intervalvalue' => '0', 'intervalunit' => 'days'])
        );
        $this->assertArrayHasKey(
            'intervalvalue',
            $el->validate(['dateitem' => 'custom', 'intervalvalue' => '101', 'intervalunit' => 'years'])
        );
        $this->assertArrayHasKey('intervalunit', $el->validate(['dateitem' => 'custom', 'intervalvalue' => 3]));
        $this->assertArrayHasKey(
            'intervalunit',
            $el->validate(['dateitem' => 'custom', 'intervalvalue' => 3, 'intervalunit' => 'weeks'])
        );
    }

    /**
     * Test the custom interval is saved in the payload while legacy payloads are unchanged.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_normalise_data_custom_interval(): void {
        $el = new element($this->make_record());

        $custom = $el->normalise_data((object)[
            'dateitem' => 'custom',
            'intervalvalue' => '6',
            'intervalunit' => 'months',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
        ]);
        $this->assertSame('custom', $custom['dateitem']);
        $this->assertSame(6, $custom['intervalvalue']);
        $this->assertSame('months', $custom['intervalunit']);

        // The interval fields are not stored for the original options, even if submitted.
        $legacy = $el->normalise_data((object)['dateitem' => '-8', 'intervalvalue' => '6', 'intervalunit' => 'months']);
        $this->assertArrayNotHasKey('intervalvalue', $legacy);
        $this->assertArrayNotHasKey('intervalunit', $legacy);
    }

    /**
     * Test the edit form offers the custom option and populates stored interval values.
     *
     * @covers \customcertelement_expiry\element::build_form
     * @covers \customcertelement_expiry\element::prepare_form
     */
    public function test_form_builds_and_prepares_custom_interval(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/mod/customcert/includes/colourpicker.php');
        \MoodleQuickForm::registerElementType(
            'customcert_colourpicker',
            $CFG->dirroot . '/mod/customcert/includes/colourpicker.php',
            'MoodleQuickForm_customcert_colourpicker'
        );

        $el = new element($this->make_record([
            'data' => json_encode([
                'dateitem' => 'custom',
                'intervalvalue' => 6,
                'intervalunit' => 'months',
                'dateformat' => 'validfor',
                'startfrom' => 'award',
            ]),
        ]));
        $mform = new \MoodleQuickForm('expiry_test', 'post', '');
        $el->build_form($mform);
        $el->prepare_form($mform);

        $options = array_column($mform->getElement('dateitem')->_options, 'attr', 'text');
        $this->assertCount(6, $options);
        $this->assertSame('custom', $mform->getElement('dateitem')->getValue()[0]);
        $this->assertSame(6, $mform->getElement('intervalvalue')->getValue());
        $this->assertSame('months', $mform->getElement('intervalunit')->getValue()[0]);
    }

    /**
     * Create the expiry exporter with mocked collaborators.
     *
     * @return exporter
     */
    private function make_exporter(): exporter {
        return new exporter(
            'expiry',
            $this->createMock(template_import_logger_interface::class),
            $this->createMock(template_appendix_manager_interface::class)
        );
    }

    /**
     * Test custom interval settings survive template export and import.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_export_import_custom_interval(): void {
        $exporter = $this->make_exporter();
        $data = json_encode([
            'dateitem' => 'custom',
            'intervalvalue' => 6,
            'intervalunit' => 'months',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'font' => 'times',
            'fontsize' => 12,
            'colour' => '#000000',
            'width' => 0,
        ]);

        $imported = json_decode($exporter->convert_for_import($exporter->export($data)), true);

        $this->assertSame('custom', $imported['dateitem']);
        $this->assertSame(6, $imported['intervalvalue']);
        $this->assertSame('months', $imported['intervalunit']);
    }

    /**
     * Test original options and exports created before custom intervals existed still import.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_import_legacy_export_without_interval_fields(): void {
        $exporter = $this->make_exporter();
        $exported = [
            'dateitem' => ['value' => '-10'],
            'dateformat' => ['value' => 'validfor'],
            'startfrom' => ['value' => 'coursecomplete'],
            'font' => ['value' => 'times'],
            'fontsize' => ['value' => 12],
            'colour' => ['value' => '#000000'],
            'width' => ['value' => 0],
        ];

        $imported = json_decode($exporter->convert_for_import($exported), true);

        $this->assertSame('-10', $imported['dateitem']);
        $this->assertSame('coursecomplete', $imported['startfrom']);
        $this->assertArrayNotHasKey('intervalvalue', $imported);
        $this->assertArrayNotHasKey('intervalunit', $imported);
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

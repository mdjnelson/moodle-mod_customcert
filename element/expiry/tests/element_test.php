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
    private function create_customcert_setup(
        array $payload = ['dateitem' => '-8', 'dateformat' => 'strftimedate', 'startfrom' => '-1']
    ): array {
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
        $rec->data = json_encode($payload);
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
     * Data provider for the original 1-5 year options.
     *
     * @return array
     */
    public static function legacy_interval_provider(): array {
        return [
            'one year' => ['-8', '+1 year'],
            'two years' => ['-9', '+2 years'],
            'three years' => ['-10', '+3 years'],
            'four years' => ['-11', '+4 years'],
            'five years' => ['-12', '+5 years'],
        ];
    }

    /**
     * Test the original 1-5 year options keep their strtotime() based results.
     *
     * @dataProvider legacy_interval_provider
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @param string $dateitem
     * @param string $relative
     */
    public function test_calculate_expiry_time_legacy_options(string $dateitem, string $relative): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $start = mktime(10, 30, 0, 2, 29, 2024);
        $this->assertSame(
            strtotime($relative, $start),
            element::calculate_expiry_time($start, ['dateitem' => $dateitem])
        );
    }

    /**
     * Data provider for custom interval calculations.
     *
     * @return array
     */
    public static function custom_interval_provider(): array {
        return [
            'days' => [[10, 'days'], '2025-01-01 08:00:00', '2025-01-11 08:00:00'],
            'days across leap day' => [[2, 'days'], '2024-02-28 08:00:00', '2024-03-01 08:00:00'],
            'months' => [[3, 'months'], '2025-01-15 08:00:00', '2025-04-15 08:00:00'],
            'six months' => [[6, 'months'], '2025-03-10 08:00:00', '2025-09-10 08:00:00'],
            'six months over year end' => [[6, 'months'], '2025-09-10 08:00:00', '2026-03-10 08:00:00'],
            'years' => [[2, 'years'], '2025-05-05 08:00:00', '2027-05-05 08:00:00'],
            'end of month clamped' => [[1, 'months'], '2025-01-31 08:00:00', '2025-02-28 08:00:00'],
            'end of month clamped in leap year' => [[1, 'months'], '2024-01-31 08:00:00', '2024-02-29 08:00:00'],
            'six months from 31 August' => [[6, 'months'], '2025-08-31 08:00:00', '2026-02-28 08:00:00'],
            'thirty one day month' => [[1, 'months'], '2025-03-31 08:00:00', '2025-04-30 08:00:00'],
            'leap day plus one year' => [[1, 'years'], '2024-02-29 08:00:00', '2025-02-28 08:00:00'],
            'leap day plus four years' => [[4, 'years'], '2024-02-29 08:00:00', '2028-02-29 08:00:00'],
            'twelve months' => [[12, 'months'], '2025-06-30 08:00:00', '2026-06-30 08:00:00'],
        ];
    }

    /**
     * Test custom interval calculations.
     *
     * @dataProvider custom_interval_provider
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @param array $interval Value and unit
     * @param string $start
     * @param string $expected
     */
    public function test_calculate_expiry_time_custom(array $interval, string $start, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $payload = [
            'dateitem' => element::EXPIRY_CUSTOM,
            'intervalvalue' => $interval[0],
            'intervalunit' => $interval[1],
        ];
        $result = element::calculate_expiry_time(strtotime($start . ' UTC'), $payload);
        $this->assertSame($expected, gmdate('Y-m-d H:i:s', $result));
    }

    /**
     * Data provider for invalid configurations.
     *
     * @return array
     */
    public static function invalid_payload_provider(): array {
        $custom = element::EXPIRY_CUSTOM;
        return [
            'missing dateitem' => [[]],
            'unknown dateitem' => [['dateitem' => '-99']],
            'missing value and unit' => [['dateitem' => $custom]],
            'missing unit' => [['dateitem' => $custom, 'intervalvalue' => 3]],
            'missing value' => [['dateitem' => $custom, 'intervalunit' => 'days']],
            'zero value' => [['dateitem' => $custom, 'intervalvalue' => 0, 'intervalunit' => 'days']],
            'negative value' => [['dateitem' => $custom, 'intervalvalue' => -5, 'intervalunit' => 'days']],
            'non numeric value' => [['dateitem' => $custom, 'intervalvalue' => 'abc', 'intervalunit' => 'days']],
            'fractional value' => [['dateitem' => $custom, 'intervalvalue' => 1.5, 'intervalunit' => 'days']],
            'too large value' => [['dateitem' => $custom, 'intervalvalue' => 101, 'intervalunit' => 'years']],
            'invalid unit' => [['dateitem' => $custom, 'intervalvalue' => 3, 'intervalunit' => 'weeks']],
        ];
    }

    /**
     * Test invalid configurations are handled safely.
     *
     * @dataProvider invalid_payload_provider
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @param array $payload
     */
    public function test_calculate_expiry_time_invalid_payload(array $payload): void {
        $this->assertSame(0, element::calculate_expiry_time(time(), $payload));
    }

    /**
     * Test normalise_data() only stores interval settings for custom intervals.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_normalise_data_custom_interval(): void {
        $el = new element($this->make_record());

        $result = $el->normalise_data((object) [
            'dateitem' => element::EXPIRY_CUSTOM,
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'intervalvalue' => '6',
            'intervalunit' => 'months',
        ]);
        $this->assertSame('-13', $result['dateitem']);
        $this->assertSame(6, $result['intervalvalue']);
        $this->assertSame('months', $result['intervalunit']);

        $legacy = $el->normalise_data((object) [
            'dateitem' => '-8',
            'dateformat' => 'strftimedate',
            'startfrom' => 'award',
            'intervalvalue' => '6',
            'intervalunit' => 'months',
        ]);
        $this->assertArrayNotHasKey('intervalvalue', $legacy);
        $this->assertArrayNotHasKey('intervalunit', $legacy);
    }

    /**
     * Test validate().
     *
     * @covers \customcertelement_expiry\element::validate
     */
    public function test_validate(): void {
        $el = new element($this->make_record());

        $this->assertSame([], $el->validate(['dateitem' => '-8']));
        $this->assertSame([], $el->validate(['dateitem' => '-8', 'intervalvalue' => 0, 'intervalunit' => 'bogus']));
        $this->assertSame([], $el->validate(['dateitem' => '-13', 'intervalvalue' => 6, 'intervalunit' => 'months']));
        $this->assertSame([], $el->validate(['dateitem' => '-13', 'intervalvalue' => '6', 'intervalunit' => 'days']));

        $invalidvalues = [0, -1, 'x', null, 101];
        foreach ($invalidvalues as $value) {
            $data = ['dateitem' => '-13', 'intervalvalue' => $value, 'intervalunit' => 'years'];
            $this->assertArrayHasKey('intervalvalue', $el->validate($data), var_export($value, true));
        }

        $data = ['dateitem' => '-13', 'intervalvalue' => 3, 'intervalunit' => 'weeks'];
        $this->assertArrayHasKey('intervalunit', $el->validate($data));
        $this->assertArrayHasKey('intervalunit', $el->validate(['dateitem' => '-13', 'intervalvalue' => 3]));
    }

    /**
     * Test the form offers the custom option and is populated from saved data.
     *
     * @covers \customcertelement_expiry\element::build_form
     * @covers \customcertelement_expiry\element::prepare_form
     */
    public function test_form_for_custom_interval(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $payload = ['dateitem' => '-13', 'dateformat' => 'validfor', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months'];
        $el = new element($this->make_record(['data' => json_encode($payload)]));

        $mform = new MoodleQuickForm('expirytest', 'post', '');
        $el->build_form($mform);
        $el->prepare_form($mform);

        $this->assertEquals(6, $mform->getElement('intervalvalue')->getValue());
        $this->assertSame(['months'], $mform->getElement('intervalunit')->getValue());
        $this->assertSame(['-13'], $mform->getElement('dateitem')->getValue());
    }

    /**
     * Test the "Valid for" text shown in the designer preview for custom intervals.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_custom_validfor(): void {
        $this->resetAfterTest();

        $base = ['dateitem' => '-13', 'dateformat' => 'validfor', 'startfrom' => 'award'];
        $cases = [
            [1, 'days', 'Valid for 1 day'],
            [10, 'days', 'Valid for 10 days'],
            [1, 'months', 'Valid for 1 month'],
            [6, 'months', 'Valid for 6 months'],
            [1, 'years', 'Valid for 1 year'],
            [3, 'years', 'Valid for 3 years'],
        ];
        foreach ($cases as [$value, $unit, $expected]) {
            $payload = $base + ['intervalvalue' => $value, 'intervalunit' => $unit];
            $el = new element($this->make_record(['data' => json_encode($payload)]));
            $this->assertStringContainsString($expected, $el->render_html());
        }
    }

    /**
     * Test the designer preview for a custom interval shown as a date, and invalid configuration.
     *
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_render_html_custom_date_and_invalid(): void {
        $this->resetAfterTest();

        $payload = ['dateitem' => '-13', 'dateformat' => 'strftimedate', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months'];
        $el = new element($this->make_record(['data' => json_encode($payload)]));
        $this->assertNotEmpty($el->render_html());

        $payload['intervalunit'] = 'weeks';
        $el = new element($this->make_record(['data' => json_encode($payload)]));
        $this->assertSame('', $el->render_html());

        $payload['dateformat'] = 'validfor';
        $el = new element($this->make_record(['data' => json_encode($payload)]));
        $this->assertSame('', $el->render_html());
    }

    /**
     * Test PDF rendering, preview rendering and reporting for a custom interval from the award date.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::get_expiry_html
     * @covers \customcertelement_expiry\element::has_expiry
     */
    public function test_custom_interval_from_award_date(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [$elementid, $customcertid] = $this->create_customcert_setup([
            'dateitem' => '-13',
            'dateformat' => 'strftimedate',
            'startfrom' => 'award',
            'intervalvalue' => 6,
            'intervalunit' => 'months',
        ]);
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $issuetime = strtotime('2025-08-31 12:00:00 UTC');
        $DB->set_field('customcert_issues', 'timecreated', $issuetime, ['customcertid' => $customcertid]);

        $this->assertTrue(element::has_expiry($customcertid));
        $expected = strtotime('2026-02-28 12:00:00 UTC');
        $this->assertSame($expected, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertStringContainsString(userdate($expected), element::get_expiry_html($customcertid, (int)$student->id));

        $el = new element($DB->get_record('customcert_elements', ['id' => $elementid]));
        $user = $DB->get_record('user', ['id' => $student->id]);

        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);
        $el->render($pdf, true, $user);

        $this->assertNotEmpty($el->render_html());
    }

    /**
     * Test a custom interval relative to course completion, including a missing completion date.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     * @covers \customcertelement_expiry\element::render
     */
    public function test_custom_interval_from_course_completion(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [$elementid, $customcertid] = $this->create_customcert_setup([
            'dateitem' => '-13',
            'dateformat' => 'strftimedate',
            'startfrom' => 'coursecomplete',
            'intervalvalue' => 30,
            'intervalunit' => 'days',
        ]);
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $el = new element($DB->get_record('customcert_elements', ['id' => $elementid]));
        $user = $DB->get_record('user', ['id' => $student->id]);

        // No completion date: no expiry and rendering does not fail.
        $this->assertSame(0, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame('', element::get_expiry_html($customcertid, (int)$student->id));
        $pdf = new pdf();
        $pdf->AddPage();
        $el->render($pdf, false, $user);

        $completed = strtotime('2025-01-15 09:00:00 UTC');
        $DB->insert_record('course_completions', (object)[
            'userid' => $student->id,
            'course' => $courseid,
            'timeenrolled' => $completed - DAYSECS,
            'timestarted' => $completed - DAYSECS,
            'timecompleted' => $completed,
        ]);

        $this->assertSame($completed + (30 * DAYSECS), element::get_expiry_date($customcertid, (int)$student->id));
        $el->render($pdf, false, $user);
    }

    /**
     * Test legacy 1-5 year configurations still report the same expiry date.
     *
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_legacy_option_reporting(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [, $customcertid] = $this->create_customcert_setup(
            ['dateitem' => '-10', 'dateformat' => 'strftimedate', 'startfrom' => 'award']
        );
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);

        $issuetime = strtotime('2024-03-10 12:00:00 UTC');
        $DB->set_field('customcert_issues', 'timecreated', $issuetime, ['customcertid' => $customcertid]);

        $this->assertSame(
            strtotime('+3 years', $issuetime),
            element::get_expiry_date($customcertid, (int)$student->id)
        );
    }

    /**
     * Build an exporter for the import/export tests.
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
     * Test a custom interval survives a template export and import.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_export_import_custom_interval(): void {
        $exporter = $this->make_exporter();
        $data = json_encode(['dateitem' => '-13', 'dateformat' => 'validfor', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months', 'font' => 'times', 'fontsize' => 12,
            'colour' => '#000000', 'width' => 0]);

        $exported = $exporter->export($data);
        $this->assertSame(6, $exported['intervalvalue']['value']);
        $this->assertSame('months', $exported['intervalunit']['value']);

        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame('-13', $imported['dateitem']);
        $this->assertSame(6, $imported['intervalvalue']);
        $this->assertSame('months', $imported['intervalunit']);
    }

    /**
     * Test legacy configurations, and exports created before custom intervals, still import unchanged.
     *
     * @covers \customcertelement_expiry\exporter
     */
    public function test_export_import_legacy_interval(): void {
        $exporter = $this->make_exporter();
        $data = json_encode(['dateitem' => '-9', 'dateformat' => 'strftimedate', 'startfrom' => 'award',
            'font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0]);

        $exported = $exporter->export($data);
        $this->assertArrayNotHasKey('intervalvalue', $exported);
        $this->assertArrayNotHasKey('intervalunit', $exported);

        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame('-9', $imported['dateitem']);
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

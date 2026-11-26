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
use mod_customcert\edit_element_form;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\export\datatypes\format_error;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;
use MoodleQuickForm;
use DateTimeImmutable;
use DateTimeZone;
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
     * @param array $payload The element payload.
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
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/mod/customcert/includes/colourpicker.php');
        MoodleQuickForm::registerElementType(
            'customcert_colourpicker',
            $CFG->dirroot . '/mod/customcert/includes/colourpicker.php',
            'MoodleQuickForm_customcert_colourpicker'
        );

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
     * Custom payload helper.
     *
     * @param mixed $value
     * @param mixed $unit
     * @return array
     */
    private static function custom_payload(mixed $value, mixed $unit): array {
        return ['dateitem' => element::EXPIRY_CUSTOM, 'intervalvalue' => $value, 'intervalunit' => $unit];
    }

    /**
     * Expiry timestamps use the server timezone and never the viewing user's timezone.
     *
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     */
    public function test_calculate_expiry_time_ignores_viewing_user_timezone(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');

        // 23:30 UTC on 31 January is already 1 February in Auckland, but still 31 January in London.
        $start = strtotime('2025-01-31 23:30:00 UTC');
        $payloads = [
            self::custom_payload(1, 'months'),
            self::custom_payload(1, 'years'),
            self::custom_payload(1, 'days'),
            ['dateitem' => '-8'],
        ];

        $results = [];
        foreach (['Pacific/Auckland', 'America/Los_Angeles', 'UTC', 'Europe/London'] as $timezone) {
            $this->setUser($this->getDataGenerator()->create_user(['timezone' => $timezone]));
            foreach ($payloads as $key => $payload) {
                $results[$key][] = element::calculate_expiry_time($start, $payload);
            }
        }
        $this->setUser(null);
        foreach ($payloads as $key => $payload) {
            $results[$key][] = element::calculate_expiry_time($start, $payload);
            $this->assertCount(1, array_unique($results[$key]), 'Payload ' . $key);
        }

        $this->assertSame(strtotime('2025-02-28 23:30:00 UTC'), $results[0][0]);
    }

    /**
     * The site's server timezone decides which calendar day the start time falls on.
     *
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     */
    public function test_calculate_expiry_time_uses_server_timezone(): void {
        $this->resetAfterTest();

        // 23:30 UTC on 31 January is 12:30 on 1 February in Auckland (NZDT).
        $start = strtotime('2025-01-31 23:30:00 UTC');
        $payload = self::custom_payload(1, 'months');

        $this->setTimezone('Pacific/Auckland', 'Pacific/Auckland');
        $this->assertSame(strtotime('2025-03-01 12:30:00 NZDT'), element::calculate_expiry_time($start, $payload));

        $this->setTimezone('UTC', 'Pacific/Auckland');
        $this->assertSame(strtotime('2025-02-28 23:30:00 UTC'), element::calculate_expiry_time($start, $payload));
    }

    /**
     * Data provider for Europe/London calculations, as local start, interval and local expected expiry.
     *
     * @return array
     */
    public static function london_interval_provider(): array {
        return [
            'day across spring forward' => [[1, 'days'], '2025-03-29 12:00:00', '2025-03-30 12:00:00'],
            'day across autumn back' => [[1, 'days'], '2025-10-25 12:00:00', '2025-10-26 12:00:00'],
            'month across spring forward' => [[1, 'months'], '2025-03-15 09:00:00', '2025-04-15 09:00:00'],
            'six months across autumn back' => [[6, 'months'], '2025-05-31 18:00:00', '2025-11-30 18:00:00'],
            'year across both' => [[1, 'years'], '2025-03-30 12:00:00', '2026-03-30 12:00:00'],
            'summer start into winter month end' => [[4, 'months'], '2025-06-30 23:30:00', '2025-10-30 23:30:00'],
            'summer 31 August plus six months' => [[6, 'months'], '2025-08-31 23:30:00', '2026-02-28 23:30:00'],
            'leap day plus one year' => [[1, 'years'], '2024-02-29 23:30:00', '2025-02-28 23:30:00'],
            'leap day plus four years' => [[4, 'years'], '2024-02-29 00:30:00', '2028-02-29 00:30:00'],
            'january 31 plus one month in leap year' => [[1, 'months'], '2024-01-31 00:30:00', '2024-02-29 00:30:00'],
            'day count across spring forward' => [[30, 'days'], '2025-03-15 12:00:00', '2025-04-14 12:00:00'],
        ];
    }

    /**
     * Custom intervals keep the local time of day across daylight saving changes and calendar boundaries.
     *
     * @dataProvider london_interval_provider
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @param array $interval Value and unit
     * @param string $start Local start time
     * @param string $expected Local expected expiry time
     */
    public function test_calculate_expiry_time_europe_london(array $interval, string $start, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');
        $london = new DateTimeZone('Europe/London');

        $startts = (new DateTimeImmutable($start, $london))->getTimestamp();
        $result = element::calculate_expiry_time($startts, self::custom_payload($interval[0], $interval[1]));

        $this->assertSame($expected, (new DateTimeImmutable('@' . $result))->setTimezone($london)->format('Y-m-d H:i:s'));
    }

    /**
     * Data provider for the accepted and rejected interval boundaries.
     *
     * @return array
     */
    public static function interval_boundary_provider(): array {
        return [
            'days minimum' => [1, 'days', true],
            'days maximum' => [36500, 'days', true],
            'days below minimum' => [0, 'days', false],
            'days above maximum' => [36501, 'days', false],
            'months minimum' => [1, 'months', true],
            'months maximum' => [1200, 'months', true],
            'months below minimum' => [0, 'months', false],
            'months above maximum' => [1201, 'months', false],
            'years minimum' => [1, 'years', true],
            'years maximum' => [100, 'years', true],
            'years below minimum' => [0, 'years', false],
            'years above maximum' => [101, 'years', false],
            'negative' => [-1, 'days', false],
            'string digits' => ['12', 'months', true],
            'integral float' => [12.0, 'months', true],
            'fraction' => ['1.5', 'days', false],
            'float fraction' => [1.5, 'days', false],
            'exponent' => ['1e1', 'days', false],
            'text' => ['abc', 'days', false],
            'empty' => ['', 'days', false],
            'null' => [null, 'days', false],
            'boolean' => [true, 'days', false],
            'array' => [[1], 'days', false],
            'invalid unit' => [1, 'weeks', false],
            'missing unit' => [1, null, false],
        ];
    }

    /**
     * Form validation, import validation and calculation all apply the same interval rules.
     *
     * @dataProvider interval_boundary_provider
     * @covers \customcertelement_expiry\element::validate_interval
     * @covers \customcertelement_expiry\element::validate
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @covers \customcertelement_expiry\exporter::convert_for_import
     * @param mixed $value
     * @param mixed $unit
     * @param bool $valid
     */
    public function test_interval_rules_are_shared(mixed $value, mixed $unit, bool $valid): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $this->assertSame($valid, element::validate_interval($value, $unit) === []);

        $el = new element($this->make_record());
        $data = ['dateitem' => element::EXPIRY_CUSTOM, 'intervalvalue' => $value, 'intervalunit' => $unit];
        $this->assertSame($valid, $el->validate($data) === []);

        $this->assertSame($valid, element::calculate_expiry_time(time(), self::custom_payload($value, $unit)) > 0);

        $import = [
            'dateitem' => ['value' => element::EXPIRY_CUSTOM],
            'dateformat' => ['value' => 'validfor'],
            'startfrom' => ['value' => 'award'],
            'font' => ['value' => 'times'],
            'fontsize' => ['value' => 12],
            'colour' => ['value' => '#000000'],
            'width' => ['value' => 0],
            'intervalvalue' => ['value' => $value],
            'intervalunit' => ['value' => $unit],
        ];
        if ($valid) {
            $imported = json_decode($this->make_exporter()->convert_for_import($import), true);
            $this->assertSame((int)$value, $imported['intervalvalue']);
            $this->assertSame($unit, $imported['intervalunit']);
        } else {
            $this->expectException(format_error::class);
            $this->make_exporter()->convert_for_import($import);
        }
    }

    /**
     * Validation errors are reported against the matching field and are localised.
     *
     * @covers \customcertelement_expiry\element::validate_interval
     */
    public function test_interval_errors_per_field(): void {
        $errors = element::validate_interval(101, 'years');
        $this->assertSame(['intervalvalue'], array_keys($errors));
        $this->assertSame(get_string('errorinvalidinterval', 'customcertelement_expiry', 100), $errors['intervalvalue']);

        $errors = element::validate_interval(1, 'weeks');
        $this->assertSame(['intervalunit'], array_keys($errors));
        $this->assertSame(get_string('errorinvalidunit', 'customcertelement_expiry'), $errors['intervalunit']);
    }

    /**
     * Submit the real edit element form with a custom interval.
     *
     * @param mixed $value Submitted interval length
     * @param string $unit Submitted interval unit
     * @return edit_element_form
     */
    private function submit_interval_form(mixed $value, string $unit = 'months'): edit_element_form {
        $this->setAdminUser();

        edit_element_form::mock_submit([
            'name' => 'Expiry',
            'dateitem' => element::EXPIRY_CUSTOM,
            'intervalvalue' => $value,
            'intervalunit' => $unit,
            'dateformat' => 'validfor',
            'startfrom' => 'award',
            'font' => 'times',
            'fontsize' => 12,
            'colour' => '#000000',
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
            'refpoint' => 0,
            'alignment' => 'L',
        ]);

        return new edit_element_form(null, ['element' => $this->make_record()]);
    }

    /**
     * Fractional and non-numeric input fails the real form submission rather than being truncated.
     *
     * @covers \customcertelement_expiry\element::build_form
     * @covers \customcertelement_expiry\element::validate
     */
    public function test_form_submission_rejects_invalid_intervals(): void {
        $this->resetAfterTest();

        foreach (['1.5', '0.9', 'abc', '0', '-1', '1e1', '101', ''] as $value) {
            $form = $this->submit_interval_form($value, 'years');
            $this->assertFalse($form->is_validated(), var_export($value, true));
            $this->assertNull($form->get_data(), var_export($value, true));
        }
    }

    /**
     * A valid interval passes the real form submission and is stored as an integer.
     *
     * @covers \customcertelement_expiry\element::build_form
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_form_submission_accepts_valid_interval(): void {
        $this->resetAfterTest();

        $form = $this->submit_interval_form('100', 'years');
        $this->assertTrue($form->is_validated());

        $payload = (new element($this->make_record()))->normalise_data($form->get_data());
        $this->assertSame(100, $payload['intervalvalue']);
        $this->assertSame('years', $payload['intervalunit']);
    }

    /**
     * Imports of custom intervals that are missing or malformed skip the element, never defaulting.
     *
     * @covers \customcertelement_expiry\exporter::convert_for_import
     */
    public function test_import_custom_interval_missing_fields(): void {
        $base = [
            'dateitem' => ['value' => element::EXPIRY_CUSTOM],
            'dateformat' => ['value' => 'validfor'],
            'startfrom' => ['value' => 'award'],
            'font' => ['value' => 'times'],
            'fontsize' => ['value' => 12],
            'colour' => ['value' => '#000000'],
            'width' => ['value' => 0],
        ];
        $cases = [
            'both missing' => [],
            'unit missing' => ['intervalvalue' => ['value' => 3]],
            'value missing' => ['intervalunit' => ['value' => 'days']],
            'value not wrapped' => ['intervalvalue' => 3, 'intervalunit' => ['value' => 'days']],
            'value over unit maximum' => ['intervalvalue' => ['value' => 101], 'intervalunit' => ['value' => 'years']],
            'months over unit maximum' => ['intervalvalue' => ['value' => 1201], 'intervalunit' => ['value' => 'months']],
        ];

        foreach ($cases as $name => $extra) {
            try {
                $this->make_exporter()->convert_for_import($extra + $base);
                $this->fail($name . ' should not import');
            } catch (format_error $e) {
                $this->assertNotSame('', $e->getMessage(), $name);
            }
        }
    }

    /**
     * Stray interval fields on legacy options are ignored and never stored.
     *
     * @covers \customcertelement_expiry\exporter::convert_for_import
     */
    public function test_import_legacy_ignores_stray_interval_fields(): void {
        $data = [
            'dateitem' => ['value' => '-12'],
            'dateformat' => ['value' => 'strftimedate'],
            'startfrom' => ['value' => 'award'],
            'font' => ['value' => 'times'],
            'fontsize' => ['value' => 12],
            'colour' => ['value' => '#000000'],
            'width' => ['value' => 0],
            'intervalvalue' => ['value' => 'junk'],
            'intervalunit' => ['value' => 'junk'],
        ];

        $imported = json_decode($this->make_exporter()->convert_for_import($data), true);
        $this->assertSame('-12', $imported['dateitem']);
        $this->assertArrayNotHasKey('intervalvalue', $imported);
        $this->assertArrayNotHasKey('intervalunit', $imported);
    }

    /**
     * The generated PDF text, the HTML preview and reporting agree for a custom interval.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     * @covers \customcertelement_expiry\element::get_expiry_date
     */
    public function test_pdf_html_and_reporting_are_consistent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');

        $issuetime = (new DateTimeImmutable('2025-08-31 12:00:00', new DateTimeZone('Europe/London')))->getTimestamp();
        $expected = (new DateTimeImmutable('2026-02-28 12:00:00', new DateTimeZone('Europe/London')))->getTimestamp();
        $format = 'strftimedate';

        [$dateid, $customcertid] = $this->create_customcert_setup([
            'dateitem' => '-13', 'dateformat' => $format, 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months',
        ]);
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        $DB->set_field('customcert_issues', 'timecreated', $issuetime, ['customcertid' => $customcertid]);
        $user = $DB->get_record('user', ['id' => $student->id]);

        $captured = fn(int $elementid): string => $this->capture_pdf_content(
            new element($DB->get_record('customcert_elements', ['id' => $elementid])),
            false,
            $user
        );

        // Reporting and the PDF agree for the same start timestamp.
        $this->assertSame($expected, element::get_expiry_date($customcertid, (int)$student->id));
        $this->assertSame(element_helper::get_date_format_string($expected, $format), $captured($dateid));

        // Valid for text matches between the PDF and the HTML preview.
        $DB->set_field('customcert_elements', 'data', json_encode([
            'dateitem' => '-13', 'dateformat' => 'validfor', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months',
        ]), ['id' => $dateid]);
        $validfor = get_string('validforcustommonths', 'customcertelement_expiry', 6);
        $this->assertSame($validfor, $captured($dateid));
        $this->assertStringContainsString(
            $validfor,
            (new element($DB->get_record('customcert_elements', ['id' => $dateid])))->render_html()
        );
    }

    /**
     * Render an element to a mocked PDF and return the content it passed to the renderer.
     *
     * @param element $el
     * @param bool $preview
     * @param stdClass $user
     * @return string
     */
    private function capture_pdf_content(element $el, bool $preview, stdClass $user): string {
        $content = null;
        $pdf = $this->getMockBuilder(pdf::class)->onlyMethods(['writeHTMLCell'])->getMock();
        $pdf->method('writeHTMLCell')->willReturnCallback(
            function ($w, $h, $x, $y, $html) use (&$content) {
                $content = $html;
                return 0;
            }
        );
        $pdf->AddPage();
        $el->render($pdf, $preview, $user);

        return (string)$content;
    }

    /**
     * The PDF preview and the HTML preview show the same expiry date, calculated from the current time.
     *
     * @covers \customcertelement_expiry\element::render
     * @covers \customcertelement_expiry\element::render_html
     */
    public function test_pdf_preview_matches_html_preview(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');

        $payload = ['dateitem' => '-13', 'dateformat' => 'strftimedate', 'startfrom' => 'award',
            'intervalvalue' => 90, 'intervalunit' => 'days', 'font' => 'times', 'fontsize' => 12,
            'colour' => '#000000', 'width' => 0];
        $el = new element($this->make_record(['data' => json_encode($payload)]));
        $user = $this->getDataGenerator()->create_user();

        // Both previews start from time(), so allow for the clock passing midnight between the calls.
        $before = element_helper::get_date_format_string(
            element::calculate_expiry_time(time(), $payload),
            'strftimedate'
        );
        $pdfcontent = $this->capture_pdf_content($el, true, $user);
        $htmlcontent = $el->render_html();
        $after = element_helper::get_date_format_string(
            element::calculate_expiry_time(time(), $payload),
            'strftimedate'
        );

        $this->assertNotSame('', $pdfcontent);
        $this->assertContains($pdfcontent, [$before, $after]);
        $this->assertTrue(
            str_contains($htmlcontent, $pdfcontent),
            'HTML preview "' . $htmlcontent . '" does not contain PDF preview "' . $pdfcontent . '"'
        );
    }

    /**
     * Data provider for local times that do not exist or occur twice, in Europe/London.
     *
     * @return array
     */
    public static function london_dst_edge_provider(): array {
        return [
            'days into skipped hour' => [[1, 'days'], '2025-03-29 01:30:00', '2025-03-30 01:30:00 UTC'],
            'months into skipped hour' => [[2, 'months'], '2025-01-30 01:30:00', '2025-03-30 01:30:00 UTC'],
            'years into skipped hour' => [[1, 'years'], '2024-03-30 01:30:00', '2025-03-30 01:30:00 UTC'],
            'days into repeated hour' => [[1, 'days'], '2025-10-25 01:30:00', '2025-10-26 00:30:00 UTC'],
            'months into repeated hour from summer time' => [[2, 'months'], '2025-08-26 01:30:00', '2025-10-26 00:30:00 UTC'],
            'months into repeated hour from winter time' => [[9, 'months'], '2025-01-26 01:30:00', '2025-10-26 01:30:00 UTC'],
        ];
    }

    /**
     * Local times skipped or repeated by a clock change follow PHP's normalisation, deterministically.
     *
     * A skipped time moves forward by the gap. A repeated time resolves to the occurrence that matches
     * the UTC offset of the start time.
     *
     * @dataProvider london_dst_edge_provider
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @param array $interval Value and unit
     * @param string $start Local start time
     * @param string $expected Expected expiry, with an explicit UTC offset
     */
    public function test_calculate_expiry_time_dst_edges(array $interval, string $start, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');

        $startts = (new DateTimeImmutable($start, new DateTimeZone('Europe/London')))->getTimestamp();
        $payload = self::custom_payload($interval[0], $interval[1]);
        $result = element::calculate_expiry_time($startts, $payload);

        $this->assertSame(strtotime($expected), $result);
        $this->assertSame($result, element::calculate_expiry_time($startts, $payload));
    }

    /**
     * Test the shifted result for the skipped hour is always later than the requested local time.
     *
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     */
    public function test_calculate_expiry_time_skipped_hour_moves_forward(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');
        $london = new DateTimeZone('Europe/London');

        $start = (new DateTimeImmutable('2025-03-29 01:30:00', $london))->getTimestamp();
        $result = element::calculate_expiry_time($start, self::custom_payload(1, 'days'));

        $this->assertSame('2025-03-30 02:30', (new DateTimeImmutable('@' . $result))->setTimezone($london)->format('Y-m-d H:i'));
    }

    /**
     * Data provider for integer inputs that PHP's integer filter treats in a particular way.
     *
     * @return array
     */
    public static function integer_input_provider(): array {
        return [
            'plain string' => ['12', true],
            'plus sign' => ['+12', true],
            'surrounding spaces' => [' 12 ', true],
            'integral float' => [12.0, true],
            'leading zero' => ['012', false],
            'decimal string' => ['12.0', false],
            'exponent' => ['1e1', false],
            'hexadecimal' => ['0x0C', false],
            'comma separated' => ['1,2', false],
            'inner space' => ['1 2', false],
            'boolean true' => [true, false],
            'boolean false' => [false, false],
            'empty array' => [[], false],
            'array with value' => [['12'], false],
            'object' => [new stdClass(), false],
        ];
    }

    /**
     * Document how awkward integer inputs are treated.
     *
     * Surrounding spaces and a plus sign are accepted, like PARAM_INT. Leading zeros, decimals and
     * exponents are rejected rather than truncated or reinterpreted.
     *
     * @dataProvider integer_input_provider
     * @covers \customcertelement_expiry\element::validate_interval
     * @param mixed $value
     * @param bool $valid
     */
    public function test_interval_integer_edge_cases(mixed $value, bool $valid): void {
        $this->assertSame($valid, element::validate_interval($value, 'months') === []);
    }

    /**
     * Accepted interval strings are stored as the integer they represent.
     *
     * @covers \customcertelement_expiry\element::normalise_data
     */
    public function test_accepted_interval_strings_are_stored_as_integers(): void {
        $el = new element($this->make_record());

        foreach (['12', '+12', ' 12 '] as $value) {
            $payload = $el->normalise_data((object)[
                'dateitem' => element::EXPIRY_CUSTOM, 'intervalvalue' => $value, 'intervalunit' => 'months',
            ]);
            $this->assertSame(12, $payload['intervalvalue'], var_export($value, true));
        }
    }

    /**
     * The expiry timestamp is the same for every viewer, while its displayed date follows the viewer's timezone.
     *
     * This is the existing Moodle convention: dates are formatted with userdate().
     *
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     * @covers \customcertelement_expiry\element::get_expiry_html
     */
    public function test_expiry_display_follows_viewer_timezone_not_timestamp(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        [, $customcertid] = $this->create_customcert_setup([
            'dateitem' => '-13', 'dateformat' => 'strftimedate', 'startfrom' => 'award',
            'intervalvalue' => 1, 'intervalunit' => 'months',
        ]);
        $courseid = (int)$DB->get_field('customcert', 'course', ['id' => $customcertid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid);
        certificate_issue_service::create()->issue_certificate($customcertid, (int)$student->id);
        // Expires 2025-02-28 23:30 UTC, which is already 1 March in Auckland.
        $DB->set_field('customcert_issues', 'timecreated', strtotime('2025-01-31 23:30:00 UTC'), ['customcertid' => $customcertid]);

        $timestamps = [];
        $days = [];
        foreach (['UTC', 'Pacific/Auckland'] as $timezone) {
            $this->setUser($this->getDataGenerator()->create_user(['timezone' => $timezone]));
            $timestamp = element::get_expiry_date($customcertid, (int)$student->id);
            $timestamps[] = $timestamp;
            $days[] = userdate($timestamp, '%Y-%m-%d', 99, false);
            $this->assertStringContainsString(userdate($timestamp), element::get_expiry_html($customcertid, (int)$student->id));
        }

        $this->assertCount(1, array_unique($timestamps));
        $this->assertSame(['2025-02-28', '2025-03-01'], $days);
    }

    /**
     * Importing a template whose custom interval is invalid skips only that element, with a warning.
     *
     * @covers \customcertelement_expiry\exporter::convert_for_import
     * @covers \mod_customcert\export\element::import
     * @covers \mod_customcert\export\template::import
     */
    public function test_template_import_skips_invalid_expiry_element(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        $warnings = [];
        $logger = $this->createMock(template_import_logger_interface::class);
        $logger->method('warning')->willReturnCallback(function (string $message) use (&$warnings) {
            $warnings[] = $message;
        });
        $clock = $this->createMock(\core\clock::class);
        $clock->method('time')->willReturn(1000000);
        $importer = new \mod_customcert\export\template(
            $clock,
            new \mod_customcert\export\page(
                $clock,
                new \mod_customcert\export\element(
                    $clock,
                    $logger,
                    $this->createMock(template_appendix_manager_interface::class)
                )
            )
        );

        $layout = ['posx' => 10, 'posy' => 10, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1];
        $base = [
            'dateformat' => ['value' => 'validfor'], 'startfrom' => ['value' => 'award'],
            'font' => ['value' => 'times'], 'fontsize' => ['value' => 12],
            'colour' => ['value' => '#000000'], 'width' => ['value' => 0],
        ];
        $valid = $base + [
            'dateitem' => ['value' => element::EXPIRY_CUSTOM],
            'intervalvalue' => ['value' => 6], 'intervalunit' => ['value' => 'months'],
        ];
        $invalid = $base + [
            'dateitem' => ['value' => element::EXPIRY_CUSTOM],
            'intervalvalue' => ['value' => 101], 'intervalunit' => ['value' => 'years'],
        ];
        $legacy = $base + ['dateitem' => ['value' => '-9']];
        // Exports made before custom intervals existed have no interval fields at all.
        $this->assertArrayNotHasKey('intervalvalue', $legacy);

        $importer->import(1, [
            'name' => 'Imported expiry template',
            'pages' => [[
                'width' => 210, 'height' => 297, 'leftmargin' => 10, 'rightmargin' => 10, 'sequence' => 1,
                'elements' => [
                    ['name' => 'Valid custom', 'element' => 'expiry', 'data' => $valid] + $layout,
                    ['name' => 'Invalid custom', 'element' => 'expiry', 'data' => $invalid] + $layout,
                    ['name' => 'Legacy', 'element' => 'expiry', 'data' => $legacy] + $layout,
                ],
            ]],
        ]);

        $names = $DB->get_fieldset_select('customcert_elements', 'name', '1 = 1 ORDER BY name');
        $this->assertSame(['Legacy', 'Valid custom'], $names);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString(
            get_string('errorinvalidinterval', 'customcertelement_expiry', 100),
            $warnings[0]
        );
        $this->assertStringContainsString(
            strip_tags(get_string('errorimportinterval', 'customcertelement_expiry', '')),
            $warnings[0]
        );

        $stored = json_decode($DB->get_field('customcert_elements', 'data', ['name' => 'Valid custom']), true);
        $this->assertSame(6, $stored['intervalvalue']);
        $this->assertSame('months', $stored['intervalunit']);
        $legacystored = json_decode($DB->get_field('customcert_elements', 'data', ['name' => 'Legacy']), true);
        $this->assertArrayNotHasKey('intervalvalue', $legacystored);
    }

    /**
     * Legacy 1-5 year options keep their historical strtotime() results in the server timezone.
     *
     * @covers \customcertelement_expiry\element::calculate_expiry_time
     */
    public function test_legacy_options_use_server_timezone_like_strtotime(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Pacific/Auckland']));

        $start = strtotime('2024-02-29 23:30:00 UTC');
        $this->assertSame(strtotime('+1 year', $start), element::calculate_expiry_time($start, ['dateitem' => '-8']));
        $this->assertSame(strtotime('+5 years', $start), element::calculate_expiry_time($start, ['dateitem' => '-12']));
        // Legacy options overflow from 29 February to 1 March, unlike custom intervals which clamp.
        $this->assertSame(
            strtotime('2025-03-01 23:30:00 UTC'),
            element::calculate_expiry_time($start, ['dateitem' => '-8'])
        );
        $this->assertSame(
            strtotime('2025-02-28 23:30:00 UTC'),
            element::calculate_expiry_time($start, self::custom_payload(1, 'years'))
        );
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

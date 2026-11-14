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

namespace customcertelement_expiry;

use advanced_testcase;
use mod_customcert\element_factory;
use mod_customcert\external;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Tests for the expiry element.
 *
 * @package    customcertelement_expiry
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \customcertelement_expiry\element
 */
final class element_test extends advanced_testcase {
    /** @var stdClass The course. */
    private stdClass $course;

    /** @var stdClass The student. */
    private stdClass $student;

    /** @var stdClass The customcert instance. */
    private stdClass $customcert;

    /** @var int Page id. */
    private int $pageid;

    /** @var int Template id. */
    private int $templateid;

    /**
     * Create a course, student and a certificate with one page, using a fixed timezone.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

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
        $this->setAdminUser();
    }

    /**
     * Build a timestamp in UTC.
     *
     * @param string $date Date/time string.
     * @return int
     */
    private static function ts(string $date): int {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->getTimestamp();
    }

    /**
     * Insert an expiry element and return an element instance.
     *
     * @param array $data Stored data.
     * @return element
     */
    private function create_element(array $data): element {
        global $DB;

        $record = (object) [
            'pageid' => $this->pageid,
            'element' => 'expiry',
            'name' => 'Expiry',
            'posx' => 10,
            'posy' => 10,
            'width' => 0,
            'font' => 'helvetica',
            'fontsize' => 12,
            'colour' => '#000000',
            'refpoint' => 1,
            'alignment' => 'L',
            'data' => json_encode($data),
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = (int) $DB->insert_record('customcert_elements', $record);

        return element_factory::get_element_instance($record);
    }

    /**
     * Issue the certificate to the student at the given time.
     *
     * @param int $time Issue time.
     */
    private function issue(int $time): void {
        global $DB;

        $DB->insert_record('customcert_issues', (object) [
            'userid' => $this->student->id,
            'customcertid' => $this->customcert->id,
            'code' => 'ABC123',
            'emailed' => 0,
            'timecreated' => $time,
        ]);
    }

    /**
     * Create a PDF double which records the text written to it.
     *
     * @return \pdf
     */
    private function get_pdf_double(): \pdf {
        return new class extends \pdf {
            /** @var string[] Text written. */
            public array $written = [];

            // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
            #[\ReturnTypeWillChange]
            public function writeHTMLCell(
                $w,
                $h,
                $x,
                $y,
                $html = '',
                $border = 0,
                $ln = 0,
                $fill = false,
                $reseth = true,
                $align = '',
                $autopadding = true
            ) {
                $this->written[] = $html;
                return 0;
            }
        };
    }

    /**
     * Data provider for calculate_expiry().
     *
     * @return array
     */
    public static function calculate_expiry_provider(): array {
        $custom = fn(int $value, string $unit) => ['dateitem' => 'custom', 'intervalvalue' => $value, 'intervalunit' => $unit];

        return [
            'legacy 1 year' => [['dateitem' => '-8'], '2024-03-15 10:30:00', '2025-03-15 10:30:00'],
            'legacy 2 years' => [['dateitem' => '-9'], '2024-03-15 10:30:00', '2026-03-15 10:30:00'],
            'legacy 3 years' => [['dateitem' => '-10'], '2024-03-15 10:30:00', '2027-03-15 10:30:00'],
            'legacy 4 years' => [['dateitem' => '-11'], '2024-03-15 10:30:00', '2028-03-15 10:30:00'],
            'legacy 5 years' => [['dateitem' => '-12'], '2024-03-15 10:30:00', '2029-03-15 10:30:00'],
            'legacy leap day keeps strtotime behaviour' => [['dateitem' => '-8'], '2024-02-29 00:00:00', '2025-03-01 00:00:00'],
            'custom days' => [$custom(10, 'days'), '2024-03-15 10:30:00', '2024-03-25 10:30:00'],
            'custom days across month end' => [$custom(30, 'days'), '2024-01-31 00:00:00', '2024-03-01 00:00:00'],
            'custom days across leap day' => [$custom(1, 'days'), '2024-02-28 00:00:00', '2024-02-29 00:00:00'],
            'custom 6 months' => [$custom(6, 'months'), '2024-03-15 10:30:00', '2024-09-15 10:30:00'],
            'custom months crossing year' => [$custom(6, 'months'), '2024-08-15 00:00:00', '2025-02-15 00:00:00'],
            'custom 12 months' => [$custom(12, 'months'), '2024-03-15 00:00:00', '2025-03-15 00:00:00'],
            'custom months end of month' => [$custom(1, 'months'), '2023-01-31 12:00:00', '2023-02-28 12:00:00'],
            'custom months end of month leap year' => [$custom(1, 'months'), '2024-01-31 12:00:00', '2024-02-29 12:00:00'],
            'custom months 31st to 30-day month' => [$custom(1, 'months'), '2024-03-31 00:00:00', '2024-04-30 00:00:00'],
            'custom months to December' => [$custom(9, 'months'), '2024-03-31 00:00:00', '2024-12-31 00:00:00'],
            'custom years' => [$custom(2, 'years'), '2024-03-15 10:30:00', '2026-03-15 10:30:00'],
            'custom years from leap day' => [$custom(1, 'years'), '2024-02-29 00:00:00', '2025-02-28 00:00:00'],
            'custom years leap to leap' => [$custom(4, 'years'), '2024-02-29 00:00:00', '2028-02-29 00:00:00'],
            'custom value as numeric string' => [
                ['dateitem' => 'custom', 'intervalvalue' => '3', 'intervalunit' => 'months'],
                '2024-03-15 00:00:00',
                '2024-06-15 00:00:00',
            ],
        ];
    }

    /**
     * Test the shared calculation.
     *
     * @dataProvider calculate_expiry_provider
     * @param array $config Element configuration.
     * @param string $start Start time.
     * @param string $expected Expected expiry.
     */
    public function test_calculate_expiry(array $config, string $start, string $expected): void {
        $this->assertSame(self::ts($expected), element::calculate_expiry((object) $config, self::ts($start)));
    }

    /**
     * Data provider for invalid configurations.
     *
     * @return array
     */
    public static function invalid_config_provider(): array {
        return [
            'unknown dateitem' => [['dateitem' => '-99']],
            'missing dateitem' => [[]],
            'custom missing value and unit' => [['dateitem' => 'custom']],
            'custom missing unit' => [['dateitem' => 'custom', 'intervalvalue' => 3]],
            'custom missing value' => [['dateitem' => 'custom', 'intervalunit' => 'days']],
            'custom zero' => [['dateitem' => 'custom', 'intervalvalue' => 0, 'intervalunit' => 'days']],
            'custom negative' => [['dateitem' => 'custom', 'intervalvalue' => -1, 'intervalunit' => 'days']],
            'custom non numeric' => [['dateitem' => 'custom', 'intervalvalue' => 'abc', 'intervalunit' => 'days']],
            'custom decimal' => [['dateitem' => 'custom', 'intervalvalue' => 1.5, 'intervalunit' => 'days']],
            'custom decimal string' => [['dateitem' => 'custom', 'intervalvalue' => '1.5', 'intervalunit' => 'days']],
            'custom unknown unit' => [['dateitem' => 'custom', 'intervalvalue' => 3, 'intervalunit' => 'weeks']],
            'custom too large' => [['dateitem' => 'custom', 'intervalvalue' => 101, 'intervalunit' => 'years']],
        ];
    }

    /**
     * Invalid configurations are handled safely.
     *
     * @dataProvider invalid_config_provider
     * @param array $config Element configuration.
     */
    public function test_calculate_expiry_invalid_config(array $config): void {
        $this->assertSame(0, element::calculate_expiry((object) $config, self::ts('2024-03-15 00:00:00')));
    }

    /**
     * Expiry reporting relative to the issue date, for legacy and custom configurations.
     */
    public function test_get_expiry_date_from_award(): void {
        $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months']);
        $this->issue(self::ts('2024-01-31 09:00:00'));

        $this->assertSame(
            self::ts('2024-07-31 09:00:00'),
            element::get_expiry_date($this->customcert->id, $this->student->id)
        );
        $this->assertTrue(element::has_expiry($this->customcert->id));
        $this->assertStringContainsString('customcertelement-expiry', element::get_expiry_html(
            $this->customcert->id,
            $this->student->id
        ));
    }

    /**
     * Legacy configuration reporting is unchanged.
     */
    public function test_get_expiry_date_legacy(): void {
        $this->create_element(['dateitem' => '-9', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->issue(self::ts('2024-03-15 09:00:00'));

        $this->assertSame(
            self::ts('2026-03-15 09:00:00'),
            element::get_expiry_date($this->customcert->id, $this->student->id)
        );
    }

    /**
     * Expiry relative to course completion, including a missing completion date.
     */
    public function test_get_expiry_date_from_course_completion(): void {
        global $DB;

        $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'coursecomplete',
            'intervalvalue' => 30, 'intervalunit' => 'days']);
        $this->issue(self::ts('2024-05-01 00:00:00'));

        // No completion yet.
        $this->assertSame(0, element::get_expiry_date($this->customcert->id, $this->student->id));
        $this->assertSame('', element::get_expiry_html($this->customcert->id, $this->student->id));

        $DB->insert_record('course_completions', (object) [
            'userid' => $this->student->id,
            'course' => $this->course->id,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => self::ts('2024-02-10 12:00:00'),
        ]);
        $this->assertSame(
            self::ts('2024-03-11 12:00:00'),
            element::get_expiry_date($this->customcert->id, $this->student->id)
        );
    }

    /**
     * Invalid custom configuration produces no report date.
     */
    public function test_get_expiry_date_invalid_config(): void {
        $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->issue(self::ts('2024-03-15 09:00:00'));

        $this->assertSame(0, element::get_expiry_date($this->customcert->id, $this->student->id));
    }

    /**
     * Report date is zero when the certificate has not been issued.
     */
    public function test_get_expiry_date_not_issued(): void {
        $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award']);

        $this->assertSame(0, element::get_expiry_date($this->customcert->id, $this->student->id));
    }

    /**
     * Data provider for "Valid for" PDF output.
     *
     * @return array
     */
    public static function validfor_provider(): array {
        return [
            'legacy 1 year' => [['dateitem' => '-8'], 'Valid for 1 year'],
            'legacy 5 years' => [['dateitem' => '-12'], 'Valid for 5 years'],
            'custom 1 day' => [['dateitem' => 'custom', 'intervalvalue' => 1, 'intervalunit' => 'days'], 'Valid for 1 day'],
            'custom 10 days' => [['dateitem' => 'custom', 'intervalvalue' => 10, 'intervalunit' => 'days'], 'Valid for 10 days'],
            'custom 1 month' => [['dateitem' => 'custom', 'intervalvalue' => 1, 'intervalunit' => 'months'], 'Valid for 1 month'],
            'custom 6 months' => [['dateitem' => 'custom', 'intervalvalue' => 6, 'intervalunit' => 'months'], 'Valid for 6 months'],
            'custom 1 year' => [['dateitem' => 'custom', 'intervalvalue' => 1, 'intervalunit' => 'years'], 'Valid for 1 year'],
            'custom 3 years' => [['dateitem' => 'custom', 'intervalvalue' => 3, 'intervalunit' => 'years'], 'Valid for 3 years'],
        ];
    }

    /**
     * PDF rendering of the "Valid for" format.
     *
     * @dataProvider validfor_provider
     * @param array $config Element configuration.
     * @param string $expected Expected text.
     */
    public function test_render_pdf_validfor(array $config, string $expected): void {
        $element = $this->create_element($config + ['dateformat' => 'validfor', 'startfrom' => 'award']);
        $this->issue(self::ts('2024-03-15 09:00:00'));

        $pdf = $this->get_pdf_double();
        $element->render($pdf, false, $this->student);
        $this->assertSame([$expected], $pdf->written);
    }

    /**
     * PDF rendering of a formatted date.
     */
    public function test_render_pdf_date(): void {
        $element = $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months']);
        $this->issue(self::ts('2024-03-15 09:00:00'));

        $pdf = $this->get_pdf_double();
        $element->render($pdf, false, $this->student);
        $this->assertCount(1, $pdf->written);
        $this->assertStringContainsString('September 15th, 2024', $pdf->written[0]);
    }

    /**
     * PDF rendering writes nothing for invalid configuration or a missing completion date.
     */
    public function test_render_pdf_nothing_when_unavailable(): void {
        $invalid = $this->create_element(['dateitem' => 'custom', 'dateformat' => 'validfor', 'startfrom' => 'award',
            'intervalvalue' => 0, 'intervalunit' => 'days']);
        $nocompletion = $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'coursecomplete',
            'intervalvalue' => 2, 'intervalunit' => 'days']);
        $this->issue(self::ts('2024-03-15 09:00:00'));

        $pdf = $this->get_pdf_double();
        $invalid->render($pdf, false, $this->student);
        $nocompletion->render($pdf, false, $this->student);
        $this->assertSame([], $pdf->written);
    }

    /**
     * Preview rendering calculates from the current time and ignores issue data.
     */
    public function test_render_pdf_preview(): void {
        $element = $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'coursecomplete',
            'intervalvalue' => 2, 'intervalunit' => 'years']);

        $pdf = $this->get_pdf_double();
        $element->render($pdf, true, $this->student);
        $this->assertCount(1, $pdf->written);
        $expected = element::calculate_expiry((object) ['dateitem' => 'custom', 'intervalvalue' => 2,
            'intervalunit' => 'years'], time());
        $this->assertStringContainsString(userdate($expected, '%Y'), $pdf->written[0]);
    }

    /**
     * HTML rendering (drag and drop page) for legacy and custom configurations.
     */
    public function test_render_html(): void {
        $legacy = $this->create_element(['dateitem' => '-8', 'dateformat' => 'validfor', 'startfrom' => 'award']);
        $this->assertStringContainsString('Valid for one year', $legacy->render_html());

        $custom = $this->create_element(['dateitem' => 'custom', 'dateformat' => 'validfor', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months']);
        $this->assertStringContainsString('Valid for 6 months', $custom->render_html());

        $date = $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months']);
        $expected = element::calculate_expiry((object) ['dateitem' => 'custom', 'intervalvalue' => 6,
            'intervalunit' => 'months'], time());
        $this->assertStringContainsString(userdate($expected, '%Y'), $date->render_html());

        $invalid = $this->create_element(['dateitem' => 'custom', 'dateformat' => 'validfor', 'startfrom' => 'award']);
        $this->assertEmpty($invalid->render_html());

        $legacydate = $this->create_element(['dateitem' => '-9', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->assertNotEmpty($legacydate->render_html());
    }

    /**
     * Save through the real edit form and return the stored data.
     *
     * @param array $values Submitted values.
     * @param int|null $elementid Existing element id.
     * @return array|null Decoded data or null if rejected.
     */
    private function save(array $values, ?int $elementid = null): ?array {
        global $DB;

        if ($elementid === null) {
            $elementid = $this->create_element(['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award'])->get_id();
        }
        $submitted = [];
        foreach ($values as $name => $value) {
            $submitted[] = ['name' => $name, 'value' => (string) $value];
        }
        try {
            external::save_element($this->templateid, $elementid, $submitted);
        } catch (\invalid_parameter_exception $e) {
            return null;
        }
        return json_decode($DB->get_field('customcert_elements', 'data', ['id' => $elementid]), true);
    }

    /**
     * Build the element's form fields and populate them from the stored data.
     *
     * @param element $element The element.
     * @return \MoodleQuickForm
     */
    private function build_form(element $element): \MoodleQuickForm {
        $mform = new \MoodleQuickForm('expirytest', 'post', '');
        $element->render_form_elements($mform);
        $element->definition_after_data($mform);
        return $mform;
    }

    /**
     * Saving a custom interval stores the interval fields.
     */
    public function test_save_custom_interval(): void {
        $stored = $this->save([
            'name' => 'Expiry',
            'dateitem' => 'custom',
            'customintervalvalue' => 6,
            'customintervalunit' => 'months',
            'dateformat' => 'validfor',
            'startfrom' => 'award',
        ]);
        $this->assertSame('custom', $stored['dateitem']);
        $this->assertSame(6, $stored['intervalvalue']);
        $this->assertSame('months', $stored['intervalunit']);
        $this->assertSame('validfor', $stored['dateformat']);
    }

    /**
     * Saving a legacy option does not store interval fields, even if they are submitted.
     */
    public function test_save_legacy_option_has_no_interval(): void {
        $stored = $this->save([
            'name' => 'Expiry',
            'dateitem' => '-10',
            'customintervalvalue' => 6,
            'customintervalunit' => 'months',
            'dateformat' => '2',
            'startfrom' => 'award',
        ]);
        $this->assertSame(
            ['dateitem' => '-10', 'dateformat' => '2', 'startfrom' => 'award'],
            $stored
        );
    }

    /**
     * Invalid custom values are rejected by the form.
     */
    public function test_save_custom_interval_validation(): void {
        $base = ['name' => 'Expiry', 'dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award'];
        $this->assertNull($this->save($base + ['customintervalvalue' => 0, 'customintervalunit' => 'days']));
        $this->assertNull($this->save($base + ['customintervalvalue' => -3, 'customintervalunit' => 'days']));
        $this->assertNull($this->save($base + ['customintervalvalue' => 'abc', 'customintervalunit' => 'days']));
        $this->assertNull($this->save($base + ['customintervalvalue' => '', 'customintervalunit' => 'days']));
        $this->assertNull($this->save($base + ['customintervalvalue' => 101, 'customintervalunit' => 'years']));
        $this->assertNull($this->save($base + ['customintervalvalue' => 3, 'customintervalunit' => 'weeks']));
        $this->assertNotNull($this->save($base + ['customintervalvalue' => 100, 'customintervalunit' => 'years']));
    }

    /**
     * Editing an existing configuration populates the form, and changing it works.
     */
    public function test_edit_existing_configuration(): void {
        $element = $this->create_element(['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award',
            'intervalvalue' => 6, 'intervalunit' => 'months']);

        $mform = $this->build_form($element);
        $this->assertEquals(['custom'], $mform->getElement('dateitem')->getValue());
        $this->assertEquals(6, $mform->getElement('customintervalvalue')->getValue());
        $this->assertEquals(['months'], $mform->getElement('customintervalunit')->getValue());

        $legacy = $this->create_element(['dateitem' => '-11', 'dateformat' => '2', 'startfrom' => 'award']);
        $this->assertEquals(['-11'], $this->build_form($legacy)->getElement('dateitem')->getValue());

        // Switch the legacy element to a custom interval and back.
        $stored = $this->save([
            'name' => 'Expiry', 'dateitem' => 'custom', 'customintervalvalue' => 45,
            'customintervalunit' => 'days', 'dateformat' => '2', 'startfrom' => 'award',
        ], $legacy->get_id());
        $this->assertSame(45, $stored['intervalvalue']);
        $stored = $this->save([
            'name' => 'Expiry', 'dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award',
        ], $legacy->get_id());
        $this->assertArrayNotHasKey('intervalvalue', $stored);
        $this->assertSame('-8', $stored['dateitem']);
    }

    /**
     * Duplicating the activity (backup and restore) preserves custom and legacy configurations.
     */
    public function test_backup_restore_preserves_configuration(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $configs = [
            ['dateitem' => 'custom', 'dateformat' => 'validfor', 'startfrom' => 'award',
                'intervalvalue' => 6, 'intervalunit' => 'months'],
            ['dateitem' => '-8', 'dateformat' => '2', 'startfrom' => 'award'],
        ];
        foreach ($configs as $config) {
            $this->create_element($config);
        }

        $cm = get_fast_modinfo($this->course)->get_cm($this->customcert->cmid);
        $newcm = duplicate_module($this->course, $cm);
        $newtemplateid = (int) $DB->get_field('customcert', 'templateid', ['id' => $newcm->instance], MUST_EXIST);
        $this->assertNotEquals($this->templateid, $newtemplateid);

        $sql = "SELECT e.data
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON p.id = e.pageid
                 WHERE p.templateid = :templateid
              ORDER BY e.id";
        $restored = array_map(
            fn($data) => json_decode($data, true),
            $DB->get_fieldset_sql($sql, ['templateid' => $newtemplateid])
        );
        $this->assertSame($configs, $restored);
    }

    /**
     * Copying a template preserves the custom interval configuration.
     */
    public function test_copy_to_template_preserves_configuration(): void {
        global $DB;

        $config = ['dateitem' => 'custom', 'dateformat' => '2', 'startfrom' => 'award',
            'intervalvalue' => 2, 'intervalunit' => 'years'];
        $this->create_element($config);

        $template = $DB->get_record('customcert_templates', ['id' => $this->templateid], '*', MUST_EXIST);
        $copy = (new \mod_customcert\template($template))->copy_to_template(
            new \mod_customcert\template((object) [
                'id' => $DB->insert_record('customcert_templates', (object) [
                    'name' => 'Target',
                    'contextid' => \context_system::instance()->id,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ]),
                'name' => 'Target',
                'contextid' => \context_system::instance()->id,
            ])
        );
        unset($copy);
        $datas = $DB->get_fieldset_select('customcert_elements', 'data', "element = 'expiry'");
        $this->assertCount(2, $datas);
        foreach ($datas as $data) {
            $this->assertSame($config, json_decode($data, true));
        }
    }
}

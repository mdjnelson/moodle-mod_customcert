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
 * This file contains the customcert element expiry's core interaction API.
 *
 * @package    customcertelement_expiry
 * @copyright  2024 Leon Stringer <leon.stringer@ntlworld.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_expiry;

use mod_customcert\element as base_element;
use core_date;
use DateTimeImmutable;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\certificate_repository;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;
use mod_customcert\element\restorable_element_interface;

/**
 * The customcert element expiry's core interaction API.
 *
 * @package    customcertelement_expiry
 * @copyright  2024 Leon Stringer <leon.stringer@ntlworld.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    restorable_element_interface,
    validatable_element_interface
{
    /**
     * Date - Relative expiry date of 1 year
     */
    private const string EXPIRY_ONE = '-8';

    /**
     * Date - Relative expiry date of 2 year
     */
    private const string EXPIRY_TWO = '-9';

    /**
     * Date - Relative expiry date of 3 year
     */
    private const string EXPIRY_THREE = '-10';

    /**
     * Date - Relative expiry date of 4 year
     */
    private const string EXPIRY_FOUR = '-11';

    /**
     * Date - Relative expiry date of 5 year
     */
    private const string EXPIRY_FIVE = '-12';

    /**
     * Date - Custom interval, configured using a value and a unit.
     */
    public const string EXPIRY_CUSTOM = '-13';

    /** @var string Custom interval unit - days. */
    public const string UNIT_DAYS = 'days';

    /** @var string Custom interval unit - months. */
    public const string UNIT_MONTHS = 'months';

    /** @var string Custom interval unit - years. */
    public const string UNIT_YEARS = 'years';

    /** @var array Maximum custom interval value allowed for each unit. */
    private const array MAX_INTERVAL = [
        self::UNIT_DAYS => 36500,
        self::UNIT_MONTHS => 1200,
        self::UNIT_YEARS => 100,
    ];

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        global $CFG, $COURSE;

        $dateoptions[self::EXPIRY_ONE] = get_string('expirydateone', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_TWO] = get_string('expirydatetwo', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_THREE] = get_string('expirydatethree', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_FOUR] = get_string('expirydatefour', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_FIVE] = get_string('expirydatefive', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_CUSTOM] = get_string('expirydatecustom', 'customcertelement_expiry');

        $startdates['award'] = get_string('awarddate', 'customcertelement_expiry');
        if ($CFG->enablecompletion && ($COURSE->id == SITEID || $COURSE->enablecompletion)) {
            $startdates['coursecomplete'] = get_string('completiondate', 'customcertelement_expiry');
        }

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelement_expiry'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelement_expiry');

        $mform->addElement('text', 'intervalvalue', get_string('intervalvalue', 'customcertelement_expiry'), ['size' => 5]);
        $mform->setType('intervalvalue', PARAM_INT);
        $mform->setDefault('intervalvalue', 1);
        $mform->hideIf('intervalvalue', 'dateitem', 'neq', self::EXPIRY_CUSTOM);

        $unitoptions = [
            self::UNIT_DAYS => get_string('unitdays', 'customcertelement_expiry'),
            self::UNIT_MONTHS => get_string('unitmonths', 'customcertelement_expiry'),
            self::UNIT_YEARS => get_string('unityears', 'customcertelement_expiry'),
        ];
        $mform->addElement('select', 'intervalunit', get_string('intervalunit', 'customcertelement_expiry'), $unitoptions);
        $mform->setDefault('intervalunit', self::UNIT_MONTHS);
        $mform->hideIf('intervalunit', 'dateitem', 'neq', self::EXPIRY_CUSTOM);

        $mform->addElement(
            'select',
            'dateformat',
            get_string('dateformat', 'customcertelement_expiry'),
            self::get_date_formats()
        );
        $mform->addHelpButton('dateformat', 'dateformat', 'customcertelement_expiry');

        $mform->addElement('select', 'startfrom', get_string('startfrom', 'customcertelement_expiry'), $startdates);
        $mform->addHelpButton('startfrom', 'startfrom', 'customcertelement_expiry');

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Normalise expiry element data.
     *
     * @param stdClass $formdata Form submission data
     * @return array JSON-serialisable payload
     */
    public function normalise_data(stdClass $formdata): array {
        $data = [
            'dateitem' => (string)($formdata->dateitem ?? ''),
            'dateformat' => (string)($formdata->dateformat ?? ''),
            'startfrom' => (string)($formdata->startfrom ?? ''),
        ];

        // Only custom intervals store their configuration, so legacy payloads are unchanged.
        if ($data['dateitem'] === self::EXPIRY_CUSTOM) {
            $data['intervalvalue'] = (int)($formdata->intervalvalue ?? 0);
            $data['intervalunit'] = (string)($formdata->intervalunit ?? '');
        }

        return array_merge($data, stylable_payload::from_form($formdata)->to_array());
    }

    /**
     * Validate the custom interval settings.
     *
     * @param array $data Submitted form data
     * @return array<string,string> Errors keyed by form field name
     */
    public function validate(array $data): array {
        $errors = [];

        if (($data['dateitem'] ?? '') !== self::EXPIRY_CUSTOM) {
            return $errors;
        }

        $unit = (string)($data['intervalunit'] ?? '');
        if (!isset(self::MAX_INTERVAL[$unit])) {
            $errors['intervalunit'] = get_string('errorinvalidunit', 'customcertelement_expiry');
            return $errors;
        }

        $value = $data['intervalvalue'] ?? null;
        if (!is_numeric($value) || (int)$value != $value || (int)$value < 1 || (int)$value > self::MAX_INTERVAL[$unit]) {
            $errors['intervalvalue'] = get_string('errorinvalidinterval', 'customcertelement_expiry', self::MAX_INTERVAL[$unit]);
        }

        return $errors;
    }

    /**
     * Prepare the form by populating the dateitem, dateformat, and startfrom fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        if (!empty($this->get_data())) {
            $payload = $this->get_payload();
            if (isset($payload['dateitem'])) {
                $mform->getElement('dateitem')->setValue($payload['dateitem']);
            }
            if (isset($payload['dateformat'])) {
                $mform->getElement('dateformat')->setValue($payload['dateformat']);
            }
            if (isset($payload['startfrom'])) {
                $mform->getElement('startfrom')->setValue($payload['startfrom']);
            }
            if (isset($payload['intervalvalue'])) {
                $mform->getElement('intervalvalue')->setValue($payload['intervalvalue']);
            }
            if (isset($payload['intervalunit'])) {
                $mform->getElement('intervalunit')->setValue($payload['intervalunit']);
            }
        }
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     */
    public function render(pdf $pdf, bool $preview, stdClass $user): void {
        if (empty($this->get_data())) {
            return;
        }

        $payload = $this->get_payload();
        $dateformat = $payload['dateformat'] ?? '';
        $dateitem = $payload['dateitem'] ?? '';
        $date = $this->expiry((int) $user->id, $preview);

        if (!empty($date)) {
            $content = '';
            if ($dateformat == 'validfor') {
                if ($dateitem == self::EXPIRY_CUSTOM) {
                    $content = $this->get_validfor_string($payload);
                } else if ($dateitem == self::EXPIRY_ONE) {
                    $content = 'Valid for 1 year';
                } else if ($dateitem == self::EXPIRY_TWO) {
                    $content = 'Valid for 2 years';
                } else if ($dateitem == self::EXPIRY_THREE) {
                    $content = 'Valid for 3 years';
                } else if ($dateitem == self::EXPIRY_FOUR) {
                    $content = 'Valid for 4 years';
                } else if ($dateitem == self::EXPIRY_FIVE) {
                    $content = 'Valid for 5 years';
                }
            } else {
                $content = element_helper::get_date_format_string($date, (string)$dateformat);
            }

            if (!empty($content)) {
                element_helper::render_content($pdf, $this, $content);
            }
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html(): string {
        if (empty($this->get_data())) {
            return '';
        }

        $payload = $this->get_payload();
        $dateformat = $payload['dateformat'] ?? '';
        $dateitem = $payload['dateitem'] ?? '';

        $content = '';
        if ($dateformat == 'validfor') {
            if ($dateitem == self::EXPIRY_CUSTOM) {
                $content = $this->get_validfor_string($payload);
            } else if ($dateitem == self::EXPIRY_ONE) {
                $content = get_string('validfor1year', 'customcertelement_expiry');
            } else if ($dateitem == self::EXPIRY_TWO) {
                $content = get_string('validfor2years', 'customcertelement_expiry');
            } else if ($dateitem == self::EXPIRY_THREE) {
                $content = get_string('validfor3years', 'customcertelement_expiry');
            } else if ($dateitem == self::EXPIRY_FOUR) {
                $content = get_string('validfor4years', 'customcertelement_expiry');
            } else if ($dateitem == self::EXPIRY_FIVE) {
                $content = get_string('validfor5years', 'customcertelement_expiry');
            }
        } else {
            $date = self::calculate_expiry_time(time(), $payload);
            $content = $date ? element_helper::get_date_format_string($date, (string)$dateformat) : '';
        }

        if (empty($content)) {
            return '';
        }

        return element_helper::render_html_content($this, $content);
    }


    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the date element is pointing to as it will
     * have changed in the course restore.
     *
     * @param restore_customcert_activity_task $restore
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $data = $this->get_payload();
        // Expiry element typically uses relative constants; keep legacy mapping logic if present.
        if (!empty($data['dateitem']) && strpos((string)$data['dateitem'], 'gradeitem:') === 0) {
            $oldid = str_replace('gradeitem:', '', (string)$data['dateitem']);
            $newid = $restore->get_mappingid('grade_item', (int)$oldid);
            if ($newid) {
                $data['dateitem'] = 'gradeitem:' . $newid;
                $DB->set_field('customcert_elements', 'data', json_encode($data), ['id' => $this->get_id()]);
            }
        }
    }

    /**
     * Returns the "Valid for ..." text for a custom interval.
     *
     * @param array $payload The element payload
     * @return string The text, or an empty string if the interval is invalid
     */
    private function get_validfor_string(array $payload): string {
        $interval = self::get_custom_interval($payload);
        if ($interval === null) {
            return '';
        }

        // Strings are 'validforcustomday', 'validforcustomdays', etc.
        $unit = $interval['value'] === 1 ? rtrim($interval['unit'], 's') : $interval['unit'];

        return get_string('validforcustom' . $unit, 'customcertelement_expiry', $interval['value']);
    }

    /**
     * Returns the validated custom interval from the payload.
     *
     * @param array $payload The element payload
     * @return array|null Array with 'value' (int) and 'unit' (string), or null if missing or invalid
     */
    private static function get_custom_interval(array $payload): ?array {
        $unit = (string)($payload['intervalunit'] ?? '');
        $value = $payload['intervalvalue'] ?? null;

        if (!isset(self::MAX_INTERVAL[$unit]) || !is_numeric($value) || (int)$value != $value) {
            return null;
        }

        $value = (int)$value;
        if ($value < 1 || $value > self::MAX_INTERVAL[$unit]) {
            return null;
        }

        return ['value' => $value, 'unit' => $unit];
    }

    /**
     * Calculate an expiry timestamp from a start time and an element payload.
     *
     * Used for PDF rendering, previews and reporting. The 1-5 year options keep their historical
     * strtotime() behaviour. Custom month/year intervals are clamped to the last day of the target
     * month (for example, 31 January + 1 month = 28 or 29 February).
     *
     * @param int $starttime Unix timestamp to calculate from
     * @param array $payload The element payload
     * @return int Unix timestamp, or 0 if the configuration is missing or invalid
     */
    public static function calculate_expiry_time(int $starttime, array $payload): int {
        $dateitem = (string)($payload['dateitem'] ?? '');

        if ($dateitem !== self::EXPIRY_CUSTOM) {
            $relative = [
                self::EXPIRY_ONE => '+1 year',
                self::EXPIRY_TWO => '+2 years',
                self::EXPIRY_THREE => '+3 years',
                self::EXPIRY_FOUR => '+4 years',
                self::EXPIRY_FIVE => '+5 years',
            ];
            if (!isset($relative[$dateitem])) {
                return 0;
            }
            return (int)strtotime($relative[$dateitem], $starttime);
        }

        $interval = self::get_custom_interval($payload);
        if ($interval === null) {
            return 0;
        }

        $start = (new DateTimeImmutable('@' . $starttime))->setTimezone(core_date::get_user_timezone_object());

        if ($interval['unit'] === self::UNIT_DAYS) {
            return $start->modify('+' . $interval['value'] . ' days')->getTimestamp();
        }

        $months = $interval['unit'] === self::UNIT_YEARS ? $interval['value'] * 12 : $interval['value'];
        $total = ((int)$start->format('Y') * 12) + ((int)$start->format('n') - 1) + $months;
        $year = intdiv($total, 12);
        $month = ($total % 12) + 1;
        $lastday = (int)$start->setDate($year, $month, 1)->format('t');
        $day = min((int)$start->format('j'), $lastday);

        return $start->setDate($year, $month, $day)->getTimestamp();
    }

    /**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    public static function get_date_formats(): array {
        $dateformats = element_helper::get_date_formats();
        $dateformats['validfor'] = get_string('validfor', 'customcertelement_expiry');

        return $dateformats;
    }

    /**
     * Get expiry date for user.
     *
     * @param int $userid User who has been awarded certificate.
     * @param bool $preview True if it is a preview in which case calculate
     * expiry date from now, false otherwise.
     * @return int Timestamp in Unix format (number of seconds since epoch).
     */
    private function expiry(int $userid, bool $preview = false): int {
        global $DB;

        $payload = $this->get_payload();
        $startfrom = $payload['startfrom'] ?? '';
        $starttime = null;

        if ($preview) {
            $starttime = time();
        } else if ($startfrom == 'coursecomplete') {
            $courseid = element_helper::get_courseid($this->id);
            $sql = "SELECT MAX(c.timecompleted) as timecompleted
                      FROM {course_completions} c
                     WHERE c.userid = :userid
                       AND c.course = :courseid";
            if ($timecompleted = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid])) {
                if (!empty($timecompleted->timecompleted)) {
                    $starttime = $timecompleted->timecompleted;
                }
            }
        } else { // Expiry date calculated from certificate award date.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
            $customcert = (new certificate_repository())->get_by_template_id_or_fail((int)$page->templateid);
            $issue = $DB->get_record(
                'customcert_issues',
                ['userid' => $userid, 'customcertid' => $customcert->id],
                '*',
                IGNORE_MULTIPLE
            );
            $starttime = $issue ? $issue->timecreated : null;
        }

        if (is_null($starttime)) {
            return 0;
        }

        return self::calculate_expiry_time((int) $starttime, $payload);
    }

    /**
     * Does this certificate have one or more expiry elements?
     *
     * @param int $customcertid ID of the certificate.
     * @return bool True if this certificate has an expiry element (and thus
     * can show an expiry date for reports), false otherwise.
     */
    public static function has_expiry($customcertid): bool {
        global $DB;
        $sql = "SELECT e.id
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON e.pageid = p.id
                  JOIN {customcert} c ON p.templateid = c.templateid
                 WHERE element = 'expiry' AND c.id = :customcertid";
        return !empty($DB->get_records_sql($sql, ['customcertid' => $customcertid]));
    }

    /**
     * Return the expiry date for this certificate wrapped in a <span>.
     *
     * @param int $customcertid The certificate.
     * @param int $userid The user who has been awarded this certificate.
     * @return string HTML fragment, for example, '<span
     * class="customcertelement-expiry ok">Monday, 6 July 2026, 2:40 PM</span>'
     */
    public static function get_expiry_html(int $customcertid, int $userid): string {
        global $OUTPUT;
        $expiry = self::get_expiry_date($customcertid, $userid);

        // This can happen if the 'startfrom' date is course completion and the
        // student hasn't completed the course but has been awarded a
        // certificate.
        if (empty($expiry)) {
            return '';
        }

        $data = new stdClass();
        $data->date = userdate($expiry);
        $expired = ($expiry - time()) / DAYSECS;

        if ($expired < 0) {
            $data->expiry = "expired";
        } else if ($expired < 14) {
            $data->expiry = "expire-soon";
        } else {
            $data->expiry = "ok";
        }

        return $OUTPUT->render_from_template('customcertelement_expiry/date', $data);
    }

    /**
     * Return the expiry date for this certificate.  If there are multiple
     * expiry elements for the given certificate then the date is calculated
     * using the settings for the first element returned by the database.
     * (Multiple elements are supported as date elements using dateitem = -8 to
     * -12 are migrated to this element with no restriction on the number of
     * elements).
     *
     * @param int $customcertid The certificate.
     * @param int $userid The user who has been awarded this certificate.
     * @return int Timestamp in Unix format (number of seconds since epoch).
     */
    public static function get_expiry_date(int $customcertid, int $userid): int {
        global $DB;
        $sql = "SELECT e.*
                  FROM {customcert_elements} e
                  JOIN {customcert_pages} p ON e.pageid = p.id
                  JOIN {customcert} c ON p.templateid = c.templateid
                 WHERE element = 'expiry' AND c.id = :customcertid";

        // As it's permitted to have more than one expiry element on a
        // certificate we use the first returned by this query to calculate the
        // expiry date for reporting.
        $expirydata = $DB->get_records_sql($sql, ['customcertid' => $customcertid], 0, 1);
        $element = new self(reset($expirydata));
        return $element->expiry($userid);
    }
}

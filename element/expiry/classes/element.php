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

namespace customcertelement_expiry;

use mod_customcert\element_helper;

/**
 * The customcert element expiry's core interaction API.
 *
 * @package    customcertelement_expiry
 * @copyright  2024 Leon Stringer <leon.stringer@ntlworld.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {
    /**
     * Date - Relative expiry date of 1 year
     */
    private const EXPIRY_ONE = '-8';

    /**
     * Date - Relative expiry date of 2 year
     */
    private const EXPIRY_TWO = '-9';

    /**
     * Date - Relative expiry date of 3 year
     */
    private const EXPIRY_THREE = '-10';

    /**
     * Date - Relative expiry date of 4 year
     */
    private const EXPIRY_FOUR = '-11';

    /**
     * Date - Relative expiry date of 5 year
     */
    private const EXPIRY_FIVE = '-12';

    /**
     * Date - Custom interval (a positive number of days, months or years).
     */
    private const EXPIRY_CUSTOM = 'custom';

    /** Custom interval unit - days. */
    public const UNIT_DAYS = 'days';

    /** Custom interval unit - months. */
    public const UNIT_MONTHS = 'months';

    /** Custom interval unit - years. */
    public const UNIT_YEARS = 'years';

    /** @var array Largest accepted custom interval value for each unit. */
    private const MAX_INTERVAL = [
        self::UNIT_DAYS => 36500,
        self::UNIT_MONTHS => 1200,
        self::UNIT_YEARS => 100,
    ];

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $CFG, $COURSE;

        $dateoptions[self::EXPIRY_ONE] = get_string('expirydateone', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_TWO] = get_string('expirydatetwo', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_THREE] = get_string('expirydatethree', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_FOUR] = get_string('expirydatefour', 'customcertelement_expiry');
        $dateoptions[self::EXPIRY_FIVE] = get_string('expirydatefive', 'customcertelement_expiry');

        $dateoptions[self::EXPIRY_CUSTOM] = get_string('expirydatecustom', 'customcertelement_expiry');

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelement_expiry'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelement_expiry');

        $mform->addElement('text', 'customintervalvalue', get_string('customintervalvalue', 'customcertelement_expiry'));
        $mform->setType('customintervalvalue', PARAM_INT);
        $mform->setDefault('customintervalvalue', 1);
        $mform->hideIf('customintervalvalue', 'dateitem', 'neq', self::EXPIRY_CUSTOM);

        $mform->addElement(
            'select',
            'customintervalunit',
            get_string('customintervalunit', 'customcertelement_expiry'),
            [
                self::UNIT_DAYS => get_string('unitdays', 'customcertelement_expiry'),
                self::UNIT_MONTHS => get_string('unitmonths', 'customcertelement_expiry'),
                self::UNIT_YEARS => get_string('unityears', 'customcertelement_expiry'),
            ]
        );
        $mform->setDefault('customintervalunit', self::UNIT_MONTHS);
        $mform->hideIf('customintervalunit', 'dateitem', 'neq', self::EXPIRY_CUSTOM);

        $mform->addElement('select', 'dateformat', get_string('dateformat', 'customcertelement_expiry'), self::get_date_formats());
        $mform->addHelpButton('dateformat', 'dateformat', 'customcertelement_expiry');

        $startdates['award'] = get_string('awarddate', 'customcertelement_expiry');

        if ($CFG->enablecompletion && ($COURSE->id == SITEID || $COURSE->enablecompletion)) {
            $startdates['coursecomplete'] = get_string('completiondate', 'customcertelement_expiry');
        }

        $mform->addElement('select', 'startfrom', get_string('startfrom', 'customcertelement_expiry'), $startdates);
        $mform->addHelpButton('startfrom', 'startfrom', 'customcertelement_expiry');

        parent::render_form_elements($mform);
    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);

        if (($data['dateitem'] ?? '') === self::EXPIRY_CUSTOM) {
            $unit = $data['customintervalunit'] ?? '';
            $value = $data['customintervalvalue'] ?? '';
            if (!array_key_exists($unit, self::MAX_INTERVAL)) {
                $errors['customintervalunit'] = get_string('errorinvalidunit', 'customcertelement_expiry');
            } else if (!self::is_valid_interval_value($value, $unit)) {
                $errors['customintervalvalue'] = get_string(
                    'errorinvalidvalue',
                    'customcertelement_expiry',
                    self::MAX_INTERVAL[$unit]
                );
            }
        }

        return $errors;
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = [
            'dateitem' => $data->dateitem,
            'dateformat' => $data->dateformat,
            'startfrom' => $data->startfrom,
        ];

        // The interval is only stored for custom intervals so configurations
        // of the original 1-5 year options remain unchanged.
        if ($data->dateitem === self::EXPIRY_CUSTOM) {
            $arrtostore['intervalvalue'] = (int) ($data->customintervalvalue ?? 0);
            $arrtostore['intervalunit'] = (string) ($data->customintervalunit ?? '');
        }

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $dateinfo = json_decode($this->get_data());
        $dateformat = $dateinfo->dateformat;
        $date = $this->expiry($user->id, $preview);

        // Ensure that a date has been set.
        if (!empty($date)) {
            if ($dateformat == 'validfor') {
                $text = $this->get_valid_for_text($dateinfo, true);
                if ($text !== null) {
                    element_helper::render_content($pdf, $this, $text);
                }
            } else {
                element_helper::render_content($pdf, $this, element_helper::get_date_format_string($date, $dateformat));
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
    public function render_html() {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());

        if ($dateinfo->dateformat == 'validfor') {
            $text = $this->get_valid_for_text($dateinfo, false);
            if ($text !== null) {
                return element_helper::render_html_content($this, $text);
            }
            return;
        }

        $date = self::calculate_expiry($dateinfo, time());
        if (empty($date)) {
            return;
        }
        return element_helper::render_html_content(
            $this,
            element_helper::get_date_format_string($date, $dateinfo->dateformat)
        );
    }

    /**
     * Returns the "Valid for ..." text for the given element configuration.
     *
     * @param \stdClass $dateinfo the decoded element data
     * @param bool $forpdf true when rendering the PDF. The original 1-5 year
     *             options keep their historical PDF wording.
     * @return string|null the text, or null if the configuration is invalid
     */
    private function get_valid_for_text(\stdClass $dateinfo, bool $forpdf): ?string {
        $dateitem = (string) ($dateinfo->dateitem ?? '');

        if ($dateitem === self::EXPIRY_CUSTOM) {
            $interval = self::get_interval($dateinfo);
            if ($interval === null) {
                return null;
            }
            [$value, $unit] = $interval;
            $stringid = 'validfor' . rtrim($unit, 's') . ($value === 1 ? '' : 's');
            return get_string($stringid, 'customcertelement_expiry', $value);
        }

        $legacy = [
            self::EXPIRY_ONE => [1, 'Valid for 1 year', 'validfor1year'],
            self::EXPIRY_TWO => [2, 'Valid for 2 years', 'validfor2years'],
            self::EXPIRY_THREE => [3, 'Valid for 3 years', 'validfor3years'],
            self::EXPIRY_FOUR => [4, 'Valid for 4 years', 'validfor4years'],
            self::EXPIRY_FIVE => [5, 'Valid for 5 years', 'validfor5years'],
        ];
        if (!isset($legacy[$dateitem])) {
            return null;
        }
        return $forpdf ? $legacy[$dateitem][1] : get_string($legacy[$dateitem][2], 'customcertelement_expiry');
    }

    /**
     * Returns the validated custom interval from the element data.
     *
     * @param \stdClass $dateinfo the decoded element data
     * @return array|null [int value, string unit] or null if missing/invalid
     */
    private static function get_interval(\stdClass $dateinfo): ?array {
        $value = $dateinfo->intervalvalue ?? null;
        $unit = $dateinfo->intervalunit ?? null;
        if (!is_string($unit) || !array_key_exists($unit, self::MAX_INTERVAL)) {
            return null;
        }
        if (!self::is_valid_interval_value($value, $unit)) {
            return null;
        }
        return [(int) $value, $unit];
    }

    /**
     * Is the given value a valid positive integer for the unit?
     *
     * @param mixed $value the value to check
     * @param string $unit one of the UNIT_ constants
     * @return bool
     */
    private static function is_valid_interval_value($value, string $unit): bool {
        if (is_string($value)) {
            $value = trim($value);
            if (!preg_match('/^\d+$/', $value)) {
                return false;
            }
        } else if (!is_int($value)) {
            return false;
        }
        $value = (int) $value;
        return $value >= 1 && $value <= self::MAX_INTERVAL[$unit];
    }

    /**
     * Calculates the expiry timestamp for the given configuration and start time.
     *
     * This is the single calculation used by the PDF, the preview and reports.
     * Month and year intervals clamp to the last day of the target month
     * (31 January + 1 month = 28/29 February, 29 February + 1 year = 28 February).
     * The original 1-5 year options keep their historical strtotime() behaviour.
     *
     * @param \stdClass $dateinfo the decoded element data
     * @param int $starttime the start time
     * @return int the expiry timestamp, or 0 if the configuration is invalid
     */
    public static function calculate_expiry(\stdClass $dateinfo, int $starttime): int {
        $dateitem = (string) ($dateinfo->dateitem ?? '');

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
            return (int) strtotime($relative[$dateitem], $starttime);
        }

        $interval = self::get_interval($dateinfo);
        if ($interval === null) {
            return 0;
        }
        [$value, $unit] = $interval;

        $start = (new \DateTimeImmutable())->setTimestamp($starttime);
        if ($unit === self::UNIT_DAYS) {
            return $start->modify('+' . $value . ' days')->getTimestamp();
        }

        $months = $unit === self::UNIT_YEARS ? $value * 12 : $value;
        $year = (int) $start->format('Y');
        $month = (int) $start->format('n') - 1 + $months;
        $year += intdiv($month, 12);
        $month = $month % 12 + 1;
        $day = min(
            (int) $start->format('j'),
            (int) $start->setDate($year, $month, 1)->format('t')
        );

        return $start->setDate($year, $month, $day)->getTimestamp();
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $dateinfo = json_decode($this->get_data());

            $element = $mform->getElement('dateitem');
            $element->setValue($dateinfo->dateitem);

            $element = $mform->getElement('dateformat');
            $element->setValue($dateinfo->dateformat);

            $element = $mform->getElement('startfrom');
            $element->setValue($dateinfo->startfrom);

            if (isset($dateinfo->intervalvalue)) {
                $mform->getElement('customintervalvalue')->setValue($dateinfo->intervalvalue);
            }
            if (isset($dateinfo->intervalunit)) {
                $mform->getElement('customintervalunit')->setValue($dateinfo->intervalunit);
            }
        }

        parent::definition_after_data($mform);
    }

    /**
     * The expiry element does not reference any course data, so there is nothing to remap on restore.
     * The stored configuration (including any custom interval) is restored unchanged.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
    }

    /**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    private static function get_date_formats(): array {
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
    private function expiry($userid, $preview = false) {
        global $DB;

        $dateinfo = json_decode($this->get_data());
        $startfrom = $dateinfo->startfrom ?? 'award';
        $starttime = null;

        if ($preview) {
            $starttime = time();
        } else if ($startfrom == 'coursecomplete') {
            $courseid = \mod_customcert\element_helper::get_courseid($this->id);
            // Get the last completion date.
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
            // Get the page.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
            // Get the customcert this page belongs to.
            $customcert = $DB->get_record('customcert', ['templateid' => $page->templateid], '*', MUST_EXIST);
            // Now we can get the issue for this user.
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

        return self::calculate_expiry($dateinfo, (int) $starttime);
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

        $data = new \stdClass();
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

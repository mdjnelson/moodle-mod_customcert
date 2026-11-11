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

namespace customcertelement_nestedform;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\validatable_element_interface;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Native element fixture with repeated rows of nested date selectors, like the historical daterange plugin.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    validatable_element_interface {
    /** @var int Number of repeated rows on the form. */
    public const ROWS = 2;

    /**
     * Build the configuration form: each row posts startdate[n][day], enddate[n][day], label[n] and recurring[n].
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        for ($i = 0; $i < self::ROWS; $i++) {
            $mform->addElement('date_selector', "startdate[$i]", 'Start ' . $i);
            $mform->addElement('date_selector', "enddate[$i]", 'End ' . $i);
            $mform->addElement('text', "label[$i]", 'Label ' . $i);
            $mform->setType("label[$i]", PARAM_RAW);
            $mform->addElement('advcheckbox', "recurring[$i]", 'Recurring ' . $i);
        }
    }

    /**
     * Load the stored rows into the form.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $stored = json_decode((string)$this->get_data(), true);
        foreach (($stored['rows'] ?? []) as $i => $row) {
            foreach ($row as $field => $value) {
                $mform->setDefault("{$field}[$i]", $value);
            }
        }
    }

    /**
     * Each row's end date must not precede its start date.
     *
     * @param array $data
     * @return array
     */
    public function validate(array $data): array {
        $errors = [];
        for ($i = 0; $i < self::ROWS; $i++) {
            if ((int)$data['enddate'][$i] < (int)$data['startdate'][$i]) {
                $errors["enddate[$i]"] = 'End before start';
            }
        }

        return $errors;
    }

    /**
     * Store the repeated rows as a list.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        $rows = [];
        for ($i = 0; $i < self::ROWS; $i++) {
            $rows[$i] = [
                'startdate' => (int)$formdata->startdate[$i],
                'enddate' => (int)$formdata->enddate[$i],
                'label' => (string)$formdata->label[$i],
                'recurring' => (int)$formdata->recurring[$i],
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Render into a PDF (not needed by the fixture).
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user): void {
        unset($pdf, $preview, $user);
    }

    /**
     * Render the stored labels.
     *
     * @return string
     */
    public function render_html(): string {
        $stored = json_decode((string)$this->get_data(), true);

        return s(implode(', ', array_column($stored['rows'] ?? [], 'label')));
    }
}

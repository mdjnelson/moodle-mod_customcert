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
 * Native element fixture that implements only the documented native interfaces.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\edit_element_form;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\renderable_element_interface;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Native element with no base class, styling capability, width or availability hook.
 */
class independent_native_element implements form_element_interface, renderable_element_interface {
    /** @var stdClass The stored element record. */
    private stdClass $record;

    /**
     * Constructor.
     *
     * @param stdClass $record The stored element record.
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
    }

    /**
     * Get the element ID.
     *
     * @return int
     */
    public function get_id(): int {
        return (int)$this->record->id;
    }

    /**
     * Get the ID of the page containing the element.
     *
     * @return int
     */
    public function get_pageid(): int {
        return (int)$this->record->pageid;
    }

    /**
     * Get the element name.
     *
     * @return string
     */
    public function get_name(): string {
        return (string)$this->record->name;
    }

    /**
     * Get the element data.
     *
     * @return mixed
     */
    public function get_data(): mixed {
        return $this->record->data ?? null;
    }

    /**
     * Get the element type.
     *
     * @return string
     */
    public function get_type(): string {
        return (string)$this->record->element;
    }

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        unset($mform);
    }

    /**
     * Receive the edit form.
     *
     * @param edit_element_form $editelementform
     * @return void
     */
    public function set_edit_element_form(edit_element_form $editelementform): void {
        unset($editelementform);
    }

    /**
     * Report whether the form offers a save and continue button.
     *
     * @return bool
     */
    public function has_save_and_continue(): bool {
        return false;
    }

    /**
     * Render the element into a PDF context.
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
     * Render the element in HTML for the designer.
     *
     * @return string
     */
    public function render_html(): string {
        return 'Independent preview';
    }
}

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
 * Contract for elements that participate in the edit-element form lifecycle.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\edit_element_form;
use MoodleQuickForm;

/**
 * Interface form_element_interface
 *
 * Implemented by elements (both v2 and legacy-adapted) that can participate in
 * the edit-element form lifecycle. edit_element_form types its element property
 * against this interface rather than the legacy base class, so that
 * legacy_element_adapter instances are accepted without a TypeError.
 *
 * All registered certificate elements must implement this interface (or extend
 * mod_customcert\element, which implements it). The build_form() method replaces
 * the legacy render_form_elements() hook for new elements.
 */
interface form_element_interface extends element_interface {
    /**
     * Add element-specific fields to the edit form.
     *
     * New elements should implement this method directly. Legacy elements that
     * override render_form_elements() are bridged automatically by the base class.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void;

    /**
     * Attach the edit element form to this element.
     *
     * @param edit_element_form $editelementform
     * @return void
     */
    public function set_edit_element_form(edit_element_form $editelementform): void;

    /**
     * Whether this element supports a "Save and continue" action in the form.
     *
     * @return bool
     */
    public function has_save_and_continue(): bool;
}

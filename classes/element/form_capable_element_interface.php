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
 * Provides helper functionality.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use MoodleQuickForm;
use stdClass;

/**
 * Form-capable element contract (scaffolding only; not wired yet).
 *
 * Describes how an element contributes to forms, validates, and persists
 * its configuration. Mirrors current method shapes to maintain BC later.
 */
interface form_capable_element_interface {
    /**
     * Render form controls for this element type.
     *
     * @param MoodleQuickForm $mform
     */
    public function render_form_elements(MoodleQuickForm $mform): void;

    /**
     * Populate form with element data after defaults are set.
     *
     * @param MoodleQuickForm $mform
     */
    public function definition_after_data(MoodleQuickForm $mform): void;

    /**
     * Validate submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array field => error message
     */
    public function validate_form_elements(array $data, array $files): array;

    /**
     * Save submitted data related to this element.
     * Returns new id (int) on insert, true on successful update, or false.
     *
     * @param stdClass $data
     */
    public function save_form_elements(stdClass $data): int|bool;

    /**
     * Map unique element data to a string for DB storage (typically JSON).
     *
     * @param stdClass $data
     */
    public function save_unique_data(stdClass $data): string;

    /**
     * Whether this element needs a "Save and continue" button.
     */
    public function has_save_and_continue(): bool;
}

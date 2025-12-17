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

namespace mod_customcert\element\traits;

use mod_customcert\element_helper;
use MoodleQuickForm;

/**
 * Shared helpers to add common form controls for elements.
 *
 * Wraps calls to element_helper to avoid duplication in concrete elements.
 * Scaffolding only; not wired yet.
 */
trait form_common_controls_trait {
    /**
     * Add the common controls: font, colour, position (optional), width, refpoint, alignment.
     *
     * @param MoodleQuickForm $mform
     * @param bool $showposxy
     */
    protected function add_common_controls(MoodleQuickForm $mform, bool $showposxy = false): void {
        element_helper::render_form_element_font($mform);
        element_helper::render_form_element_colour($mform);
        if ($showposxy) {
            element_helper::render_form_element_position($mform);
        }
        element_helper::render_form_element_width($mform);
        element_helper::render_form_element_refpoint($mform);
        element_helper::render_form_element_alignment($mform);
    }
}

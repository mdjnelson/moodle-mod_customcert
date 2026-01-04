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
 * Enum of supported MoodleQuickForm element types used by customcert element forms.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Backed enum mapping to MoodleQuickForm element type strings.
 *
 * Note: Values mirror MoodleQuickForm names to avoid adapter layers.
 */
enum field_type: string {
    case text = 'text';
    case textarea = 'textarea';
    case select = 'select';
    case filemanager = 'filemanager';
    case passwordunmask = 'passwordunmask';
    case advcheckbox = 'advcheckbox';
    case editor = 'editor';
    case header = 'header';
    case date_selector = 'date_selector';
    // Note: cannot use "static" as a case name (reserved keyword); use snake_case alias mapping to value 'static'.
    case static_text = 'static';
}

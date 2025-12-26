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

namespace mod_customcert\element;

/**
 * Dynamic selects interface for element form fields.
 *
 * Elements implementing this interface expose one or more select fields whose
 * options are provided dynamically at build time by the form service.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface dynamic_selects_interface {
    /**
     * Map field names to callables that return an array of options.
     * Example: [ 'fileid' => [self::class, 'get_images'] ]
     *
     * @return array<string, callable>
     */
    public function get_dynamic_selects(): array;
}

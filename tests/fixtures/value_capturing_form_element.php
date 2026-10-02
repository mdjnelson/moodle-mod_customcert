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

namespace mod_customcert\tests\fixtures;

/**
 * Minimal stub MoodleQuickForm element that records the value passed to setValue().
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class value_capturing_form_element {
    /** @var mixed The value captured via setValue(). */
    private mixed $value = null;

    /**
     * Record the value set on this form element.
     *
     * @param mixed $value
     */
    public function setValue($value): void { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->value = $value;
    }

    /**
     * Return the captured value.
     *
     * @return mixed
     */
    public function get_value(): mixed {
        return $this->value;
    }
}

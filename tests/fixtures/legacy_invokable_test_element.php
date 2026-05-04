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

namespace mod_customcert\tests\fixtures;

use MoodleQuickForm;

/**
 * Legacy-only element fixture that records render_form_elements invocations.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_invokable_test_element extends legacy_only_test_element {
    /**
     * Flag set when render_form_elements() is called.
     *
     * @var bool
     */
    public bool $called = false;

    /**
     * Record that the legacy render path was invoked.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function render_form_elements($mform) {
        $this->called = true;
    }
}

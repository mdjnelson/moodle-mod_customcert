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

use mod_customcert\element;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Test fixture: legacy element with definition_after_data for adapter testing.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_definition_after_data_element extends element {
    /**
     * Flag to track if definition_after_data was called.
     *
     * @var bool
     */
    public bool $called = false;

    /**
     * Legacy definition_after_data implementation.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function definition_after_data($mform) {
        $this->called = true;
    }

    /**
     * Render to PDF (not used in this test).
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(
        pdf $pdf,
        bool $preview,
        stdClass $user,
        ?element_renderer $renderer = null
    ): void {
    }

    /**
     * Render HTML (not used in this test).
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

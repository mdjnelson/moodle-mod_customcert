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
 * Legacy element fixture that returns a plain string from save_unique_data().
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element as legacy_base_element;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * A minimal legacy element that returns a plain string from save_unique_data().
 *
 * Used to test JSON-encoding fallback logic for legacy elements.
 */
final class legacy_plain_string_element extends legacy_base_element {
    /**
     * Legacy save implementation returning a plain string.
     *
     * @param stdClass $data Raw form data.
     * @return string
     */
    public function save_unique_data($data) { // phpcs:ignore
        return 'plainstring';
    }

    /**
     * Render into TCPDF (unused in these tests).
     *
     * @param \pdf $pdf The PDF instance.
     * @param bool $preview Preview flag.
     * @param stdClass $user User record.
     * @param element_renderer|null $renderer Optional renderer.
     * @return void
     */
    public function render(\pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
    }

    /**
     * Render HTML (unused in these tests).
     *
     * @param element_renderer|null $renderer Optional renderer.
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

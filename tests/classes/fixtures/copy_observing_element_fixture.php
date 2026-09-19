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
use mod_customcert\element\copyable_element_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Fixture element that implements copyable_element_interface and records copy_from() calls.
 *
 * Used to verify that element_repository::copy_element() invokes copy_from(), passes it the
 * expected source record and that the result is reflected on the new element instance.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_observing_element_fixture extends element implements
    copyable_element_interface,
    renderable_element_interface {
    /** @var stdClass|null The source record most recently passed to copy_from(). */
    public static ?stdClass $lastsource = null;

    /** @var int Number of times copy_from() has been invoked. */
    public static int $calls = 0;

    /** @var bool Value copy_from() should return; allows simulating a copy failure. */
    public static bool $result = true;

    /** @var bool Whether copy_from() was invoked on this particular instance. */
    public bool $copiedfrom = false;

    /**
     * Reset the static observation state between tests.
     *
     * @return void
     */
    public static function reset(): void {
        self::$lastsource = null;
        self::$calls = 0;
        self::$result = true;
    }

    /**
     * Perform additional work when this element is copied from a source record.
     *
     * @param stdClass $source The original element DB record being copied from.
     * @return bool True on success, false if the copy should be aborted.
     */
    public function copy_from(stdClass $source): bool {
        self::$calls++;
        self::$lastsource = $source;
        $this->copiedfrom = self::$result;
        return self::$result;
    }

    /**
     * Add element-specific fields to the edit form (no-op in fixture).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
    }

    /**
     * Render the element to PDF (no-op in fixture).
     *
     * @param pdf $pdf The PDF instance
     * @param bool $preview Preview mode flag
     * @param stdClass $user The user record
     * @param element_renderer|null $renderer Optional renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
    }

    /**
     * Render the element to HTML (empty in fixture).
     *
     * @param element_renderer|null $renderer Optional renderer
     * @return string HTML
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

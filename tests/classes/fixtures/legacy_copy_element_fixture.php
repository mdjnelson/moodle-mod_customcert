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
 * Legacy-style fixture element that genuinely overrides the deprecated element::copy_element().
 *
 * It does not implement copyable_element_interface, allowing the repository's legacy-copy
 * fallback to be exercised. Note that element_repository also supports instances wrapped in
 * legacy_element_adapter, but this fixture is returned directly by element_factory::create()
 * since it already satisfies element_interface via the legacy element base class.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_copy_element_fixture extends element {
    /** @var int Number of times the legacy copy_element() has been invoked. */
    public static int $calls = 0;

    /** @var stdClass|null The data most recently passed to copy_element(). */
    public static ?stdClass $lastdata = null;

    /** @var bool Value copy_element() should return; allows simulating a copy failure. */
    public static bool $result = true;

    /**
     * Reset the static observation state between tests.
     *
     * @return void
     */
    public static function reset(): void {
        self::$calls = 0;
        self::$lastdata = null;
        self::$result = true;
    }

    /**
     * Legacy copy hook (deprecated since Moodle 5.2). Genuinely overrides the base no-op.
     *
     * @param mixed $data legacy form/element data
     * @return bool returns true if the data was copied successfully, false otherwise
     */
    public function copy_element($data) {
        self::$calls++;
        self::$lastdata = $data;
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

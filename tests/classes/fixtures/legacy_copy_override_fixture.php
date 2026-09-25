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
use stdClass;

/**
 * Fixture: genuine legacy element whose concrete class overrides the deprecated
 * element::copy_element() hook and records invocations.
 *
 * Used to verify that element_repository::copy_element() detects and dispatches to a
 * concrete legacy copy_element() override (#984), as distinct from the native
 * copyable_element_interface::copy_from() path and from a legacy element that merely
 * inherits the deprecated no-op base implementation.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_copy_override_fixture extends element {
    /** @var stdClass|null The source record most recently passed to copy_element(). */
    public static ?stdClass $lastsource = null;

    /** @var int Number of times copy_element() has been invoked. */
    public static int $calls = 0;

    /** @var bool Value copy_element() should return; allows simulating a copy failure. */
    public static bool $result = true;

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
     * Historical copy hook override.
     *
     * @param mixed $data legacy form/element data (here, the historical source record).
     * @return bool
     */
    public function copy_element($data) {
        self::$calls++;
        self::$lastsource = $data;
        return self::$result;
    }

    /**
     * Historical untyped PDF render signature (no-op in fixture).
     *
     * @param mixed $pdf
     * @param mixed $preview
     * @param mixed $user
     */
    public function render($pdf, $preview, $user) {
    }

    /**
     * Historical parameterless render_html signature (no-op in fixture).
     *
     * @return string
     */
    public function render_html() {
        return '';
    }

    /**
     * Whether this element type can be added.
     *
     * @return bool
     */
    public static function can_add(): bool {
        return true;
    }
}

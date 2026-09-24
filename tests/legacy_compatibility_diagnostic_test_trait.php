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
 * Test-only helper trait to reset legacy_compatibility_diagnostic static state.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests;

use mod_customcert\element\legacy_compatibility_diagnostic;
use ReflectionProperty;

/**
 * Resets the per-request de-duplication cache in legacy_compatibility_diagnostic
 * between PHPUnit test methods (which otherwise share PHP static state across many
 * logical "requests" running in the same long-lived process), without requiring the
 * production class to expose a public test-only reset() method.
 */
trait legacy_compatibility_diagnostic_test_trait {
    /**
     * Clear the private static de-duplication cache via reflection.
     *
     * @return void
     */
    protected function reset_legacy_compatibility_diagnostic_state(): void {
        $property = new ReflectionProperty(legacy_compatibility_diagnostic::class, 'warned');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }
}

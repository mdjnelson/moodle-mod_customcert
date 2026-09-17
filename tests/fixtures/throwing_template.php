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

use mod_customcert\template;

/**
 * Template fixture used to force a deterministic exception during PDF generation.
 *
 * Used by the language-restoration regression test to verify that PDF generation
 * has switched into the expected runtime language before deliberately interrupting
 * generation with a known exception.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class throwing_template extends template {
    /**
     * Verify the PDF language switch and throw a deliberate test exception.
     *
     * @return \context
     */
    public function get_context(): \context {
        if (current_language() !== 'en') {
            throw new \LogicException(
                'Expected PDF generation to switch the runtime language to en.'
            );
        }

        throw new \RuntimeException('Intentional test exception');
    }
}

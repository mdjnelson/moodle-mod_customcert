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

namespace mod_customcert;

use mod_customcert\service\element_renderer;

/**
 * Minimal concrete subclass for constructing an element instance in tests.
 *
 * @package   mod_customcert
 * @category  test
 */
class element_stub extends element {
    /**
     * No-op PDF render for test purposes.
     *
     * @param \pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(\pdf $pdf, bool $preview, \stdClass $user, ?element_renderer $renderer = null): void {
        // Intentionally empty.
    }

    /**
     * No-op HTML render for test purposes.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

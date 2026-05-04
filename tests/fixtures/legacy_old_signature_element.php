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
 * Fixture: legacy element with old untyped non-render hook signatures.
 *
 * Simulates a third-party element plugin that was written before 5.2 and has
 * not yet updated its non-render method signatures. Render methods use the
 * required 5.2 typed signatures; all other legacy hooks use old untyped
 * signatures to verify they do not cause a PHP fatal at class-load time.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\tests\fixtures;

use pdf;
use mod_customcert\service\element_renderer;

/**
 * Legacy element fixture with old untyped non-render hook signatures.
 */
class legacy_old_signature_element extends \mod_customcert\element {

    /**
     * Render method uses the required 5.2 typed signature.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param \stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, \stdClass $user, ?element_renderer $renderer = null): void {
        // No-op fixture.
    }

    /**
     * Render HTML method uses the required 5.2 typed signature.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }

    /**
     * Old-style non-render hook — no type hint on $mform (untyped legacy signature).
     *
     * @param mixed $mform
     */
    public function render_form_elements($mform) {
        // No-op fixture.
    }

    /**
     * Old-style validate_form_elements — no type hints.
     *
     * @param mixed $data
     * @param mixed $files
     * @return array
     */
    public function validate_form_elements($data, $files) {
        return [];
    }

    /**
     * Old-style copy_element — no type hint on $data, no return type.
     *
     * @param mixed $data
     * @return bool
     */
    public function copy_element($data) {
        return true;
    }

    /**
     * Old-style can_add — no return type.
     *
     * @return bool
     */
    public static function can_add() {
        return true;
    }
}

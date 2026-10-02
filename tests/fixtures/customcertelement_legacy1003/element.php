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
 * Fixture: legacy element whose definition_after_data() override calls
 * parent::definition_after_data(), matching the real-world customcertelement_daterange
 * pattern (#1003).
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacy1003;

use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Legacy element that overrides definition_after_data() but delegates to the parent.
 */
class element extends \mod_customcert\element {
    /** @var bool Whether this override's own custom preparation ran. */
    public $customprepared = false;

    /**
     * Render the element to PDF.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        unset($pdf, $preview, $user, $renderer);
    }

    /**
     * Render HTML preview for the designer.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        unset($renderer);
        return 'legacy1003';
    }

    /**
     * Add legacy form fields.
     *
     * @param mixed $mform
     */
    public function render_form_elements($mform) {
        unset($mform);
    }

    /**
     * Custom legacy preparation, then delegates to the inherited base implementation.
     *
     * @param mixed $mform
     */
    public function definition_after_data($mform) {
        $this->customprepared = true;
        parent::definition_after_data($mform);
    }

    /**
     * Persist element-specific form data.
     *
     * @param mixed $data
     * @return string
     */
    public function save_unique_data($data) {
        unset($data);
        return 'saved1003';
    }

    /**
     * Validate element-specific form data.
     *
     * @param mixed $data
     * @param mixed $files
     * @return array
     */
    public function validate_form_elements($data, $files) {
        unset($data, $files);
        return [];
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

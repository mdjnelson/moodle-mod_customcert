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
 * Fixture: realistically-namespaced 5.2-adapter-style legacy element (#958).
 *
 * Namespaced as customcertelement_legacy52 so that the inherited
 * mod_customcert\element::get_type() naturally returns 'legacy52', matching how a real
 * third-party plugin's element type is derived, unlike a synthetic type key registered
 * under an arbitrary namespace.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacy52;

use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Legacy element using the released Moodle 5.2 typed render contract plus legacy hooks.
 *
 * Does not override get_type(): the type is derived from the namespace by the base class.
 */
class element extends \mod_customcert\element {
    /** @var string|null Last saved unique data (for assertions). */
    public $lastsaved = null;

    /** @var bool Whether render_form_elements was called. */
    public $formcalled = false;

    /** @var bool Whether the released 5.2 render() method was actually invoked. */
    public $rendercalled = false;

    /** @var array|null Captured [$pdf, $preview, $user, $renderer] arguments from the last render() call. */
    public $lastrenderargs = null;

    /** @var element_renderer|null Renderer received by the last render_html() call. */
    public $lasthtmlrenderer = null;

    /**
     * Render the element to PDF (released Moodle 5.2 typed contract).
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @param element_renderer|null $renderer
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $this->rendercalled = true;
        $this->lastrenderargs = [$pdf, $preview, $user, $renderer];
    }

    /**
     * Render HTML preview for the designer.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        $this->lasthtmlrenderer = $renderer;
        return 'legacy52';
    }

    /**
     * Add legacy form fields.
     *
     * @param mixed $mform
     */
    public function render_form_elements($mform) {
        $this->formcalled = true;
        unset($mform);
    }

    /**
     * Persist element-specific form data.
     *
     * @param mixed $data
     * @return string
     */
    public function save_unique_data($data) {
        $value = isset($data->legacyvalue) ? (string) $data->legacyvalue : 'saved52';
        $this->lastsaved = $value;
        return $value;
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

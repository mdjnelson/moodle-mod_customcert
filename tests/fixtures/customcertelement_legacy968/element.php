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
 * Fixture: realistically-namespaced genuine Moodle 4.5-era third-party element.
 *
 * Namespaced as customcertelement_legacy968 so that the inherited
 * mod_customcert\element::get_type() naturally returns 'legacy968', matching how a real
 * third-party plugin's element type is derived, unlike a synthetic type key registered
 * under an arbitrary namespace.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_legacy968;

/**
 * Genuine 4.5-era legacy element with untyped render()/render_html() and legacy hooks.
 *
 * Does not override get_type(): the type is derived from the namespace by the base class.
 */
class element extends \mod_customcert\element {
    /** @var string|null Last saved unique data (for assertions). */
    public $lastsaved = null;

    /**
     * Historical untyped PDF render signature.
     *
     * @param mixed $pdf
     * @param mixed $preview
     * @param mixed $user
     */
    public function render($pdf, $preview, $user) {
        // No-op render body for tests.
        unset($pdf, $preview, $user);
    }

    /**
     * Historical parameterless render_html signature.
     *
     * @return string
     */
    public function render_html() {
        $label = $this->font ?? 'n/a';
        return 'legacy968:' . $label;
    }

    /**
     * Legacy form hook.
     *
     * @param mixed $mform
     */
    public function render_form_elements($mform) {
        // Do not call parent — element_helper form renderers need a real MoodleQuickForm.
        unset($mform);
    }

    /**
     * Legacy persistence hook.
     *
     * @param mixed $data
     * @return string
     */
    public function save_unique_data($data) {
        $value = isset($data->legacyvalue) ? (string) $data->legacyvalue : 'saved';
        $this->lastsaved = $value;
        return $value;
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

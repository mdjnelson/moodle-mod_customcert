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
 * A realistic legacy element fixture, namespaced like a real customcertelement_* plugin,
 * used to exercise element_repository::create() so that the inherited get_type() naturally
 * derives the 'legacy968' type from its own top-level namespace segment.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_legacy968;

use mod_customcert\element as legacy_base_element;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * A minimal legacy element that exposes whatever get_data() returns, placed in a realistic
 * customcertelement_legacy968 namespace so that get_type() (inherited from the base class)
 * naturally resolves to 'legacy968'.
 */
final class element extends legacy_base_element {
    /**
     * Return whatever get_data() returns, for assertion in tests.
     *
     * @return mixed
     */
    public function read_data(): mixed {
        return $this->get_data();
    }

    /**
     * Legacy save_unique_data implementation, used to exercise the real production
     * persistence_helper::to_json_data() pipeline in tests.
     *
     * Historically, a genuine third-party plugin's save_unique_data() returns ONLY its
     * own plugin-specific scalar value. Common visual fields (font, fontsize, colour,
     * width) were historically separate DB columns persisted by the base class, not
     * part of what save_unique_data() returns. This fixture must preserve that
     * historical contract so that the persistence layer (not the plugin) is responsible
     * for merging in the visual fields.
     *
     * @param stdClass $data The form data.
     * @return string
     */
    public function save_unique_data($data): string {
        return (string) ($data->value ?? '');
    }

    /**
     * Render into TCPDF (unused in these tests).
     *
     * @param \pdf $pdf The PDF instance.
     * @param bool $preview Preview flag.
     * @param stdClass $user User record.
     * @param element_renderer|null $renderer Optional renderer.
     * @return void
     */
    public function render(\pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
    }

    /**
     * Render HTML (unused in these tests).
     *
     * @param element_renderer|null $renderer Optional renderer.
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

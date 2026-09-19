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
 * A realistic legacy element fixture whose save_unique_data() returns a JSON object
 * string, mirroring bundled legacy elements such as date/daterange/expiry/grade/image/
 * qrcode/userpicture/digitalsignature on MOODLE_404_STABLE.
 *
 * Namespaced as customcertelement_legacyjson968 so that the inherited get_type()
 * naturally resolves to 'legacyjson968'.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_legacyjson968;

use mod_customcert\element as legacy_base_element;
use mod_customcert\service\element_renderer;
use stdClass;

/**
 * A minimal legacy element whose save_unique_data() returns a JSON object string,
 * placed in a realistic customcertelement_legacyjson968 namespace so that get_type()
 * (inherited from the base class) naturally resolves to 'legacyjson968'.
 */
final class element extends legacy_base_element {
    /**
     * Legacy save_unique_data implementation returning a JSON object string, as
     * historically supported by third-party and bundled legacy elements that persisted
     * structured data.
     *
     * @param stdClass $data The form data.
     * @return string JSON object string.
     */
    public function save_unique_data($data): string {
        return json_encode([
            'first' => $data->first ?? null,
            'second' => $data->second ?? null,
        ]);
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

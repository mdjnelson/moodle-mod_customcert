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

namespace customcertelement_text;

use mod_customcert\export\contracts\subplugin_exportable;

/**
 * Handles import and export of static text elements for custom certificates.
 *
 * This exporter deals with plain text input used in certificates, allowing
 * serialization of the content and logging informational messages for empty fields.
 *
 * @package    customcertelement_text
 * @extends    subplugin_exportable
 * @implements mod_customcert\export\contracts\subplugin_exportable
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @copyright  2025, oncampus GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_exportable {
    /**
     * Validates the static text element's content.
     *
     * If empty, logs an informational message. Returns sanitized data structure.
     *
     * @param array $data The element input data.
     * @return array|false Sanitized data or false on failure.
     */
    public function validate(array $data): array|false {
        $text = $data['text'] ?? "";

        if ($text == "") {
            $this->logger->info("Empty text element found.");
        }

        return [
            "text" => $text,
        ];
    }

    /**
     * Returns the validated text for direct database storage.
     *
     * @param array $data Validated element data.
     * @return string|null The text to be stored or null.
     */
    public function convert_for_import(array $data): ?string {
        return $data['text'];
    }

    /**
     * Wraps the stored text value in an array for export.
     *
     * @param int $elementid The ID of the text element.
     * @param string $customdata The stored text value.
     * @return array Exportable structure containing the text.
     */
    public function export(int $elementid, string $customdata): array {
        $data = [];
        $data['text'] = $customdata;
        return $data;
    }
}

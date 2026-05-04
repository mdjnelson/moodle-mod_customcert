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

namespace mod_customcert\export\datatypes;

use mod_customcert\export\template_appendix_manager_interface;
use stored_file;

/**
 * Field which exports and import a file.
 *
 * This class handles the import and export for files and
 * references files to elements.
 *
 * @package    mod_customcert
 * @copyright  2026, onCampus GmbH
 * @author     Konrad Ebel <konrad.ebel@oncampus.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_field implements field_interface, file_field_interface {
    /**
     * @var string $component Component of the file storage.
     *
     * Template export/import currently supports only mod_customcert/image files.
     * This field is always set to 'mod_customcert' by the subplugin_exportable constructor
     * and is not extensible via payload data. If support for additional components or
     * file areas is needed in future, it should be added via an explicit allowlist here
     * rather than trusting element payload data.
     */
    private string $component;

    /**
     * Constructor.
     *
     * @param string $component Component of the file storage.
     * @param template_appendix_manager_interface $filemng File manager for appendix operations.
     */
    public function __construct(
        string $component,
        /** @var template_appendix_manager_interface File manager for appendix operations. */
        private readonly template_appendix_manager_interface $filemng,
    ) {
        $this->component = $component;
    }

    /**
     * Returns the file reference name
     *
     * @param array $data Associative array containing a 'file_ref' key
     * @return array The validated input value. (Local file reference)
     */
    public function import(array $data): mixed {
        if (!$fileref = ($data['file_ref'] ?? null)) {
            throw new format_exception("File reference not set");
        }
        return $this->filemng->get_file_reference($fileref);
    }

    /**
     * Exports the file field as a structured array containing its reference ID.
     *
     * Uses internal file data to resolve and serialize a file identifier.
     *
     * @param mixed $value Field value containing file location data.
     * @return array Array containing the 'file_ref' identifier.
     */
    public function export(mixed $value): array {
        if (!$file = $this->get_file($value)) {
            return [];
        }

        return [
            'file_ref' => $this->filemng->get_identifier($file),
        ];
    }

    /**
     * Fallback value for file reference.
     *
     * @return array No fallback needed.
     */
    public function get_fallback(): mixed {
        return [];
    }

    /**
     * Retrieves the stored file instance associated with this element.
     *
     * @param array $data JSON-encoded data with file metadata.
     * @return stored_file|false The resolved image file or false if not found.
     */
    public function get_file(array $data): stored_file|false {
        if (
            empty($data["contextid"]) || empty($data["filearea"]) ||
            !isset($data["itemid"]) || !isset($data["filepath"]) || !isset($data["filename"])
        ) {
            return false;
        }
        $component = $this->component;
        $fs = get_file_storage();
        return $fs->get_file(
            (int) $data["contextid"],
            $component,
            $data["filearea"],
            (int) $data["itemid"],
            $data["filepath"],
            $data["filename"]
        );
    }
}

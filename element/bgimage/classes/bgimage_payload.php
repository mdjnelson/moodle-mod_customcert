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
 * Typed payload for the bgimage element.
 *
 * @package    customcertelement_bgimage
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_bgimage;

use mod_customcert\element\element_payload_interface;

/**
 * Typed payload for the bgimage element.
 *
 * File metadata fields are nullable; they are absent when no image has been selected.
 * to_array() omits null values to preserve the existing JSON structure.
 *
 * @package    customcertelement_bgimage
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bgimage_payload implements element_payload_interface {
    /**
     * Construct a bgimage_payload.
     *
     * @param int|null $contextid File context ID, or null if no image selected.
     * @param string|null $filearea File area, or null if no image selected.
     * @param int|null $itemid File item ID, or null if no image selected.
     * @param string|null $filepath File path, or null if no image selected.
     * @param string|null $filename File name, or null if no image selected.
     */
    public function __construct(
        /** @var int|null File context ID, or null if no image selected. */
        public readonly ?int $contextid,
        /** @var string|null File area, or null if no image selected. */
        public readonly ?string $filearea,
        /** @var int|null File item ID, or null if no image selected. */
        public readonly ?int $itemid,
        /** @var string|null File path, or null if no image selected. */
        public readonly ?string $filepath,
        /** @var string|null File name, or null if no image selected. */
        public readonly ?string $filename,
    ) {
    }

    /**
     * Construct a bgimage_payload from a decoded data array.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        $hasfile = isset(
            $data['contextid'],
            $data['filearea'],
            $data['itemid'],
            $data['filepath'],
            $data['filename']
        );
        return new static(
            contextid: $hasfile ? (int)$data['contextid'] : null,
            filearea: $hasfile ? (string)$data['filearea'] : null,
            itemid: $hasfile ? (int)$data['itemid'] : null,
            filepath: $hasfile ? (string)$data['filepath'] : null,
            filename: $hasfile ? (string)$data['filename'] : null,
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * File metadata keys are omitted when null to preserve the existing JSON structure.
     *
     * @return array
     */
    public function to_array(): array {
        if ($this->contextid === null) {
            return [];
        }
        return [
            'contextid' => $this->contextid,
            'filearea' => $this->filearea,
            'itemid' => $this->itemid,
            'filepath' => $this->filepath,
            'filename' => $this->filename,
        ];
    }

    /**
     * Enforce the all-or-none file metadata invariant.
     *
     * Either all five file metadata fields must be set, or none of them.
     *
     * @return void
     * @throws \coding_exception If only some file metadata fields are present.
     */
    public function validate(): void {
        $fields = [$this->contextid, $this->filearea, $this->itemid, $this->filepath, $this->filename];
        $set = array_filter($fields, fn($v) => $v !== null);
        if (count($set) > 0 && count($set) < 5) {
            throw new \coding_exception('bgimage_payload: file metadata fields must all be set or all be null.');
        }
    }
}

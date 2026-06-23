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
 * Typed payload for the image element.
 *
 * @package    customcertelement_image
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_image;

use mod_customcert\element\element_payload_interface;

/**
 * Typed payload for the image element.
 *
 * File metadata fields and alphachannel are nullable; they are absent when no image
 * has been selected or no alpha value was set. to_array() omits null values to
 * preserve the existing JSON structure.
 *
 * @package    customcertelement_image
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_payload implements element_payload_interface {
    /**
     * Construct an image_payload.
     *
     * @param int $width Width in mm.
     * @param int $height Height in mm.
     * @param float|null $alphachannel Alpha transparency value, or null if not set.
     * @param int|null $contextid File context ID, or null if no image selected.
     * @param string|null $filearea File area, or null if no image selected.
     * @param int|null $itemid File item ID, or null if no image selected.
     * @param string|null $filepath File path, or null if no image selected.
     * @param string|null $filename File name, or null if no image selected.
     */
    public function __construct(
        /** @var int Width in mm. */
        public readonly int $width,
        /** @var int Height in mm. */
        public readonly int $height,
        /** @var float|null Alpha transparency value, or null if not set. */
        public readonly ?float $alphachannel,
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
     * Construct an image_payload from a decoded data array.
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
            width: (int)($data['width'] ?? 0),
            height: (int)($data['height'] ?? 0),
            alphachannel: isset($data['alphachannel']) ? (float)$data['alphachannel'] : null,
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
     * Null fields are omitted to preserve the existing JSON structure.
     *
     * @return array
     */
    public function to_array(): array {
        $data = [
            'width' => $this->width,
            'height' => $this->height,
        ];
        if ($this->alphachannel !== null) {
            $data['alphachannel'] = $this->alphachannel;
        }
        if ($this->contextid !== null) {
            $data['contextid'] = $this->contextid;
            $data['filearea'] = $this->filearea;
            $data['itemid'] = $this->itemid;
            $data['filepath'] = $this->filepath;
            $data['filename'] = $this->filename;
        }
        return $data;
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
            throw new \coding_exception('image_payload: file metadata fields must all be set or all be null.');
        }
    }
}

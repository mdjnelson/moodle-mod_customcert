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
 * Typed payload for the userpicture element.
 *
 * @package    customcertelement_userpicture
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_userpicture;

use mod_customcert\element\element_payload_interface;

/**
 * Typed payload for the userpicture element.
 *
 * @package    customcertelement_userpicture
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class userpicture_payload implements element_payload_interface {
    /**
     * Construct a userpicture_payload.
     *
     * @param int $width Width in mm.
     * @param int $height Height in mm.
     */
    public function __construct(
        /** @var int Width in mm. */
        public readonly int $width,
        /** @var int Height in mm. */
        public readonly int $height,
    ) {
    }

    /**
     * Construct a userpicture_payload from a decoded data array.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        return new static(
            width: (int)($data['width'] ?? 0),
            height: (int)($data['height'] ?? 0),
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * @return array{width:int,height:int}
     */
    public function to_array(): array {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * No invariants to check beyond type safety.
     *
     * @return void
     */
    public function validate(): void {
    }
}

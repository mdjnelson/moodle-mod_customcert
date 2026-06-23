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
 * Typed payload for the date element.
 *
 * @package    customcertelement_date
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_date;

use mod_customcert\element\element_payload_interface;
use mod_customcert\element\stylable_payload;

/**
 * Typed payload for the date element.
 *
 * @package    customcertelement_date
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_payload implements element_payload_interface {
    /**
     * Construct a date_payload.
     *
     * @param string $dateitem The date item identifier.
     * @param string $dateformat The date format string.
     * @param stylable_payload $style The four standard visual style fields.
     */
    public function __construct(
        /** @var string The date item identifier. */
        public readonly string $dateitem,
        /** @var string The date format string. */
        public readonly string $dateformat,
        /** @var stylable_payload The four standard visual style fields. */
        public readonly stylable_payload $style,
    ) {
    }

    /**
     * Construct a date_payload from a decoded data array.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        return new static(
            dateitem: (string)($data['dateitem'] ?? ''),
            dateformat: (string)($data['dateformat'] ?? ''),
            style: stylable_payload::from_array($data),
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * @return array{dateitem:string,dateformat:string,font:string,fontsize:int,colour:string,width:int}
     */
    public function to_array(): array {
        return array_merge(
            [
                'dateitem' => $this->dateitem,
                'dateformat' => $this->dateformat,
            ],
            $this->style->to_array(),
        );
    }

    /**
     * No invariants to check beyond type safety.
     *
     * @return void
     */
    public function validate(): void {
    }
}

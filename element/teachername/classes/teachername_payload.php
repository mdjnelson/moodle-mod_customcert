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
 * Typed payload for the teachername element.
 *
 * @package    customcertelement_teachername
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_teachername;

use mod_customcert\element\element_payload_interface;
use mod_customcert\element\stylable_payload;

/**
 * Typed payload for the teachername element.
 *
 * @package    customcertelement_teachername
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teachername_payload implements element_payload_interface {
    /**
     * Construct a teachername_payload.
     *
     * @param string $teacher The teacher role identifier.
     * @param stylable_payload $style The four standard visual style fields.
     */
    public function __construct(
        /** @var string The teacher role identifier. */
        public readonly string $teacher,
        /** @var stylable_payload The four standard visual style fields. */
        public readonly stylable_payload $style,
    ) {
    }

    /**
     * Construct a teachername_payload from a decoded data array.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        return new static(
            teacher: (string)($data['teacher'] ?? ''),
            style: stylable_payload::from_array($data),
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * @return array{teacher:string,font:string,fontsize:int,colour:string,width:int}
     */
    public function to_array(): array {
        return array_merge(
            ['teacher' => $this->teacher],
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

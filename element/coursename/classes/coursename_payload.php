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
 * Typed payload for the coursename element.
 *
 * @package    customcertelement_coursename
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);
namespace customcertelement_coursename;

use coding_exception;
use mod_customcert\element\element_payload_interface;
use mod_customcert\element\stylable_payload;

/**
 * Typed payload for the coursename element.
 *
 * Encapsulates all data stored in the `customcert_elements.data` JSON column for
 * a coursename element. Using this class instead of raw arrays makes the valid
 * keys, their types, and their constraints explicit and statically analysable.
 *
 * The database still stores plain JSON; this class is the PHP-side contract only.
 *
 * @package    customcertelement_coursename
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursename_payload implements element_payload_interface {
    /**
     * Construct a coursename_payload with the given field values.
     *
     * @param int $coursenamedisplay One of element::COURSE_FULL_NAME or element::COURSE_SHORT_NAME.
     * @param stylable_payload $style The four standard visual style fields.
     */
    public function __construct(
        /** @var int One of element::COURSE_FULL_NAME or element::COURSE_SHORT_NAME. */
        public readonly int $coursenamedisplay,
        /** @var stylable_payload The four standard visual style fields. */
        public readonly stylable_payload $style,
    ) {
    }

    /**
     * Construct a coursename_payload from a decoded data array.
     *
     * Missing keys receive safe defaults; values are cast to their canonical types.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        return new static(
            coursenamedisplay: (int)($data['coursenamedisplay'] ?? element::COURSE_FULL_NAME),
            style: stylable_payload::from_array($data),
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * @return array{coursenamedisplay:int,font:string,fontsize:int,colour:string,width:int}
     */
    public function to_array(): array {
        return array_merge(
            ['coursenamedisplay' => $this->coursenamedisplay],
            $this->style->to_array(),
        );
    }

    /**
     * Assert that the payload values are internally consistent.
     *
     * @return void
     * @throws coding_exception when coursenamedisplay is not a recognised constant.
     */
    public function validate(): void {
        $valid = [element::COURSE_FULL_NAME, element::COURSE_SHORT_NAME];
        if (!in_array($this->coursenamedisplay, $valid, true)) {
            throw new coding_exception(
                'coursename_payload: coursenamedisplay must be one of ' .
                implode(', ', $valid) . '; got ' . $this->coursenamedisplay
            );
        }
    }
}

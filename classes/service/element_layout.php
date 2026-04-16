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
 * DTO for the layout columns of a certificate element.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\service;

use mod_customcert\element as element_base;
use stdClass;

/**
 * Immutable value object for the layout columns stored in customcert_elements.
 */
final class element_layout {
    /**
     * Constructor.
     *
     * @param int|null $posx X coordinate in mm, or null when not set.
     * @param int|null $posy Y coordinate in mm, or null when not set.
     * @param int|null $refpoint Reference-point constant (see element_helper::CUSTOMCERT_REF_POINT_*), or null.
     * @param string $alignment Text alignment: 'L', 'C', or 'R'.
     */
    public function __construct(
        /** @var int|null X coordinate in mm, or null when not set. */
        public readonly ?int $posx,
        /** @var int|null Y coordinate in mm, or null when not set. */
        public readonly ?int $posy,
        /** @var int|null Reference-point constant, or null. */
        public readonly ?int $refpoint,
        /** @var string Text alignment: 'L', 'C', or 'R'. */
        public readonly string $alignment = element_base::ALIGN_LEFT,
    ) {
    }

    /**
     * Build a layout DTO from a raw DB record or form-data object.
     *
     * Missing or empty fields are treated as null / the default alignment.
     *
     * @param stdClass $record
     * @return self
     */
    public static function from_record(stdClass $record): self {
        $posx = isset($record->posx) && $record->posx !== '' ? (int)$record->posx : null;
        $posy = isset($record->posy) && $record->posy !== '' ? (int)$record->posy : null;
        $refpoint = isset($record->refpoint) && $record->refpoint !== '' ? (int)$record->refpoint : null;
        $alignment = isset($record->alignment) && $record->alignment !== ''
            ? (string)$record->alignment
            : element_base::ALIGN_LEFT;

        return new self($posx, $posy, $refpoint, $alignment);
    }
}

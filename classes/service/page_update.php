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

namespace mod_customcert\service;

/**
 * Immutable value object for updating page dimensions/margins.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_update {
    /** @var int Page width in millimetres. */
    public readonly int $width;

    /** @var int Page height in millimetres. */
    public readonly int $height;

    /** @var int Left margin in millimetres. */
    public readonly int $leftmargin;

    /** @var int Right margin in millimetres. */
    public readonly int $rightmargin;

    /** @var int Last modified timestamp. */
    public readonly int $timemodified;

    /**
     * Constructor for initializing the object with specified dimensions and margins.
     *
     * @param int $width The width of the object.
     * @param int $height The height of the object.
     * @param int $leftmargin The left margin of the object.
     * @param int $rightmargin The right margin of the object.
     * @param int $timemodified Optional parameter for the last modified time; if not provided, defaults to the current time.
     * @return void
     */
    public function __construct(
        int $width,
        int $height,
        int $leftmargin,
        int $rightmargin,
        int $timemodified = 0,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->leftmargin = $leftmargin;
        $this->rightmargin = $rightmargin;
        $this->timemodified = $timemodified ?: time();
    }
}

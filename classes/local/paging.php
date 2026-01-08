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

namespace mod_customcert\local;

/**
 * Simple value object to represent paging instructions.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class paging {
    /**
     * Immutable paging parameters.
     *
     * @var int $offset
     */
    public readonly int $offset;

    /**
     * Maximum number of records to return.
     *
     * @var int $limit
     */
    public readonly int $limit;

    /**
     * Construct paging with non-negative offset and limit.
     *
     * @param int $offset Zero-based offset
     * @param int $limit Maximum number of records
     */
    public function __construct(int $offset, int $limit) {
        // Normalise to non-negative values before assignment to readonly props.
        $this->offset = max(0, $offset);
        $this->limit  = max(0, $limit);
    }

    /**
     * Get the starting offset.
     *
     * @return int
     */
    public function get_offset(): int {
        return $this->offset;
    }

    /**
     * Get the maximum number of records.
     *
     * @return int
     */
    public function get_limit(): int {
        return $this->limit;
    }
}

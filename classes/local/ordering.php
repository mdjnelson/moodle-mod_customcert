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
 * Simple value object to represent ordering instructions.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ordering {
    /** @var array<string, 'ASC'|'DESC'> */
    private readonly array $fields;

    /**
     * Constructor.
     *
     * @param array $fields
     */
    public function __construct(array $fields) {
        $normalised = [];

        foreach ($fields as $k => $v) {
            if (is_int($k) && is_array($v) && isset($v[0], $v[1])) {
                $field = trim((string)$v[0]);
                $dir = strtoupper((string)$v[1]);
            } else {
                $field = trim((string)$k);
                $dir = strtoupper((string)$v);
            }

            if ($field === '') {
                continue;
            }

            $dir = $dir === 'DESC' ? 'DESC' : 'ASC';
            $normalised[$field] = $dir;
        }

        $this->fields = $normalised;
    }

    /**
     * Converts the fields and their respective sorting directions into a SQL-compliant ORDER BY clause.
     *
     * @param bool $throwoninvalid Whether to throw an exception for invalid field names.
     *   If set to true, an InvalidArgumentException will be thrown when a field name
     *   does not match the expected pattern.
     * @return string The generated SQL ORDER BY clause as a string. Invalid field names will be excluded
     *   unless $throwoninvalid is true.
     * @throws \InvalidArgumentException If $throwoninvalid is true and an invalid field name is
     *   encountered.
     */
    public function to_sql(bool $throwoninvalid = false): string {
        $parts = [];

        foreach ($this->fields as $field => $dir) {
            // Decide whether you really want dot support.
            $ok = preg_match('/^[a-z0-9_\.]+$/i', $field);

            if (!$ok) {
                if ($throwoninvalid) {
                    throw new \InvalidArgumentException("Invalid ORDER BY field: {$field}");
                }
                continue;
            }

            $parts[] = $field . ' ' . $dir;
        }

        return implode(', ', $parts);
    }
}

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
 * Contains the class that provides a grade object to be used by elements for display purposes.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

/**
 * The class that provides a grade object to be used by elements for display purposes.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_information {
    /**
     * @var string The grade name.
     */
    protected string $name;

    /**
     * @var float The raw grade.
     */
    protected ?float $grade;

    /**
     * @var string The grade to display
     */
    protected string $displaygrade;

    /**
     * @var int|null The date it was graded.
     */
    protected ?int $dategraded;

    /**
     * The constructor.
     *
     * @param string $name
     * @param float|null $grade
     * @param string $displaygrade
     * @param int|null $dategraded
     */
    public function __construct(string $name, ?float $grade, string $displaygrade, ?int $dategraded) {
        $this->name = $name;
        $this->grade = $grade;
        $this->displaygrade = $displaygrade;
        $this->dategraded = $dategraded;
    }

    /**
     * Returns the name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Returns the raw grade.
     *
     * @return float|null
     */
    public function get_grade(): ?float {
        return $this->grade;
    }

    /**
     * Returns the display grade.
     *
     * @return string
     */
    public function get_displaygrade(): string {
        return $this->displaygrade;
    }

    /**
     * Returns the date it was graded.
     *
     * @return int|null
     */
    public function get_dategraded(): ?int {
        return $this->dategraded;
    }
}

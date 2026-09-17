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
 * Testable generate_site_certificates_download_task fixture for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\task;

use mod_customcert\certificate;

/**
 * A testable generate_site_certificates_download_task that allows injecting a stubbed
 * zip factory so failure paths can be exercised without touching production code.
 */
class testable_generate_site_certificates_download_task extends generate_site_certificates_download_task {
    /** @var callable|null Factory returning a (possibly failing) zip_archive. */
    public $zipfactory;

    /**
     * Return generation result using the injected zip factory.
     *
     * @param callable|null $zipfactory
     * @param callable|null $requestdirfactory
     * @return array{path: string, filename: string}|null
     */
    protected function generate_site_certificates_zip(
        ?callable $zipfactory = null,
        ?callable $requestdirfactory = null
    ): ?array {
        return certificate::generate_all_for_site_zip($this->zipfactory, $requestdirfactory);
    }
}

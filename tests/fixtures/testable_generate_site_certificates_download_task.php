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

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\service\certificate_download_service;
use mod_customcert\task\generate_site_certificates_download_task;

/**
 * A testable generate_site_certificates_download_task that allows injecting a stubbed
 * certificate_download_service so failure paths can be exercised without touching production code.
 */
final class testable_generate_site_certificates_download_task extends generate_site_certificates_download_task {
    /** @var certificate_download_service The stubbed service to return for this test. */
    public certificate_download_service $service;

    /**
     * Return the stubbed download service injected for this test.
     *
     * @return certificate_download_service
     */
    protected function create_download_service(): certificate_download_service {
        return $this->service;
    }
}

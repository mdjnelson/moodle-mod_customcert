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
 * Testable generate_site_certificates_download_task fixture.
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
 * A testable subclass allowing a stubbed download service to be injected.
 *
 * Used to simulate download-service failures (zip open, entry, and finalisation failures)
 * without relying on an anonymous class.
 */
final class testable_generate_site_certificates_download_task extends generate_site_certificates_download_task {
    /** @var certificate_download_service Download service supplied by the test. */
    private certificate_download_service $downloadservice;

    /**
     * Set the download service used by the task.
     *
     * @param certificate_download_service $downloadservice Download service.
     * @return void
     */
    public function set_download_service(certificate_download_service $downloadservice): void {
        $this->downloadservice = $downloadservice;
    }

    /**
     * Create the certificate download service.
     *
     * @return certificate_download_service
     */
    protected function create_download_service(): certificate_download_service {
        return $this->downloadservice;
    }
}

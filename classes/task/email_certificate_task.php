<?php
// This file is part of Moodle - http://moodle.org/
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
 * An adhoc task for emailing certificates.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert\task;

use core\task\adhoc_task;
use mod_customcert\service\certificate_email_service;

/**
 * An adhoc task for emailing certificates per issue.
 *
 * @package    mod_customcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_certificate_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskemailcertificate', 'customcert');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        $customdata = $this->get_custom_data();
        if (empty($customdata) || empty($customdata->issueid) || empty($customdata->customcertid)) {
            return;
        }
        $service = new certificate_email_service();
        $service->send_issue((int)$customdata->customcertid, (int)$customdata->issueid);
    }
}

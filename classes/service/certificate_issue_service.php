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

declare(strict_types=1);

namespace mod_customcert\service;

use context_module;
use mod_customcert\event\issue_created;
use stdClass;

/**
 * Issues certificates and generates issue codes.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_issue_service {
    /**
     * @var \moodle_database
     */
    private \moodle_database $db;

    /**
     * @var callable
     */
    private $timeprovider;

    /**
     * Create a certificate_issue_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        global $DB;
        return new self($DB);
    }

    /**
     * certificate_issue_service constructor.
     *
     * @param \moodle_database $db
     * @param callable|null $timeprovider
     */
    public function __construct(\moodle_database $db, ?callable $timeprovider = null) {
        $this->db = $db;
        $this->timeprovider = $timeprovider ?? static fn(): int => time();
    }

    /**
     * Issues a certificate to a user.
     *
     * @param int $certificateid The ID of the certificate
     * @param int $userid The ID of the user to issue the certificate to
     * @return int The ID of the issue
     */
    public function issue_certificate(int $certificateid, int $userid): int {
        $issue = new stdClass();
        $issue->userid = $userid;
        $issue->customcertid = $certificateid;
        $issue->code = $this->generate_code();
        $issue->emailed = 0;
        $issue->timecreated = ($this->timeprovider)();

        // Insert the record into the database.
        $issueid = $this->db->insert_record('customcert_issues', $issue);

        // Get course module context for event.
        $cm = get_coursemodule_from_instance('customcert', $certificateid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Trigger event.
        $event = issue_created::create([
            'objectid' => $issueid,
            'context' => $context,
            'relateduserid' => $userid,
        ]);
        $event->trigger();

        return $issueid;
    }

    /**
     * Generates an unused code of random letters and numbers.
     *
     * @return string
     * @throws \moodle_exception If a unique code cannot be generated after the maximum number of attempts.
     */
    public function generate_code(): string {
        // Get the user's selected method from settings.
        $method = get_config('customcert', 'codegenerationmethod');

        $maxattempts = 10;
        for ($i = 0; $i < $maxattempts; $i++) {
            $code = match ($method) {
                '0' => $this->generate_code_upper_lower_digits(),
                '1' => $this->generate_code_digits_with_hyphens(),
                default => $this->generate_code_upper_lower_digits(),
            };
            if (!$this->db->record_exists('customcert_issues', ['code' => $code])) {
                return $code;
            }
        }

        throw new \moodle_exception('Could not generate a unique certificate code after ' . $maxattempts . ' attempts.');
    }

    /**
     * Generate a random code of the format XXXXXXXXXX, where each X is a character from the set [A-Za-z0-9].
     * Does not check that it is unused.
     *
     * @return string
     */
    private function generate_code_upper_lower_digits(): string {
        return random_string(10);
    }

    /**
     * Generate an random code of the format XXXX-XXXX-XXXX, where each X is a random digit.
     * Does not check that it is unused.
     *
     * @return string
     */
    private function generate_code_digits_with_hyphens(): string {
        return sprintf(
            '%04d-%04d-%04d',
            random_int(0, 9999),
            random_int(0, 9999),
            random_int(0, 9999)
        );
    }
}

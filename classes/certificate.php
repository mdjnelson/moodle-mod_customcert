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
 * Provides functionality needed by customcert activities.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use mod_customcert\service\certificate_download_service;
use mod_customcert\service\form_service;
use mod_customcert\service\certificate_repository;
use mod_customcert\service\issue_repository;
use mod_customcert\service\certificate_issue_service;
use mod_customcert\service\certificate_time_service;
use stdClass;

/**
 * Class certificate.
 *
 * Helper functionality for certificates.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate {
    /**
     * Send the file inline to the browser.
     *
     * @deprecated since Moodle 5.2
     */
    public const string DELIVERY_OPTION_INLINE = 'I';

    /**
     * Send to the browser and force a file download.
     *
     * @deprecated since Moodle 5.2
     */
    public const string DELIVERY_OPTION_DOWNLOAD = 'D';

    /**
     * @var string the print protection variable
     *
     * @deprecated since Moodle 5.2
     */
    public const string PROTECTION_PRINT = 'print';

    /**
     * @var string the modify protection variable
     *
     * @deprecated since Moodle 5.2
     */
    public const string PROTECTION_MODIFY = 'modify';

    /**
     * @var string the copy protection variable
     *
     * @deprecated since Moodle 5.2
     */
    public const string PROTECTION_COPY = 'copy';

    /**
     * @var int the number of issues that will be displayed on each page in the report
     *      If you want to display all customcerts on a page set this to 0.
     *
     * @deprecated since Moodle 5.2
     */
    public const int CUSTOMCERT_PER_PAGE = 50;

    /**
     * Handles setting the protection field for the customcert.
     *
     * @deprecated since Moodle 5.2
     * @param stdClass $data
     * @return string the value to insert into the protection field
     */
    public static function set_protection(stdClass $data): string {
        debugging(
            'certificate::set_protection() is deprecated since Moodle 5.2. '
            . 'Use form_service::set_protection() instead.',
            DEBUG_DEVELOPER
        );

        return form_service::set_protection($data);
    }

    /**
     * Handles uploading an image for the customcert module.
     *
     * @deprecated since Moodle 5.2
     * @param int $draftitemid the draft area containing the files
     * @param int $contextid the context we are storing this image in
     * @param string $filearea identifies the file area.
     */
    public static function upload_files(int $draftitemid, int $contextid, string $filearea = 'image'): void {
        debugging(
            'certificate::upload_files() is deprecated since Moodle 5.2. '
            . 'Use form_service::upload_files() instead.',
            DEBUG_DEVELOPER
        );

        form_service::upload_files($draftitemid, $contextid, $filearea);
    }

    /**
     * Return the list of possible fonts to use.
     *
     * @deprecated since Moodle 5.2
     * @return array
     */
    public static function get_fonts(): array {
        debugging(
            'certificate::get_fonts() is deprecated since Moodle 5.2. '
            . 'Use element_helper::get_fonts() instead.',
            DEBUG_DEVELOPER
        );

        return element_helper::get_fonts();
    }

    /**
     * Return the list of possible font sizes to use.
     *
     * @deprecated since Moodle 5.2
     * @return array
     */
    public static function get_font_sizes(): array {
        debugging(
            'certificate::get_font_sizes() is deprecated since Moodle 5.2. '
            . 'Use element_helper::get_font_sizes() instead.',
            DEBUG_DEVELOPER
        );

        return element_helper::get_font_sizes();
    }

    /**
     * Get the time the user has spent in the course.
     *
     * @deprecated since Moodle 5.2
     * @param int $courseid
     * @param int $userid
     * @return int the total time spent in seconds
     */
    public static function get_course_time(int $courseid, int $userid = 0): int {
        debugging(
            'certificate::get_course_time() is deprecated since Moodle 5.2. '
            . 'Use certificate_time_service::get_course_time() instead.',
            DEBUG_DEVELOPER
        );

        $service = certificate_time_service::create();
        return $service->get_course_time($courseid, $userid);
    }

    /**
     * Download all certificate issues.
     *
     * @deprecated since Moodle 5.2
     * @param template $template
     * @param array $issues
     * @return void
     * @throws \moodle_exception
     */
    public static function download_all_issues_for_instance(template $template, array $issues): void {
        debugging(
            'certificate::download_all_issues_for_instance() is deprecated since Moodle 5.2. '
            . 'Use certificate_download_service::download_all_issues_for_instance() instead.',
            DEBUG_DEVELOPER
        );

        $service = certificate_download_service::create();
        $service->download_all_issues_for_instance($template, $issues);
    }

    /**
     * Download all certificates on the site.
     *
     * @deprecated since Moodle 5.2
     * @return void
     */
    public static function download_all_for_site(): void {
        debugging(
            'certificate::download_all_for_site() is deprecated since Moodle 5.2. '
            . 'Use certificate_download_service::download_all_for_site() instead.',
            DEBUG_DEVELOPER
        );

        $service = certificate_download_service::create();
        $service->download_all_for_site();
    }

    /**
     * Returns a list of issued customcerts.
     *
     * @deprecated since Moodle 5.2
     * @param int $customcertid
     * @param int $groupmode the group mode
     * @param stdClass $cm the course module
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @return array the users
     */
    public static function get_issues(
        int $customcertid,
        int $groupmode,
        stdClass $cm,
        int $limitfrom,
        int $limitnum,
        string $sort = ''
    ): array {
        debugging(
            'certificate::get_issues() is deprecated since Moodle 5.2. '
            . 'Use issue_repository::get_issues() instead.',
            DEBUG_DEVELOPER
        );

        $repo = new issue_repository();
        return $repo->get_issues($customcertid, $cm, $limitfrom, $limitnum, $sort);
    }

    /**
     * Returns the total number of issues for a given customcert.
     *
     * @deprecated since Moodle 5.2
     * @param int $customcertid
     * @param stdClass $cm the course module
     * @param int $groupmode the group mode
     */
    public static function get_number_of_issues(int $customcertid, stdClass $cm, int $groupmode): int {
        debugging(
            'certificate::get_number_of_issues() is deprecated since Moodle 5.2. '
            . 'Use issue_repository::get_number_of_issues() instead.',
            DEBUG_DEVELOPER
        );

        $repo = new issue_repository();
        return $repo->get_number_of_issues($customcertid, $cm);
    }

    /**
     * Returns an array of the conditional variables to use in the get_issues SQL query.
     *
     * @deprecated since Moodle 5.2
     * @param stdClass $cm the course module
     * @param int $groupmode the group mode
     * @return array the conditional variables
     */
    public static function get_conditional_issues_sql(stdClass $cm, int $groupmode): array {
        debugging(
            'certificate::get_conditional_issues_sql() is deprecated since Moodle 5.2. '
            . 'Use issue_repository::get_conditional_issues_sql() instead.',
            DEBUG_DEVELOPER
        );

        $repo = new issue_repository();
        return $repo->get_conditional_issues_sql($cm);
    }

    /**
     * Get number of certificates for a user.
     *
     * @deprecated since Moodle 5.2
     * @param int $userid
     * @return int
     */
    public static function get_number_of_certificates_for_user(int $userid): int {
        debugging(
            'certificate::get_number_of_certificates_for_user() is deprecated since Moodle 5.2. '
            . 'Use certificate_repository::get_number_of_certificates_for_user() instead.',
            DEBUG_DEVELOPER
        );

        $repo = new certificate_repository();
        return $repo->get_number_of_certificates_for_user($userid);
    }

    /**
     * Gets the certificates for the user.
     *
     * @deprecated since Moodle 5.2
     * @param int $userid
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @return array
     */
    public static function get_certificates_for_user(int $userid, int $limitfrom, int $limitnum, string $sort = ''): array {
        debugging(
            'certificate::get_certificates_for_user() is deprecated since Moodle 5.2. '
            . 'Use certificate_repository::get_certificates_for_user() instead.',
            DEBUG_DEVELOPER
        );

        $repo = new certificate_repository();
        return $repo->get_certificates_for_user($userid, $limitfrom, $limitnum, $sort);
    }

    /**
     * Issues a certificate to a user.
     *
     * @deprecated since Moodle 5.2
     * @param int $certificateid The ID of the certificate
     * @param int $userid The ID of the user to issue the certificate to
     * @return int The ID of the issue
     */
    public static function issue_certificate(int $certificateid, int $userid): int {
        debugging(
            'certificate::issue_certificate() is deprecated since Moodle 5.2. '
            . 'Use certificate_issue_service::issue_certificate() instead.',
            DEBUG_DEVELOPER
        );

        $service = certificate_issue_service::create();
        return $service->issue_certificate($certificateid, $userid);
    }

    /**
     * Generates an unused code of random letters and numbers.
     *
     * @deprecated since Moodle 5.2
     * @return string
     */
    public static function generate_code(): string {
        debugging(
            'certificate::generate_code() is deprecated since Moodle 5.2. Use certificate_issue_service::generate_code() instead.',
            DEBUG_DEVELOPER
        );

        $service = certificate_issue_service::create();
        return $service->generate_code();
    }
}

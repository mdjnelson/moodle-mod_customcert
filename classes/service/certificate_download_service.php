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

use core_user\fields;
use mod_customcert\template;
use moodle_database;
use zip_archive;

/**
 * Handles downloading certificates as ZIP archives.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_download_service {
    /**
     * Date format in filename for download all zip file.
     */
    private const string ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES_DATE_FORMAT = '%Y%m%d%H%M%S';

    /**
     * The ending part of the name of the zip file.
     */
    private const string ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES = 'all_certificates.zip';

    /**
     * @var pdf_generation_service
     */
    private pdf_generation_service $pdfservice;

    /**
     * @var moodle_database
     */
    private moodle_database $db;

    /**
     * @var callable
     */
    private $zipfactory;

    /**
     * @var callable
     */
    private $sendfile;

    /**
     * Create a certificate_download_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        global $DB;
        return new self(pdf_generation_service::create(), $DB);
    }

    /**
     * certificate_download_service constructor.
     *
     * @param pdf_generation_service $pdfservice
     * @param moodle_database|null $db
     * @param callable|null $zipfactory
     * @param callable|null $sendfile
     */
    public function __construct(
        pdf_generation_service $pdfservice,
        ?moodle_database $db = null,
        ?callable $zipfactory = null,
        ?callable $sendfile = null
    ) {
        global $DB;
        $this->pdfservice = $pdfservice;
        $this->db = $db ?? $DB;
        $this->zipfactory = $zipfactory ?? static fn(): zip_archive => new zip_archive();
        $this->sendfile = $sendfile ?? static function (string $path, string $name): void {
            send_file($path, $name);
            exit();
        };
    }

    /**
     * Download all certificate issues for a single instance.
     *
     * @param template $template
     * @param array $issues
     * @return void
     * @throws \moodle_exception
     */
    public function download_all_issues_for_instance(template $template, array $issues): void {
        $zipdir = make_request_directory();
        if (!$zipdir) {
            return;
        }

        $zipfilenameprefix = userdate(time(), self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES_DATE_FORMAT);
        $zipfilename = $zipfilenameprefix . "_" . self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES;
        $zipfullpath = $zipdir . DIRECTORY_SEPARATOR . $zipfilename;

        $ziparchive = ($this->zipfactory)();
        if ($ziparchive->open($zipfullpath)) {
            foreach ($issues as $issue) {
                $userfullname = str_replace(' ', '_', mb_strtolower(format_text(fullname($issue), FORMAT_PLAIN)));
                $pdfname = $userfullname . DIRECTORY_SEPARATOR . 'certificate.pdf';
                $filecontents = $this->pdfservice->generate_pdf($template, false, (int)$issue->id, true);
                $ziparchive->add_file_from_string($pdfname, $filecontents);
            }
            $ziparchive->close();
        }

        ($this->sendfile)($zipfullpath, $zipfilename);
    }

    /**
     * Download all certificates on the site.
     *
     * @return void
     */
    public function download_all_for_site(): void {
        [$namefields, $nameparams] = fields::get_sql_fullname();
        $sql = "SELECT ci.*, $namefields as fullname, ct.id as templateid, ct.name as templatename, ct.contextid
                  FROM {customcert_issues} ci
                  JOIN {user} u
                    ON ci.userid = u.id
                  JOIN {customcert} c
                    ON ci.customcertid = c.id
                  JOIN {customcert_templates} ct
                    ON c.templateid = ct.id";
        if ($issues = $this->db->get_records_sql($sql, $nameparams)) {
            $zipdir = make_request_directory();
            if (!$zipdir) {
                return;
            }

            $zipfilenameprefix = userdate(time(), self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES_DATE_FORMAT);
            $zipfilename = $zipfilenameprefix . "_" . self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES;
            $zipfullpath = $zipdir . DIRECTORY_SEPARATOR . $zipfilename;

            $ziparchive = ($this->zipfactory)();
            if ($ziparchive->open($zipfullpath)) {
                foreach ($issues as $issue) {
                    $template = template::load((int)$issue->templateid);

                    $ctname = str_replace(' ', '_', mb_strtolower($template->get_name()));
                    $userfullname = str_replace(' ', '_', mb_strtolower($issue->fullname));
                    $pdfname = $userfullname . DIRECTORY_SEPARATOR . $ctname . '_' . 'certificate.pdf';
                    $filecontents = $this->pdfservice->generate_pdf($template, false, (int)$issue->userid, true);
                    $ziparchive->add_file_from_string($pdfname, $filecontents);
                }
                $ziparchive->close();
            }

            ($this->sendfile)($zipfullpath, $zipfilename);
        }
    }
}

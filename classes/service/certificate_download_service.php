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
use moodle_exception;
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
     * The file area used to store the generated site-wide "download all certificates" zip archives.
     */
    public const string SITE_DOWNLOAD_FILEAREA = 'site_certificates_download';

    /**
     * How long a generated site-wide download archive is kept before being cleaned up, in seconds.
     *
     * Archives older than this lifetime are removed by the cleanup scheduled task.
     */
    public const int SITE_DOWNLOAD_FILE_LIFETIME = DAYSECS;

    /**
     * @var template_repository
     */
    private template_repository $templaterepo;

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
     * @var callable
     */
    private $requestdirfactory;

    /**
     * Create a certificate_download_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        global $DB;
        return new self(new template_repository(), pdf_generation_service::create(), $DB);
    }

    /**
     * certificate_download_service constructor.
     *
     * @param template_repository $templaterepo
     * @param pdf_generation_service $pdfservice
     * @param moodle_database $db
     * @param callable|null $zipfactory
     * @param callable|null $sendfile
     * @param callable|null $requestdirfactory Factory returning a writable temp directory path, or false on failure.
     */
    public function __construct(
        template_repository $templaterepo,
        pdf_generation_service $pdfservice,
        moodle_database $db,
        ?callable $zipfactory = null,
        ?callable $sendfile = null,
        ?callable $requestdirfactory = null
    ) {
        $this->templaterepo = $templaterepo;
        $this->pdfservice = $pdfservice;
        $this->db = $db;
        $this->zipfactory = $zipfactory ?? static fn(): zip_archive => new zip_archive();
        $this->sendfile = $sendfile ?? static function (string $path, string $name): void {
            send_file($path, $name);
            exit();
        };
        $this->requestdirfactory = $requestdirfactory ?? static function (): string|false {
            // Prefer returning false over throwing so callers can raise a domain exception.
            return make_request_directory(false);
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
     * @throws moodle_exception
     */
    public function download_all_for_site(): void {
        $zip = $this->generate_all_for_site_zip();
        if ($zip !== null) {
            ($this->sendfile)($zip['path'], $zip['filename']);
        }
    }

    /**
     * Generate a ZIP archive containing all certificates on the site.
     *
     * This builds the archive on disk without sending it, so it can be persisted (e.g. by an
     * asynchronous task) rather than streamed directly to the browser.
     *
     * @return array{path: string, filename: string}|null null if there are no certificates to include.
     * @throws moodle_exception If a temporary directory or zip archive cannot be created.
     */
    public function generate_all_for_site_zip(): ?array {
        [$namefields, $nameparams] = fields::get_sql_fullname();
        $sql = "SELECT ci.*, $namefields as fullname, ct.id as templateid, ct.name as templatename, ct.contextid
                  FROM {customcert_issues} ci
                  JOIN {user} u
                    ON ci.userid = u.id
                  JOIN {customcert} c
                    ON ci.customcertid = c.id
                  JOIN {customcert_templates} ct
                    ON c.templateid = ct.id";
        $issues = $this->db->get_recordset_sql($sql, $nameparams);

        $ziparchive = null;
        try {
            $zipfullpath = null;
            $zipfilename = null;
            /** @var array<int, template> $templates */
            $templates = [];
            $count = 0;

            foreach ($issues as $issue) {
                if ($count === 0) {
                    $zipdir = ($this->requestdirfactory)();
                    if (!$zipdir) {
                        throw new moodle_exception('errorcreatetempdir', 'customcert');
                    }

                    $zipfilenameprefix = userdate(time(), self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES_DATE_FORMAT);
                    $zipfilename = $zipfilenameprefix . "_" . self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES;
                    $zipfullpath = $zipdir . DIRECTORY_SEPARATOR . $zipfilename;

                    $candidatezip = ($this->zipfactory)();
                    if (!$candidatezip->open($zipfullpath)) {
                        throw new moodle_exception('errorcreatezip', 'customcert');
                    }
                    $ziparchive = $candidatezip;
                }

                $templateid = (int) $issue->templateid;
                if (!isset($templates[$templateid])) {
                    $templaterecord = $this->templaterepo->get_by_id_or_fail($templateid);
                    $templates[$templateid] = template::from_record($templaterecord);
                }
                $template = $templates[$templateid];

                $ctname = str_replace(' ', '_', mb_strtolower($template->get_name()));
                $userfullname = str_replace(' ', '_', mb_strtolower($issue->fullname));
                $pdfname = $userfullname . DIRECTORY_SEPARATOR . $ctname . '_' . 'certificate.pdf';
                $filecontents = $this->pdfservice->generate_pdf($template, false, (int) $issue->userid, true);
                if (!$ziparchive->add_file_from_string($pdfname, $filecontents)) {
                    throw new moodle_exception('errorcreatezip', 'customcert');
                }
                $count++;
            }

            if ($count === 0) {
                return null;
            }

            $closed = $ziparchive->close();
            $ziparchive = null;
            if (!$closed) {
                throw new moodle_exception('errorcreatezip', 'customcert');
            }

            return ['path' => $zipfullpath, 'filename' => $zipfilename];
        } finally {
            $issues->close();
            if ($ziparchive !== null) {
                $ziparchive->close();
            }
        }
    }
}

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
     * The file area used to store the generated "download all issued certificates" zip archives for an activity.
     */
    public const string INSTANCE_DOWNLOAD_FILEAREA = 'instance_certificates_download';

    /**
     * The name of the file, stored next to each activity archive, recording who the archive was generated for.
     *
     * It holds the group and the ids of the users whose certificates are in the archive, so access can be
     * checked again when the archive is downloaded. It is a file, rather than file metadata, so the number of
     * recipients is not limited by the size of a database column.
     */
    public const string INSTANCE_DOWNLOAD_MANIFEST = 'recipients.json';

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
        $zip = $this->generate_issues_zip($template, $issues);
        ($this->sendfile)($zip['path'], $zip['filename']);
    }

    /**
     * Generate a ZIP archive containing the certificates of the given users for a single instance.
     *
     * This builds the archive on disk without sending it, so it can be persisted (e.g. by an
     * asynchronous task) rather than streamed directly to the browser.
     *
     * @param template $template
     * @param array $users Records with the user's id and name fields, one certificate is generated for each.
     * @return array{path: string, filename: string}
     * @throws moodle_exception If a temporary directory or zip archive cannot be created, or a certificate cannot be added.
     */
    public function generate_issues_zip(template $template, array $users): array {
        $zipdir = ($this->requestdirfactory)();
        if (!$zipdir) {
            throw new moodle_exception('errorcreatetempdir', 'customcert');
        }

        $zipfilenameprefix = userdate(time(), self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES_DATE_FORMAT);
        $zipfilename = $zipfilenameprefix . "_" . self::ZIP_FILE_NAME_DOWNLOAD_ALL_CERTIFICATES;
        $zipfullpath = $zipdir . DIRECTORY_SEPARATOR . $zipfilename;

        $ziparchive = ($this->zipfactory)();
        if (!$ziparchive->open($zipfullpath)) {
            throw new moodle_exception('errorcreatezip', 'customcert');
        }

        try {
            $usednames = [];
            foreach ($users as $user) {
                $userdirectory = $this->get_unique_user_directory(
                    format_text(fullname($user), FORMAT_PLAIN),
                    (int) $user->id,
                    $usednames
                );
                $filecontents = $this->pdfservice->generate_pdf($template, false, (int) $user->id, true);
                if (!$ziparchive->add_file_from_string($userdirectory . DIRECTORY_SEPARATOR . 'certificate.pdf', $filecontents)) {
                    throw new moodle_exception('errorcreatezip', 'customcert');
                }
            }
        } catch (\Throwable $e) {
            $ziparchive->close();
            throw $e;
        }
        if (!$ziparchive->close()) {
            throw new moodle_exception('errorcreatezip', 'customcert');
        }

        return ['path' => $zipfullpath, 'filename' => $zipfilename];
    }

    /**
     * Build a safe archive directory name for a user, unique within the archive.
     *
     * Learners sharing a name get their user id appended so their certificates cannot overwrite each other.
     * The result is checked against every name already used, including ones produced this way, and extended
     * with a counter if it is still taken.
     *
     * @param string $fullname The user's full name.
     * @param int $userid The user's id.
     * @param array $usednames Names already used in the archive, as array keys. Updated by reference.
     * @return string
     */
    private function get_unique_user_directory(string $fullname, int $userid, array &$usednames): string {
        $base = clean_filename(str_replace(' ', '_', mb_strtolower($fullname)));
        if (trim($base, '._') === '') {
            $base = 'user';
        }

        $name = $base;
        if (isset($usednames[$name])) {
            $name = $base . '_' . $userid;
        }
        for ($counter = 2; isset($usednames[$name]); $counter++) {
            $name = $base . '_' . $userid . '_' . $counter;
        }
        $usednames[$name] = true;

        return $name;
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
        $sql = "SELECT ci.*, $namefields as fullname, ct.id as templateid, ct.contextid, c.name as customcertname
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

                // Use the activity name (not the template name, which may be shared) and the
                // issue id to guarantee a unique archive path even when activity names collide.
                $activityname = str_replace(' ', '_', mb_strtolower((string) ($issue->customcertname ?? '')));
                $userfullname = clean_filename(str_replace(' ', '_', mb_strtolower($issue->fullname))) ?: 'user';
                $pdfbasename = ($activityname !== '' ? $activityname . '_' : '') . 'certificate_' . $issue->id . '.pdf';
                $pdfname = $userfullname . DIRECTORY_SEPARATOR . clean_filename($pdfbasename);
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

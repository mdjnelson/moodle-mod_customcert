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

declare(strict_types=1);

namespace mod_customcert\service;

use dml_exception;
use mod_customcert\certificate;
use mod_customcert\local\preview_renderer;
use mod_customcert\template;
use pdf;
use stdClass;

/**
 * Handles PDF generation for customcert templates.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_generation_service {
    /** @var page_repository Repository for page records. */
    private page_repository $pages;

    /**
     * Create a pdf_generation_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        return new self(new page_repository());
    }

    /**
     * pdf_generation_service constructor.
     *
     * @param page_repository $pages
     */
    public function __construct(page_repository $pages) {
        $this->pages = $pages;
    }

    /**
     * Generate a PDF for the given template/user.
     *
     * @param template $template
     * @param bool $preview
     * @param int|null $userid
     * @param bool $return
     * @return string|void
     * @throws dml_exception
     */
    public function generate_pdf(template $template, bool $preview = false, ?int $userid = null, bool $return = false) {
        global $CFG, $DB, $USER;

        $userid = $userid !== null ? (int)$userid : null;
        $user = $userid !== null ? \core_user::get_user($userid) : $USER;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $pages = $this->pages->list_by_template($template->get_id());
        if (empty($pages)) {
            return $return ? '' : null;
        }

        $pdf = new pdf();

        $customcert = $DB->get_record('customcert', ['templateid' => $template->get_id()]) ?: null;

        $originallang = $this->apply_runtime_language_for_user($customcert, $user, false);

        $this->configure_pdf_for_customcert($pdf, $customcert);

        $deliveryoption = ($customcert && !empty($customcert->deliveryoption))
            ? $customcert->deliveryoption
            : certificate::DELIVERY_OPTION_INLINE;
        $filename = $this->compute_filename_for_user($template, $user, $customcert);

        $pdf->SetTitle($filename);

        $previewrenderer = new preview_renderer();
        foreach ($pages as $page) {
            $previewrenderer->render_pdf_page((int)$page->id, $pdf, $user, $preview);
        }

        $this->restore_runtime_language($originallang);

        if ($return) {
            return $pdf->Output('', 'S');
        }

        $pdf->Output($filename, $deliveryoption);
    }

    /**
     * Create a configured PDF instance for preview.
     *
     * Note: This keeps the runtime language switched for the preview lifecycle (restored on shutdown)
     * so callers can render pages/elements immediately after receiving the PDF instance.
     *
     * @param template $template
     * @param stdClass $user
     * @return pdf
     * @throws dml_exception
     */
    public function create_preview_pdf(template $template, stdClass $user): pdf {
        global $CFG, $DB;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $pdf = new pdf();
        $customcert = $DB->get_record('customcert', ['templateid' => $template->get_id()]) ?: null;

        $this->apply_runtime_language_for_user($customcert, $user);
        $this->configure_pdf_for_customcert($pdf, $customcert);

        return $pdf;
    }

    /**
     * Compute the PDF filename for a user/customcert.
     *
     * @param template $template
     * @param stdClass $user
     * @param stdClass|null $customcert
     * @return string
     */
    public function compute_filename_for_user(template $template, stdClass $user, ?stdClass $customcert): string {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/customcert/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        $haspattern = $customcert && !empty($customcert->usecustomfilename) && !empty($customcert->customfilenamepattern);

        if (!$haspattern) {
            $basename = rtrim(format_string($template->get_name(), true, ['context' => $template->get_context()]), '.');
        } else {
            $issue = $DB->get_record('customcert_issues', [
                'userid' => (int)$user->id,
                'customcertid' => (int)$customcert->id,
            ]);

            $issuedate = ($issue && !empty($issue->timecreated))
                ? date('Y-m-d', (int)$issue->timecreated)
                : date('Y-m-d');

            $course = !empty($customcert->course)
                ? $DB->get_record('course', ['id' => (int)$customcert->course])
                : null;

            $values = [
                '{FIRST_NAME}' => $user->firstname ?? '',
                '{LAST_NAME}' => $user->lastname ?? '',
                '{COURSE_SHORT_NAME}' => $course ? $course->shortname : '',
                '{COURSE_FULL_NAME}' => $course ? $course->fullname : '',
                '{ISSUE_DATE}' => $issuedate,
                '{GROUP_NAME}' => '',
            ];

            $needsgroup = $course && str_contains((string)$customcert->customfilenamepattern, '{GROUP_NAME}');

            if ($needsgroup) {
                require_once($CFG->dirroot . '/group/lib.php');
                $groups = groups_get_all_groups((int)$course->id, (int)$user->id);
                if (!empty($groups)) {
                    $groupnames = array_map(fn ($group) => $group->name, $groups);
                    $values['{GROUP_NAME}'] = implode(', ', $groupnames);
                }
            }

            $basename = rtrim(strtr($customcert->customfilenamepattern, $values), '.');
        }

        $basename = preg_replace('/\.pdf$/i', '', (string)$basename);

        if (empty($basename)) {
            $basename = get_string('certificate', 'customcert');
        }

        return clean_filename($basename . '.pdf');
    }

    /**
     * Apply runtime language for this certificate/user and register a shutdown restore.
     *
     * @param stdClass|null $customcert
     * @param stdClass $user
     * @param bool $registershutdown When true, register a shutdown handler to restore language.
     * @return string|null
     */
    private function apply_runtime_language_for_user(
        ?stdClass $customcert,
        stdClass $user,
        bool $registershutdown = true
    ): ?string {
        if (!$customcert) {
            return null;
        }
        $originallang = current_language();
        $uselang = mod_customcert_get_language_to_use($customcert, $user);
        $switched = mod_customcert_apply_runtime_language($uselang);
        if ($switched && $registershutdown) {
            \core_shutdown_manager::register_function('force_current_language', [$originallang]);
            return $originallang;
        }
        return $switched ? $originallang : null;
    }

    /**
     * Restore runtime language if previously switched.
     *
     * @param string|null $originallang
     * @return void
     */
    private function restore_runtime_language(?string $originallang): void {
        if (!empty($originallang)) {
            mod_customcert_apply_runtime_language($originallang);
        }
    }

    /**
     * Configure a PDF for this certificate: protection, header/footer and page break.
     *
     * @param pdf $pdf
     * @param object|null $customcert
     * @return void
     */
    private function configure_pdf_for_customcert(pdf $pdf, $customcert): void {
        if ($customcert && !empty($customcert->protection)) {
            $protection = explode(', ', $customcert->protection);
            $pdf->SetProtection($protection);
        }
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 0);
    }
}

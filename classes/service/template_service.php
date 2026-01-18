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
use invalid_parameter_exception;
use mod_customcert\certificate;
use mod_customcert\element\element_bootstrap;
use mod_customcert\element\element_interface;
use mod_customcert\element\legacy_element_adapter;
use mod_customcert\event\element_created;
use mod_customcert\event\page_created;
use mod_customcert\event\page_deleted;
use mod_customcert\event\page_updated;
use mod_customcert\event\template_deleted;
use mod_customcert\event\template_updated;
use mod_customcert\local\preview_renderer;
use mod_customcert\template;
use pdf;
use stdClass;

/**
 * Service for template-level operations (pages/elements) with transactional boundaries.
 *
 * Event timing notes:
 * - Page/element events may be emitted inside delegated transactions (delete, copy); Moodle allows this, but
 *   consumers should not assume committed state until after the transaction completes/commits.
 * - Template-level events (e.g. template_deleted) are emitted after the transaction commit in delete().
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_service {
    /** @var string Page move target. */
    public const string ITEM_PAGE = 'page';

    /** @var string Element move target. */
    public const string ITEM_ELEMENT = 'element';

    /** @var string Move direction upwards. */
    public const string DIRECTION_UP = 'up';

    /** @var string Move direction downwards. */
    public const string DIRECTION_DOWN = 'down';
    /**
     * template_service constructor.
     *
     * @param template_repository|null $templates
     * @param page_repository|null $pages
     * @param element_repository|null $elements
     * @param element_factory|null $factory
     */
    public function __construct(
        /** @var template_repository|null $templates Repository for template records. */
        private ?template_repository $templates = null,
        /** @var page_repository|null $pages Repository for page records. */
        private ?page_repository $pages = null,
        /** @var element_repository|null $elements Repository for element records. */
        private ?element_repository $elements = null,
        /** @var element_factory|null $factory Element factory shared across operations. */
        private ?element_factory $factory = null,
    ) {
        $this->templates ??= new template_repository();
        $this->pages ??= new page_repository();
        if ($this->elements !== null) {
            $this->factory ??= $this->elements->get_factory();
        }
        $this->factory ??= $this->build_element_factory();
        $this->elements ??= $this->build_element_repository();
    }

    /**
     * Build the element repository with default registry/factory wiring.
     *
     * @return element_repository
     */
    private function build_element_repository(): element_repository {
        $this->factory ??= $this->build_element_factory();
        return new element_repository($this->factory);
    }

    /**
     * Build the element factory with default wiring.
     *
     * @return element_factory
     */
    private function build_element_factory(): element_factory {
        return element_factory::build_with_defaults();
    }

    /**
     * Create an element instance from a database record using the shared factory.
     *
     * @param stdClass $record
     * @return element_interface|null
     */
    private function create_element_from_record(stdClass $record): ?element_interface {
        $this->factory ??= $this->build_element_factory();
        return $this->factory->create_from_legacy_record($record);
    }

    /**
     * Unwrap legacy adapters to their inner instance when required.
     *
     * @param element_interface $element
     * @return object
     */
    private function unwrap_element(element_interface $element): object {
        if ($element instanceof legacy_element_adapter) {
            return $element->get_inner();
        }

        return $element;
    }

    /**
     * Update template metadata (name) and fire template_updated if changed.
     *
     * @param template $template
     * @param stdClass $data
     * @return void
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public function update(template $template, stdClass $data): void {
        if (!property_exists($data, 'name')) {
            throw new invalid_parameter_exception('Template name is required');
        }

        $newname = (string)$data->name;
        $changed = $template->get_name() !== $newname;

        if (!$changed) {
            return;
        }

        $this->templates->update($template->get_id(), (object) ['name' => $newname]);

        // Keep the in-memory template in sync so subsequent calls see the new name.
        $template->set_name($newname);
        // System-context renames are persisted but do not emit template_updated to match other service flows.
        if ($template->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            template_updated::create_from_template($template)->trigger();
        }
    }

    /**
     * Add a page to a template with default dimensions and fire events.
     *
     * @param template $template
     * @param bool $triggertemplateupdatedevent
     * @return int
     * @throws dml_exception
     */
    public function add_page(template $template, bool $triggertemplateupdatedevent = true): int {
        $now = time();
        // Calculate next sequence to preserve ordering when multiple pages are added.
        // page_repository guarantees sequence ASC, id ASC ordering; end($pages) reflects the highest sequence.
        $pages = $this->pages->list_by_template($template->get_id());
        $nextsequence = 1;
        if (!empty($pages)) {
            $last = end($pages); // The method list_by_template returns sequence ASC, id ASC.
            $nextsequence = ((int)$last->sequence) + 1;
        }
        $pageid = $this->pages->create((object) [
            'templateid' => $template->get_id(),
            'width' => 210,
            'height' => 297,
            'leftmargin' => 0,
            'rightmargin' => 0,
            'sequence' => $nextsequence,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $page = $this->pages->get_by_id_or_fail($pageid);
        page_created::create_from_page($page, $template)->trigger();

        if ($triggertemplateupdatedevent) {
            template_updated::create_from_template($template)->trigger();
        }

        return $pageid;
    }

    /**
     * Save page size/margins for all pages on a template.
     *
     * Note: by default this method only emits page_updated. Set $triggertemplateupdatedevent to true to emit
     * template_updated when any page changes are persisted.
     *
     * @param template $template
     * @param stdClass $data
     * @param bool $triggertemplateupdatedevent
     * @return void
     * @throws invalid_parameter_exception When required page fields are missing.
     * @throws dml_exception
     */
    public function save_pages(template $template, stdClass $data, bool $triggertemplateupdatedevent = false): void {
        $pages = $this->pages->list_by_template($template->get_id());
        if (empty($pages)) {
            return;
        }

        $time = time();
        $updated = false;
        foreach ($pages as $page) {
            if ($this->has_page_been_updated($page, $data)) {
                $width = 'pagewidth_' . $page->id;
                $height = 'pageheight_' . $page->id;
                $leftmargin = 'pageleftmargin_' . $page->id;
                $rightmargin = 'pagerightmargin_' . $page->id;

                $this->pages->update(
                    (int)$page->id,
                    new page_update(
                        (int)$data->$width,
                        (int)$data->$height,
                        (int)$data->$leftmargin,
                        (int)$data->$rightmargin,
                        $time
                    )
                );
                $eventpage = $this->pages->get_by_id_or_fail((int)$page->id);
                page_updated::create_from_page($eventpage, $template)->trigger();
                $updated = true;
            }
        }

        if ($updated && $triggertemplateupdatedevent) {
            template_updated::create_from_template($template)->trigger();
        }
    }

    /**
     * Delete a template and all its pages/elements.
     *
     * @param template $template
     * @return bool
     * @throws dml_exception
     */
    public function delete(template $template): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Page-level events fire inside this transaction; Moodle permits in-transaction events, but consumers
        // should not assume committed state until after allow_commit(). template_deleted is emitted after commit below.
        $pages = $this->pages->list_by_template($template->get_id());
        foreach ($pages as $page) {
            $this->delete_page($template, (int)$page->id, false);
        }

        $this->templates->delete($template->get_id());

        $transaction->allow_commit();

        template_deleted::create_from_template($template)->trigger();
        return true;
    }

    /**
     * Delete a page and its elements, resequencing remaining pages.
     *
     * @param template $template
     * @param int $pageid
     * @param bool $triggertemplateupdatedevent
     * @return void
     * @throws dml_exception
     */
    public function delete_page(template $template, int $pageid, bool $triggertemplateupdatedevent = true): void {
        global $DB;

        $page = $this->pages->get_by_id_or_fail($pageid);

        // Defensive: ensure the page belongs to this template.
        if ((int)$page->templateid !== $template->get_id()) {
            throw new invalid_parameter_exception('Page does not belong to template');
        }

        if ($elements = $DB->get_records('customcert_elements', ['pageid' => $page->id])) {
            foreach ($elements as $element) {
                // Use the element instance so plugin-specific delete hooks execute; repository deletion would skip them.
                $instance = $this->create_element_from_record($element);
                if ($instance) {
                    $instance->delete();
                    continue;
                }

                $DB->delete_records('customcert_elements', ['id' => $element->id]);
            }
        }

        $DB->delete_records('customcert_pages', ['id' => $page->id]);

        page_deleted::create_from_page($page, $template)->trigger();

        // Resequence remaining pages.
        $this->pages->resequence($template->get_id());

        if ($triggertemplateupdatedevent) {
            template_updated::create_from_template($template)->trigger();
        }
    }

    /**
     * Delete an element and resequence remaining elements on the page.
     *
     * @param template $template
     * @param int $elementid
     * @return void
     * @throws dml_exception
     */
    public function delete_element(template $template, int $elementid): void {
        global $DB;

        $element = $this->elements->get_by_id_or_fail($elementid);

        $page = $this->pages->get_by_id_or_fail((int)$element->pageid);
        if ((int)$page->templateid !== $template->get_id()) {
            throw new invalid_parameter_exception('Element does not belong to template');
        }

        $instance = $this->create_element_from_record($element);
        if ($instance) {
            $instance->delete();
        } else {
            $DB->delete_records('customcert_elements', ['id' => $elementid]);
        }

        // Resequence remaining elements.
        $sql = "UPDATE {customcert_elements}
                   SET sequence = sequence - 1
                 WHERE pageid = :pageid
                   AND sequence > :sequence";
        $DB->execute($sql, ['pageid' => $element->pageid, 'sequence' => $element->sequence]);

        template_updated::create_from_template($template)->trigger();
    }

    /**
     * Copy all pages/elements from this template into another template.
     *
     * @param template $source
     * @param template $target
     * @return void
     * @throws dml_exception
     */
    public function copy_to_template(template $source, template $target): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Events are emitted inside this transaction; Moodle allows in-transaction events and
        // they rely only on the data manipulated within this transaction.
        $sourcepages = $this->pages->list_by_template($source->get_id());
        foreach ($sourcepages as $sourcepage) {
            $newpageid = $this->pages->create((object) [
                'templateid' => $target->get_id(),
                'width' => $sourcepage->width,
                'height' => $sourcepage->height,
                'leftmargin' => $sourcepage->leftmargin,
                'rightmargin' => $sourcepage->rightmargin,
                'sequence' => $sourcepage->sequence,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);

            $newpage = $this->pages->get_by_id_or_fail($newpageid);
            page_created::create_from_page($newpage, $target)->trigger();

            if ($templateelements = $DB->get_records('customcert_elements', ['pageid' => $sourcepage->id])) {
                foreach ($templateelements as $templateelement) {
                    $element = clone($templateelement);
                    $element->pageid = $newpage->id;
                    $element->timecreated = time();
                    $element->timemodified = $element->timecreated;
                    $element->id = $DB->insert_record('customcert_elements', $element);

                    if ($instance = $this->create_element_from_record($element)) {
                        $inner = $this->unwrap_element($instance);
                        if (method_exists($inner, 'copy_element') && !$inner->copy_element($templateelement)) {
                            $instance->delete();
                        } else {
                            element_created::create_from_element($instance)->trigger();
                        }
                    }
                }
            }
        }

        $transaction->allow_commit();

        // Trigger event if copying into a course module template; system-level copy is handled elsewhere.
        if ($target->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            template_updated::create_from_template($target)->trigger();
        }
    }

    /**
     * Move a page or element up/down by swapping sequences and fire template_updated.
     *
     * @param template $template
     * @param string $itemname 'page' or 'element'
     * @param int $itemid
     * @param string $direction 'up' or 'down'
     * @return void
     * @throws dml_exception
     */
    public function move_item(template $template, string $itemname, int $itemid, string $direction): void {
        global $DB;

        if (!in_array($itemname, [self::ITEM_PAGE, self::ITEM_ELEMENT], true)) {
            throw new invalid_parameter_exception('Invalid item to move');
        }
        if (!in_array($direction, [self::DIRECTION_UP, self::DIRECTION_DOWN], true)) {
            throw new invalid_parameter_exception('Invalid move direction');
        }

        $table = $itemname === self::ITEM_PAGE ? 'customcert_pages' : 'customcert_elements';

        $moveitem = $DB->get_record($table, ['id' => $itemid]);
        if (!$moveitem) {
            return;
        }

        if ($itemname === self::ITEM_PAGE) {
            if ((int)$moveitem->templateid !== $template->get_id()) {
                throw new invalid_parameter_exception('Page does not belong to template');
            }
        } else {
            $page = $this->pages->get_by_id_or_fail((int)$moveitem->pageid);
            if ((int)$page->templateid !== $template->get_id()) {
                throw new invalid_parameter_exception('Element does not belong to template');
            }
        }

        $sequence = $direction === self::DIRECTION_UP ? $moveitem->sequence - 1 : $moveitem->sequence + 1;

        $params = $itemname === self::ITEM_PAGE
            ? ['templateid' => $moveitem->templateid]
            : ['pageid' => $moveitem->pageid];
        $swapitem = $DB->get_record($table, $params + ['sequence' => $sequence]);

        if ($swapitem) {
            $DB->set_field($table, 'sequence', $swapitem->sequence, ['id' => $moveitem->id]);
            $DB->set_field($table, 'sequence', $moveitem->sequence, ['id' => $swapitem->id]);
            template_updated::create_from_template($template)->trigger();
        }
    }

    /**
     * Generate the PDF for a template.
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

        // Keep the language switch below this early return to avoid leaving the runtime language modified
        // when no pages are present.
        $pages = $this->pages->list_by_template($template->get_id());
        if (empty($pages)) {
            return $return ? '' : null;
        }

        $pdf = new pdf();

        $customcert = $DB->get_record('customcert', ['templateid' => $template->get_id()]) ?: null;

        $originallang = $this->apply_runtime_language_for_user($customcert, $user);

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

        // Leave the runtime language switched for the preview lifecycle; shutdown restore registered by
        // apply_runtime_language_for_user() provides a safety net after rendering.
        $this->apply_runtime_language_for_user($customcert, $user);
        $this->configure_pdf_for_customcert($pdf, $customcert);

        return $pdf;
    }

    /**
     * Compute the PDF filename for a user/customcert.
     *
     * @param template $template
     * @param stdClass $user
     * @param object|null $customcert
     * @return string
     */
    public function compute_filename_for_user(template $template, stdClass $user, $customcert): string {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/customcert/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        $customcert = $customcert ?: null;

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
     * @param object|null $customcert
     * @param stdClass $user
     * @return string|null
     */
    private function apply_runtime_language_for_user($customcert, stdClass $user): ?string {
        if (!$customcert) {
            return null;
        }
        $originallang = current_language();
        $uselang = mod_customcert_get_language_to_use($customcert, $user);
        $switched = mod_customcert_apply_runtime_language($uselang);
        if ($switched) {
            \core_shutdown_manager::register_function('force_current_language', [$originallang]);
            return $originallang;
        }
        return null;
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

    /**
     * Determine if a page has been updated based on form data.
     *
     * @param stdClass $page
     * @param stdClass $formdata
     * @return bool
     * @throws invalid_parameter_exception When required page fields are missing.
     */
    private function has_page_been_updated($page, $formdata): bool {
        $width = 'pagewidth_' . $page->id;
        $height = 'pageheight_' . $page->id;
        $leftmargin = 'pageleftmargin_' . $page->id;
        $rightmargin = 'pagerightmargin_' . $page->id;

        foreach ([$width, $height, $leftmargin, $rightmargin] as $field) {
            if (!property_exists($formdata, $field)) {
                throw new invalid_parameter_exception('Missing page field: ' . $field);
            }
        }

        if ($page->width != $formdata->$width) {
            return true;
        }

        if ($page->height != $formdata->$height) {
            return true;
        }

        if ($page->leftmargin != $formdata->$leftmargin) {
            return true;
        }

        if ($page->rightmargin != $formdata->$rightmargin) {
            return true;
        }

        return false;
    }
}

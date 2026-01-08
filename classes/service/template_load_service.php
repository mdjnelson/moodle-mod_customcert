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

use context;
use dml_exception;
use mod_customcert\element\element_bootstrap;
use mod_customcert\event\template_updated;
use mod_customcert\template;

/**
 * Service to replace an existing template's pages/elements with another template's content.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_load_service {
    /** @var template_repository */
    private template_repository $templates;

    /** @var page_repository */
    private page_repository $pages;

    /** @var element_repository */
    private element_repository $elements;

    /**
     * Constructor.
     *
     * @param template_repository|null $templates
     * @param page_repository|null $pages
     * @param element_repository|null $elements
     */
    public function __construct(
        ?template_repository $templates = null,
        ?page_repository $pages = null,
        ?element_repository $elements = null,
    ) {
        $this->templates = $templates ?? new template_repository();
        $this->pages = $pages ?? new page_repository();
        $this->elements = $elements ?? $this->build_element_repository();
    }

    /**
     * Build a default element repository with registered element types.
     *
     * @return element_repository
     */
    private function build_element_repository(): element_repository {
        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);
        $factory = new element_factory($registry);
        return new element_repository($factory);
    }

    /**
     * Replace the contents of a target template with a source template's pages and elements.
     *
     * All existing pages/elements on the target are removed, then source pages/elements are
     * copied preserving sequence. A template_updated event is fired for non-system contexts.
     *
     * @param int $targetid Existing template to overwrite
     * @param int $sourceid Template to copy from
     * @return void
     * @throws dml_exception For database errors.
     */
    public function replace(int $targetid, int $sourceid): void {
        global $DB;

        $target = $this->templates->get_by_id_or_fail($targetid);
        $source = $this->templates->get_by_id_or_fail($sourceid);

        $context = context::instance_by_id($target->contextid);
        require_capability('mod/customcert:manage', $context);

        $transaction = $DB->start_delegated_transaction();

        // Remove existing pages/elements on target.
        $existingpages = $this->pages->list_by_template($targetid);
        foreach ($existingpages as $page) {
            $DB->delete_records('customcert_elements', ['pageid' => $page->id]);
            $DB->delete_records('customcert_pages', ['id' => $page->id]);
        }

        // Copy pages and elements from source to target.
        $now = time();
        $sourcepages = $this->pages->list_by_template($sourceid);
        foreach ($sourcepages as $page) {
            $newpageid = $this->pages->create((object) [
                'templateid' => $targetid,
                'width' => $page->width,
                'height' => $page->height,
                'leftmargin' => $page->leftmargin,
                'rightmargin' => $page->rightmargin,
                'sequence' => $page->sequence,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);

            $this->elements->copy_page((int) $page->id, $newpageid, false);
        }

        $transaction->allow_commit();

        $targettemplate = new template($target);
        if ($targettemplate->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            template_updated::create_from_template($targettemplate)->trigger();
        }
    }
}

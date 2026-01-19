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
use mod_customcert\event\page_created;
use mod_customcert\event\template_duplicated;
use mod_customcert\template;

/**
 * Transactional template duplication service.
 *
 * Copies a template row plus all pages and elements, preserving ordering and
 * firing relevant events. Naming is delegated to {@see template_repository::duplicate()}.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_duplication_service {
    /**
     * Template repository.
     *
     * @var template_repository
     */
    private template_repository $templates;

    /**
     * Page repository.
     *
     * @var page_repository
     */
    private page_repository $pages;

    /**
     * Element repository.
     *
     * @var element_repository
     */
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
     * Duplicate a template, its pages, and elements in a single transaction.
     *
     * @param int $sourceid
     * @param string|null $newname Optional explicit name; otherwise repository default naming is used.
     * @return int New template id
     * @throws dml_exception For database errors.
     */
    public function duplicate(int $sourceid, ?string $newname = null): int {
        global $DB;

        $source = $this->templates->get_by_id_or_fail($sourceid);
        $context = context::instance_by_id($source->contextid);
        require_capability('mod/customcert:manage', $context);

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $targetid = $this->templates->duplicate($sourceid, $newname);
        $targettemplate = template::load($targetid);

        $pages = $this->pages->list_by_template($sourceid);
        foreach ($pages as $page) {
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

            $newpage = (object) [
                'id' => $newpageid,
                'templateid' => $targetid,
                'width' => $page->width,
                'height' => $page->height,
                'leftmargin' => $page->leftmargin,
                'rightmargin' => $page->rightmargin,
                'sequence' => $page->sequence,
            ];
            page_created::create_from_page($newpage, $targettemplate)->trigger();

            $this->elements->copy_page((int) $page->id, $newpageid, false);
        }

        $transaction->allow_commit();

        template_duplicated::create([
            'contextid' => $targettemplate->get_contextid(),
            'objectid' => $targetid,
            'other' => ['sourceid' => $sourceid],
        ])->trigger();

        return $targetid;
    }
}

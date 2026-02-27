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
 * Class represents a customcert template.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert;

use mod_customcert\event\template_created;
use mod_customcert\service\template_repository;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\service\template_service;
use pdf;
use stdClass;

/**
 * Class represents a customcert template.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template {
    /**
     * @var int $id The id of the template.
     */
    protected int $id;

    /**
     * @var string $name The name of this template
     */
    protected string $name;

    /**
     * @var int $contextid The context id of this template
     */
    protected int $contextid;

    /**
     * Cached template service instance for deprecated shims.
     *
     * @var template_service|null
     */
    private ?template_service $service = null;

    /**
     * Cached PDF generation service for deprecated shims.
     *
     * @var pdf_generation_service|null
     */
    private ?pdf_generation_service $pdfs = null;

    /**
     * The constructor.
     *
     * @param stdClass $template
     */
    public function __construct(stdClass $template) {
        $this->id = (int)$template->id;
        $this->name = $template->name;
        $this->contextid = (int)$template->contextid;
    }

    /**
     * Handles saving data.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::update instead
     * @param stdClass $data the template data
     */
    public function save(stdClass $data): void {
        debugging('template::save() is deprecated; use template_service::update() instead.', DEBUG_DEVELOPER);
        $this->get_service()->update($this, $data);
    }

    /**
     * Handles adding another page to the template.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::add_page instead
     * @param bool $triggertemplateupdatedevent
     * @return int the id of the page
     */
    public function add_page(bool $triggertemplateupdatedevent = true): int {
        debugging('template::add_page() is deprecated; use template_service::add_page() instead.', DEBUG_DEVELOPER);
        return $this->get_service()->add_page($this, $triggertemplateupdatedevent);
    }

    /**
     * Handles saving page data.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::save_pages instead
     * @param stdClass $data the template data
     */
    public function save_page(stdClass $data): void {
        debugging('template::save_page() is deprecated; use template_service::save_pages() instead.', DEBUG_DEVELOPER);
        $this->get_service()->save_pages($this, $data);
    }

    /**
     * Handles deleting the template.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::delete instead
     * @return bool return true if the deletion was successful, false otherwise
     */
    public function delete(): bool {
        debugging('template::delete() is deprecated; use template_service::delete() instead.', DEBUG_DEVELOPER);
        return $this->get_service()->delete($this);
    }

    /**
     * Handles deleting a page from the template.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::delete_page instead
     * @param int $pageid the template page
     * @param bool $triggertemplateupdatedevent False if page is being deleted
     * during deletion of template.
     */
    public function delete_page(int $pageid, bool $triggertemplateupdatedevent = true): void {
        debugging('template::delete_page() is deprecated; use template_service::delete_page() instead.', DEBUG_DEVELOPER);
        $this->get_service()->delete_page($this, $pageid, $triggertemplateupdatedevent);
    }

    /**
     * Handles deleting an element from the template.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::delete_element instead
     * @param int $elementid the template page
     */
    public function delete_element(int $elementid): void {
        debugging('template::delete_element() is deprecated; use template_service::delete_element() instead.', DEBUG_DEVELOPER);
        $this->get_service()->delete_element($this, $elementid);
    }

    /**
     * Generate the PDF for the template.
     *
     * @param bool $preview true if it is a preview, false otherwise
     * @param int|null $userid the id of the user whose certificate we want to view
     * @param bool $return Do we want to return the contents of the PDF?
     * @return string|void Can return the PDF in string format if specified.
     */
    public function generate_pdf(bool $preview = false, ?int $userid = null, bool $return = false) {
        debugging('template::generate_pdf() is deprecated; use pdf_generation_service::generate_pdf() instead.', DEBUG_DEVELOPER);
        return $this->get_pdf_service()->generate_pdf($this, $preview, $userid, $return);
    }

    /**
     * Create and configure a PDF instance suitable for preview rendering.
     *
     * This helper mirrors the setup used in {@see template::generate_pdf} for preview
     * and can be used by alternate preview flows (e.g., the V2 orchestrator).
     *
     * @param stdClass $user The user that the preview is for.
     * @return pdf A configured PDF instance ready for element rendering.
     */
    public function create_preview_pdf(stdClass $user): pdf {
        debugging(
            'template::create_preview_pdf() is deprecated; use pdf_generation_service::create_preview_pdf() instead.',
            DEBUG_DEVELOPER
        );
        return $this->get_pdf_service()->create_preview_pdf($this, $user);
    }


    /**
     * Compute filename for the current user/certificate using template and pattern settings.
     * Mirrors the logic in generate_pdf(). Returns a clean filename with .pdf suffix.
     *
     * @param stdClass $user
     * @param stdClass|null $customcert
     * @return string
     */
    public function compute_filename_for_user(stdClass $user, ?stdClass $customcert): string {
        debugging(
            'template::compute_filename_for_user() is deprecated; use pdf_generation_service::compute_filename_for_user() instead.',
            DEBUG_DEVELOPER
        );
        return $this->get_pdf_service()->compute_filename_for_user($this, $user, $customcert);
    }

    /**
     * Handles copying this template into another.
     *
     * @param template $copytotemplate The template instance to copy to
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::copy_to_template instead
     */
    public function copy_to_template(template $copytotemplate): void {
        debugging('template::copy_to_template() is deprecated; use template_service::copy_to_template() instead.', DEBUG_DEVELOPER);
        $this->get_service()->copy_to_template($this, $copytotemplate);
    }

    /**
     * Handles moving an item on a template.
     *
     * @deprecated since 5.2.0 Use \mod_customcert\service\template_service::move_item instead
     * @param string $itemname the item we are moving
     * @param int $itemid the id of the item
     * @param string $direction the direction
     */
    public function move_item(string $itemname, int $itemid, string $direction): void {
        debugging('template::move_item() is deprecated; use template_service::move_item() instead.', DEBUG_DEVELOPER);
        $this->get_service()->move_item($this, $itemname, $itemid, $direction);
    }

    /**
     * Returns the id of the template.
     *
     * @return int the id of the template
     */
    public function get_id(): int {
        return (int)$this->id;
    }

    /**
     * Returns the name of the template.
     *
     * @return string the name of the template
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Update the in-memory template name.
     *
     * @param string $name
     * @return void
     */
    public function set_name(string $name): void {
        $this->name = $name;
    }

    /**
     * Returns the context id.
     *
     * @return int the context id
     */
    public function get_contextid(): int {
        return $this->contextid;
    }

    /**
     * Returns the context id.
     *
     * @return \context the context
     */
    public function get_context(): \context {
        return \context::instance_by_id($this->contextid);
    }

    /**
     * Returns the context id.
     *
     * @return \context_module|null the context module, null if there is none
     */
    public function get_cm(): ?stdClass {
        $context = $this->get_context();
        if ($context->contextlevel === CONTEXT_MODULE) {
            return get_coursemodule_from_id('customcert', $context->instanceid, 0, false, MUST_EXIST);
        }

        return null;
    }

    /**
     * Ensures the user has the proper capabilities to manage this template.
     *
     * @throws \required_capability_exception if the user does not have the necessary capabilities (ie. Fred)
     */
    public function require_manage(): void {
        require_capability('mod/customcert:manage', $this->get_context());
    }

    /**
     * Creates a template.
     *
     * @param string $templatename the name of the template
     * @param int $contextid the context id
     * @return template the template object
     */
    public static function create(string $templatename, int $contextid): template {
        $repository = new template_repository();
        $id = $repository->create((object) ['name' => $templatename, 'contextid' => $contextid]);
        $record = $repository->get_by_id_or_fail($id);

        $template = new template($record);

        template_created::create_from_template($template)->trigger();

        return $template;
    }

    /**
     * Load an existing template by id.
     *
     * @param int $templateid
     * @return template
     */
    public static function load(int $templateid): template {
        $repository = new template_repository();
        $record = $repository->get_by_id_or_fail($templateid);
        return new template($record);
    }

    /**
     * Lazily build a template_service instance.
     *
     * @return template_service
     */
    private function get_service(): template_service {
        return $this->service ??= new template_service();
    }

    /**
     * Lazily build a pdf_generation_service instance.
     *
     * @return pdf_generation_service
     */
    private function get_pdf_service(): pdf_generation_service {
        return $this->pdfs ??= new pdf_generation_service();
    }
}

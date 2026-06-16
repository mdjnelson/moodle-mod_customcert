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

use context;
use mod_customcert\event\template_created;
use mod_customcert\service\template_repository;
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
     * The constructor.
     *
     * @param int $id The id of the template.
     * @param string $name The name of the template.
     * @param int $contextid The context id of the template.
     */
    public function __construct(int $id, string $name, int $contextid) {
        $this->id = $id;
        $this->name = $name;
        $this->contextid = $contextid;
    }

    /**
     * Creates a template instance from a database record.
     *
     * @param stdClass $record A record from the customcert_templates table.
     * @return template
     */
    public static function from_record(stdClass $record): template {
        return new template((int)$record->id, $record->name, (int)$record->contextid);
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
        return context::instance_by_id($this->contextid);
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

        $template = self::from_record($record);

        template_created::create_from_template($template)->trigger();

        return $template;
    }
}

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
use mod_customcert\event\template_updated;
use mod_customcert\template;
use moodle_database;

/**
 * Handles moving pages/elements on a template by swapping sequences.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_move_service {
    /** @var string Page move target. */
    public const string ITEM_PAGE = 'page';

    /** @var string Element move target. */
    public const string ITEM_ELEMENT = 'element';

    /** @var string Move direction upwards. */
    public const string DIRECTION_UP = 'up';

    /** @var string Move direction downwards. */
    public const string DIRECTION_DOWN = 'down';

    /** @var moodle_database Database handle. */
    private moodle_database $db;

    /** @var page_repository Repository for page lookups. */
    private page_repository $pages;

    /**
     * Create an item_move_service with default dependencies.
     *
     * @return self
     */
    public static function create(): self {
        global $DB;
        return new self($DB, new page_repository());
    }

    /**
     * item_move_service constructor.
     *
     * @param moodle_database $db
     * @param page_repository $pages
     */
    public function __construct(moodle_database $db, page_repository $pages) {
        $this->db = $db;
        $this->pages = $pages;
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
        if (!in_array($itemname, [self::ITEM_PAGE, self::ITEM_ELEMENT], true)) {
            throw new invalid_parameter_exception('Invalid item to move');
        }
        if (!in_array($direction, [self::DIRECTION_UP, self::DIRECTION_DOWN], true)) {
            throw new invalid_parameter_exception('Invalid move direction');
        }

        $table = $itemname === self::ITEM_PAGE ? 'customcert_pages' : 'customcert_elements';

        $moveitem = $this->db->get_record($table, ['id' => $itemid]);
        if (!$moveitem) {
            debugging(
                "item_move_service: could not find {$itemname} with id={$itemid} to move {$direction}.",
                DEBUG_DEVELOPER
            );
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
        $swapitem = $this->db->get_record($table, $params + ['sequence' => $sequence]);

        if ($swapitem) {
            $this->db->set_field($table, 'sequence', $swapitem->sequence, ['id' => $moveitem->id]);
            $this->db->set_field($table, 'sequence', $moveitem->sequence, ['id' => $swapitem->id]);
            template_updated::create_from_template($template)->trigger();
        }
    }
}

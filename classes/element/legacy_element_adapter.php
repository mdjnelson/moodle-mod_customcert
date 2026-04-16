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

/**
 * Legacy element adapter implementing the v2 element interface.
 *
 * @package    mod_customcert
 *
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\edit_element_form;
use mod_customcert\element as legacy_base;
use MoodleQuickForm;
use stdClass;
use mod_customcert\service\element_renderer;
use mod_customcert\service\element_repository;
use mod_customcert\service\element_factory;
use restore_customcert_activity_task;

/**
 * Adapts a legacy element (extending mod_customcert\element) to element_interface.
 *
 * @deprecated since Moodle 5.2 — one-release compatibility bridge only.
 *   This class exists to allow third-party element plugins that extend the legacy
 *   mod_customcert\element base class to continue working after the v2 refactor without
 *   modification. It is NOT part of the long-term architecture.
 *
 *   Plugin authors should migrate their elements to implement element_interface directly.
 *   This adapter and the legacy mod_customcert\element base class are candidates for
 *   removal in a future major release once the transition period has ended.
 */
final class legacy_element_adapter implements element_interface, restorable_element_interface {
    /** @var legacy_base The wrapped legacy element instance. */
    private legacy_base $inner;

    /**
     * Constructor.
     *
     * @param legacy_base $legacy Legacy element instance to wrap.
     */
    public function __construct(legacy_base $legacy) {
        $this->inner = $legacy;
    }

    /**
     * Access the wrapped legacy element.
     *
     * @return legacy_base
     */
    public function get_inner(): legacy_base {
        return $this->inner;
    }

    /**
     * Get the internal element ID.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->inner->get_id();
    }

    /**
     * Get the page ID this element belongs to.
     *
     * @return int
     */
    public function get_pageid(): int {
        return $this->inner->get_pageid();
    }

    /**
     * Get the display/name label of the element.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->inner->get_name();
    }

    /**
     * Get element data payload.
     *
     * @return mixed
     */
    public function get_data(): mixed {
        return $this->inner->get_data();
    }

    /**
     * Get the font name used by this element.
     *
     * @return string|null
     */
    public function get_font(): ?string {
        return $this->inner->get_font();
    }

    /**
     * Get the font size used by this element.
     *
     * @return int|null
     */
    public function get_fontsize(): ?int {
        return $this->inner->get_fontsize();
    }

    /**
     * Get the colour value used by this element.
     *
     * @return string|null
     */
    public function get_colour(): ?string {
        return $this->inner->get_colour();
    }

    /**
     * Get the width allocated to the element.
     *
     * @return int|null
     */
    public function get_width(): ?int {
        return $this->inner->get_width();
    }


    /**
     * Returns the type of the element.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->inner->get_type();
    }

    /**
     * Set the edit element form instance.
     *
     * @param edit_element_form $editelementform
     * @return void
     */
    public function set_edit_element_form(edit_element_form $editelementform): void {
        $this->inner->set_edit_element_form($editelementform);
    }

    /**
     * Render form elements (legacy fallback).
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function render_form_elements(MoodleQuickForm $mform): void {
        $this->inner->render_form_elements($mform);
    }

    /**
     * Check if this element requires the 'Save and continue' button.
     *
     * @return bool
     */
    public function has_save_and_continue(): bool {
        if (method_exists($this->inner, 'has_save_and_continue')) {
            return $this->inner->has_save_and_continue();
        }
        return false;
    }

    /**
     * Validate form elements (legacy fallback).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_form_elements(array $data, array $files): array {
        if (method_exists($this->inner, 'validate_form_elements')) {
            return $this->inner->validate_form_elements($data, $files);
        }
        return [];
    }

    /**
     * Save unique data for legacy elements (legacy fallback).
     *
     * @param \stdClass $data
     * @return mixed
     */
    public function save_unique_data(stdClass $data): mixed {
        if (method_exists($this->inner, 'save_unique_data')) {
            return $this->inner->save_unique_data($data);
        }
        return null;
    }

    /**
     * Render the element in HTML for the drag and drop interface.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        return $this->inner->render_html($renderer);
    }

    /**
     * Sets the data on the form when editing an element (legacy fallback).
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function definition_after_data(MoodleQuickForm $mform): void {
        if (method_exists($this->inner, 'definition_after_data')) {
            $this->inner->definition_after_data($mform);
        }
    }

    /**
     * Handle restoration process for legacy elements.
     *
     * Implements restorable_element_interface so the restore task hits the instanceof branch
     * and never emits a deprecation warning for adapted elements. If the wrapped legacy element
     * has its own after_restore() method, it is called silently here as the adapter is the
     * designated compatibility bridge.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        if (method_exists($this->inner, 'after_restore')) {
            $this->inner->after_restore($restore);
        }
    }

    /**
     * Handle element deletion.
     *
     * @deprecated since Moodle 5.2 — compatibility bridge only.
     *   This method exists so that legacy code calling $adapter->delete() continues to work
     *   during the transition period. New code should use element_repository::delete() directly.
     *   This method and the element_repository/element_factory imports in this class are
     *   candidates for removal once all callers have been migrated.
     *
     * @return bool success return true if deletion success, false otherwise
     */
    public function delete(): bool {
        $repository = new element_repository(element_factory::build_with_defaults());
        return $repository->delete($this);
    }
}

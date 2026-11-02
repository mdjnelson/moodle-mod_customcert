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
 * Fixture: native v2 element explicitly implementing raw_data_element_interface, with
 * get_data() and get_raw_data() deliberately returning different representations.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\raw_data_element_interface;
use mod_customcert\element\renderable_element_interface;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Native v2 element that opts in to raw_data_element_interface with a get_data()
 * representation that deliberately differs from what must actually be persisted.
 *
 * get_data() returns a rendering-friendly uppercased view of the stored value, while
 * get_raw_data() returns the exact, untouched payload that element_repository must persist.
 */
final class raw_data_native_v2_element extends element implements
    form_element_interface,
    persistable_element_interface,
    raw_data_element_interface,
    renderable_element_interface {
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    #[\Override]
    public function build_form(MoodleQuickForm $mform): void {
        unset($mform);
    }

    /**
     * Normalise form data for persistence.
     *
     * @param stdClass $formdata
     * @return array
     */
    #[\Override]
    public function normalise_data(stdClass $formdata): array {
        return ['value' => $formdata->value ?? 'raw'];
    }

    /**
     * Render the element into a PDF context.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user
     * @return void
     */
    #[\Override]
    public function render(pdf $pdf, bool $preview, stdClass $user): void {
        unset($pdf, $preview, $user);
    }

    /**
     * Render the element in HTML for the designer.
     *
     * @return string
     */
    #[\Override]
    public function render_html(): string {
        return 'raw-data-native-v2';
    }

    /**
     * Rendering-friendly view of the data: deliberately different from the stored payload
     * returned by get_raw_data(), to prove the repository uses get_raw_data() for
     * persistence rather than this method.
     *
     * @return mixed
     */
    #[\Override]
    public function get_data(): mixed {
        return strtoupper((string) $this->data);
    }

    /**
     * The exact, untouched payload that must be persisted.
     *
     * @return mixed
     */
    #[\Override]
    public function get_raw_data(): mixed {
        return $this->data;
    }

    /**
     * Overridden because this fixture lives under the shared tests\fixtures namespace
     * rather than a dedicated customcertelement_* namespace, so the base class's
     * namespace-derived get_type() would not return the registered type key.
     *
     * @return string
     */
    #[\Override]
    public function get_type(): string {
        return 'rawdata968';
    }
}

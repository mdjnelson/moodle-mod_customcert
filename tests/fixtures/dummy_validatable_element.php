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
 * Dummy element implementing validatable_element_interface for tests.
 *
 * @package    mod_customcert
 * @category   test
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\tests\fixtures;

use mod_customcert\element;
use mod_customcert\element\element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\service\element_renderer;
use pdf;
use stdClass;

/**
 * Dummy validatable element used only for PHPUnit tests.
 */
final class dummy_validatable_element extends element implements element_interface, validatable_element_interface {
    /** @var array<string,string> */
    private array $customerrors = [];

    /** @var bool */
    private bool $throwonvalidate = false;

    /**
     * Configure custom validation errors to be returned.
     *
     * @param array $errors Associative array of field names to error message strings.
     * @return void
     */
    public function set_validation_result(array $errors): void {
        $this->customerrors = $errors;
    }

    /**
     * Configure whether validate() should throw an exception.
     *
     * @param bool $throw
     * @return void
     */
    public function set_throw_on_validate(bool $throw): void {
        $this->throwonvalidate = $throw;
    }

    /**
     * Validate submitted form data for this element.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        if ($this->throwonvalidate) {
            throw new \RuntimeException('Boom');
        }
        return $this->customerrors;
    }

    /**
     * Render into TCPDF (not used in these tests).
     *
     * @param pdf $pdf The TCPDF instance.
     * @param bool $preview Whether this is a preview render.
     * @param stdClass $user The user being rendered for.
     * @param element_renderer|null $renderer Optional element renderer service.
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        // No-op for tests.
    }

    /**
     * Render HTML representation (not used in these tests).
     *
     * @param element_renderer|null $renderer Optional element renderer service.
     * @return string HTML output.
     */
    public function render_html(?element_renderer $renderer = null): string {
        return '';
    }
}

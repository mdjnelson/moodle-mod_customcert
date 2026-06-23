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
 * Defines the typed payload interface for certificate element data.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);
namespace mod_customcert\element;

/**
 * Interface element_payload_interface
 *
 * Typed payload objects give element data a clear PHP-side contract: which keys
 * exist, which are required, what types they hold, and how they are validated.
 * The database continues to store JSON; this interface governs the PHP layer only.
 *
 * Implement this interface on a dedicated payload class for each element type,
 * then use it inside `normalise_data()`, `prepare_form()`, and `render()` instead
 * of working with raw arrays or JSON strings directly.
 *
 * Example skeleton:
 *
 * ```php
 * final class coursename_payload implements element_payload_interface {
 *     public function __construct(
 *         public readonly int    $coursenamedisplay,
 *         public readonly string $font,
 *         public readonly int    $fontsize,
 *         public readonly string $colour,
 *         public readonly int    $width,
 *     ) {}
 *
 *     public static function from_array(array $data): self { ... }
 *     public function to_array(): array { ... }
 *     public function validate(): void { ... }
 * }
 * ```
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface element_payload_interface {
    /**
     * Construct a payload object from a decoded data array.
     *
     * This is the primary deserialization entry point. Implementations should
     * apply safe defaults for missing keys and cast values to their canonical types.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static;

    /**
     * Serialize the payload back to an associative array suitable for json_encode().
     *
     * The returned array must round-trip cleanly through {@see from_array()}.
     *
     * @return array
     */
    public function to_array(): array;

    /**
     * Assert that the payload values are internally consistent and valid.
     *
     * Throw an appropriate exception (e.g. \coding_exception or \invalid_parameter_exception)
     * when validation fails. This method is intended for developer-facing invariant checks,
     * not for user-facing form validation (which belongs in `validatable_element_interface`).
     *
     * @return void
     * @throws \coding_exception|\invalid_parameter_exception on invalid data.
     */
    public function validate(): void;
}

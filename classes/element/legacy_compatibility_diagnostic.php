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
 * Emits the general legacy element compatibility deprecation diagnostic.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

/**
 * Emits a single general legacy-compatibility deprecation notice per legacy
 * customcertelement_* component/type per request.
 *
 * This is deliberately the ONLY place the general "this plugin is still using the
 * legacy runtime compatibility API" notice is emitted; it complements, and does not
 * duplicate, the existing method-specific deprecation debugging() calls already
 * emitted for individual legacy hooks (save_unique_data(), validate_form_elements(),
 * definition_after_data(), after_restore()). Those remain unchanged.
 *
 * Callers should invoke notify() at the narrowest coherent compatibility boundary:
 * where a legacy element is detected and wrapped by legacy_element_adapter (i.e. the
 * element factory), not scattered across render/form/persistence/validation/restore.
 */
final class legacy_compatibility_diagnostic {
    /** @var array<string, true> Components already warned about in this request. */
    private static array $warned = [];

    /**
     * Emit the general legacy compatibility warning for a component/type, at most
     * once per component per request.
     *
     * @param string $type Registry type key the element was registered under (e.g. 'foo').
     * @param string $class Fully-qualified class name of the wrapped legacy element.
     * @return void
     */
    public static function notify(string $type, string $class): void {
        $component = self::determine_component($type, $class);
        if (isset(self::$warned[$component])) {
            return;
        }
        self::$warned[$component] = true;

        debugging(
            "Legacy custom certificate element {$component} is using the deprecated mod_customcert legacy " .
            'element API. Migrate the plugin to Element System v2 interfaces. Legacy runtime compatibility ' .
            'will be removed in the Moodle 6.0-compatible release.',
            DEBUG_DEVELOPER
        );
    }

    /**
     * Determine the customcertelement_* component name for a legacy element class.
     *
     * Prefers the class's own customcertelement_* namespace when present (the real
     * plugin component); otherwise falls back to the registry type key.
     *
     * @param string $type Registry type key.
     * @param string $class Fully-qualified class name.
     * @return string
     */
    private static function determine_component(string $type, string $class): string {
        if (preg_match('/^\\\\?(customcertelement_[a-zA-Z0-9_]+)\\\\/', $class, $matches)) {
            return $matches[1];
        }
        return 'customcertelement_' . $type;
    }
}

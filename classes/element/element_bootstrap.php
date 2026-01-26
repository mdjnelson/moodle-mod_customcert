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
 * Registers default Custom Certificate element types with the element registry.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\service\element_registry;
use mod_customcert\element\provider\plugin_provider;
use customcertelement_text\element as text_element;
use customcertelement_image\element as image_element;
use customcertelement_date\element as date_element;
use customcertelement_grade\element as grade_element;
use customcertelement_coursename\element as coursename_element;
use customcertelement_code\element as code_element;
use customcertelement_bgimage\element as bgimage_element;
use customcertelement_border\element as border_element;
use customcertelement_categoryname\element as categoryname_element;
use customcertelement_coursefield\element as coursefield_element;
use customcertelement_digitalsignature\element as digitalsignature_element;
use customcertelement_expiry\element as expiry_element;
use customcertelement_gradeitemname\element as gradeitemname_element;
use customcertelement_qrcode\element as qrcode_element;
use customcertelement_studentname\element as studentname_element;
use customcertelement_teachername\element as teachername_element;
use customcertelement_userfield\element as userfield_element;
use customcertelement_userpicture\element as userpicture_element;

/**
 * Bootstrap helper to register bundled and discovered element types.
 */
final class element_bootstrap {
    /**
     * Register the bundled element types to their existing classes.
     *
     * Note: This does not wire any runtime path; callers should invoke this
     * method explicitly (e.g., in tests or during controlled initialization).
     *
     * @param element_registry $registry Element registry to receive registrations.
     * @param plugin_provider|null $provider Optional provider for customcertelement plugin discovery.
     * @return void
     */
    public static function register_defaults(element_registry $registry, ?plugin_provider $provider = null): void {
        // Core/bundled elements shipped with mod_customcert.
        $registry->register('text', text_element::class);
        $registry->register('image', image_element::class);
        $registry->register('date', date_element::class);
        $registry->register('grade', grade_element::class);
        $registry->register('coursename', coursename_element::class);
        $registry->register('code', code_element::class);
        $registry->register('bgimage', bgimage_element::class);
        $registry->register('border', border_element::class);
        $registry->register('categoryname', categoryname_element::class);
        $registry->register('coursefield', coursefield_element::class);
        $registry->register('digitalsignature', digitalsignature_element::class);
        $registry->register('expiry', expiry_element::class);
        $registry->register('gradeitemname', gradeitemname_element::class);
        $registry->register('qrcode', qrcode_element::class);
        $registry->register('studentname', studentname_element::class);
        $registry->register('teachername', teachername_element::class);
        $registry->register('userfield', userfield_element::class);
        $registry->register('userpicture', userpicture_element::class);

        // Auto-discover third-party customcertelement_* plugins and register them.
        // This preserves explicit core registrations while enabling ecosystem compatibility.
        // Discovery with simple in-request memoization to avoid repeated scanning.
        static $discovered = []; // Cache keyed by provider class name.
        try {
            $provider = $provider ?? new provider\core_plugin_provider();
            $cachekey = get_class($provider);
            if (!array_key_exists($cachekey, $discovered)) {
                $discovered[$cachekey] = [];
                $plugins = $provider->get_plugins();
                foreach ($plugins as $name => $unused) {
                    $type = (string)$name; // E.g., 'foobar' for customcertelement_foobar.
                    $classname = "\\customcertelement_{$type}\\element";
                    if (class_exists($classname)) {
                        $discovered[$cachekey][$type] = $classname;
                    } else if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                        $missingclass = "\\customcertelement_{$type}\\element";
                        debugging(
                            "Found plugin 'customcertelement_{$type}' but missing element class {$missingclass}.",
                            DEBUG_DEVELOPER
                        );
                    }
                }
            }
            // Register discovered classes that aren't already in the registry.
            foreach ($discovered[$cachekey] as $type => $classname) {
                if ($registry->has($type)) {
                    continue;
                }
                try {
                    $registry->register($type, $classname);
                } catch (\Throwable $e) {
                    debugging("Failed to register customcertelement '{$type}': {$e->getMessage()}", DEBUG_DEVELOPER);
                }
            }
        } catch (\Throwable $e) {
            if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                debugging('Element discovery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}

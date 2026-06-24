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
 * Feature, language, cron, and miscellaneous callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

/**
 * Handles feature support, language resolution, cron, and miscellaneous callbacks for customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feature_callbacks {
    /**
     * The features this activity supports.
     *
     * @uses FEATURE_GROUPS
     * @uses FEATURE_GROUPINGS
     * @uses FEATURE_GROUPMEMBERSONLY
     * @uses FEATURE_MOD_INTRO
     * @uses FEATURE_COMPLETION_TRACKS_VIEWS
     * @uses FEATURE_GRADE_HAS_GRADE
     * @uses FEATURE_GRADE_OUTCOMES
     * @param string $feature FEATURE_xx constant for requested feature
     * @return mixed True if module supports feature, null if doesn't know
     */
    public static function supports(string $feature) {
        switch ($feature) {
            case FEATURE_GROUPINGS:
            case FEATURE_MOD_INTRO:
            case FEATURE_SHOW_DESCRIPTION:
            case FEATURE_COMPLETION_TRACKS_VIEWS:
            case FEATURE_BACKUP_MOODLE2:
            case FEATURE_GROUPS:
                return true;
            case FEATURE_MOD_PURPOSE:
                return MOD_PURPOSE_OTHER;
            default:
                return null;
        }
    }

    /**
     * Used for course participation report (in case customcert is added).
     *
     * @return array
     */
    public static function get_view_actions(): array {
        return ['view', 'view all', 'view report'];
    }

    /**
     * Used for course participation report (in case customcert is added).
     *
     * @return array
     */
    public static function get_post_actions(): array {
        return ['received'];
    }

    /**
     * Function to be run periodically according to the moodle cron.
     *
     * @return bool
     */
    public static function cron(): bool {
        return true;
    }

    /**
     * Get icon mapping for font-awesome.
     *
     * @return array
     */
    public static function get_fontawesome_icon_map(): array {
        return [
            'mod_customcert:download' => 'fa-download',
        ];
    }

    /**
     * Determine which language should be used for this certificate or email.
     *
     * Precedence:
     *   1. Certificate's forced language.
     *   2. Course language.
     *   3. User profile language.
     *   4. Site default.
     *
     * @param \stdClass $customcert Certificate record.
     * @param \stdClass|null $user Target user - falls back to global $USER if not specified.
     * @param string|null $courselang Course language, if available.
     * @return string Language code to use.
     */
    public static function get_language_to_use(
        \stdClass $customcert,
        ?\stdClass $user = null,
        ?string $courselang = null
    ): string {
        global $CFG, $USER;

        if (empty($user)) {
            $user = $USER;
        }

        if (!empty($customcert->language)) {
            return $customcert->language;
        }

        if (!empty($courselang)) {
            return $courselang;
        }

        if (!empty($user) && !empty($user->lang)) {
            return $user->lang;
        }

        return $CFG->lang;
    }

    /**
     * Apply a runtime language switch for the current execution context.
     *
     * @param string $language The language code (e.g. 'es_co')
     * @return bool True if language was switched.
     */
    public static function apply_runtime_language(string $language): bool {
        if (empty($language)) {
            return false;
        }

        $activelangs = get_string_manager()->get_list_of_translations();
        $current = current_language();

        if (array_key_exists($language, $activelangs) && $language !== $current) {
            force_current_language($language);
            return true;
        }

        return false;
    }
}

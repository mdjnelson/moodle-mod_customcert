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
 * Moodle callback bridge for the customcert module.
 *
 * This file exists solely to satisfy Moodle's global-function discovery mechanism.
 * It contains no implementation logic — every function delegates immediately to a
 * typed, namespaced callback class under classes/callback/.
 *
 * Callback classes:
 *   - mod_customcert\callback\instance_callbacks   — add/update/delete instance lifecycle
 *   - mod_customcert\callback\course_callbacks     — course reset and user activity reports
 *   - mod_customcert\callback\file_callbacks       — file serving (pluginfile)
 *   - mod_customcert\callback\navigation_callbacks — settings navigation and profile nodes
 *   - mod_customcert\callback\ajax_callbacks       — fragment and inplace-editable callbacks
 *   - mod_customcert\callback\feature_callbacks    — feature flags, language, cron, misc
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_customcert\callback\ajax_callbacks;
use mod_customcert\callback\course_callbacks;
use mod_customcert\callback\feature_callbacks;
use mod_customcert\callback\file_callbacks;
use mod_customcert\callback\instance_callbacks;
use mod_customcert\callback\navigation_callbacks;

/**
 * Add customcert instance.
 *
 * @param stdClass $data
 * @param mod_customcert_mod_form $mform
 * @return int new customcert instance id
 */
function customcert_add_instance($data, $mform) {
    return instance_callbacks::add_instance($data, $mform);
}

/**
 * Update customcert instance.
 *
 * @param stdClass $data
 * @param mod_customcert_mod_form $mform
 * @return bool true
 */
function customcert_update_instance($data, $mform) {
    return instance_callbacks::update_instance($data, $mform);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool true if successful
 */
function customcert_delete_instance($id) {
    return instance_callbacks::delete_instance((int)$id);
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified customcert
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function customcert_reset_userdata($data) {
    return course_callbacks::reset_userdata($data);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the customcert.
 *
 * @param mod_customcert_mod_form $mform form passed by reference
 */
function customcert_reset_course_form_definition(&$mform) {
    course_callbacks::reset_course_form_definition($mform);
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function customcert_reset_course_form_defaults($course) {
    return course_callbacks::reset_course_form_defaults($course);
}

/**
 * Returns information about received customcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $customcert
 * @return stdClass the user outline object
 */
function customcert_user_outline($course, $user, $mod, $customcert) {
    return course_callbacks::user_outline($course, $user, $mod, $customcert);
}

/**
 * Returns information about received customcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $customcert
 * @return string the user complete information
 */
function customcert_user_complete($course, $user, $mod, $customcert) {
    course_callbacks::user_complete($course, $user, $mod, $customcert);
}

/**
 * Serves certificate issues and other files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool|null false if file not found, does not return anything if found - just send the file
 */
function customcert_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    return file_callbacks::pluginfile($course, $cm, $context, $filearea, $args, $forcedownload);
}

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
function customcert_supports($feature) {
    return feature_callbacks::supports($feature);
}

/**
 * Used for course participation report (in case customcert is added).
 *
 * @return array
 */
function customcert_get_view_actions() {
    return feature_callbacks::get_view_actions();
}

/**
 * Used for course participation report (in case customcert is added).
 *
 * @return array
 */
function customcert_get_post_actions() {
    return feature_callbacks::get_post_actions();
}

/**
 * Function to be run periodically according to the moodle cron.
 */
function customcert_cron() {
    return feature_callbacks::cron();
}

/**
 * Serve the edit element as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_customcert_output_fragment_editelement($args) {
    return ajax_callbacks::output_fragment_editelement($args);
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called.
 *
 * @param settings_navigation $settings
 * @param navigation_node $customcertnode
 */
function customcert_extend_settings_navigation(settings_navigation $settings, navigation_node $customcertnode) {
    return navigation_callbacks::extend_settings_navigation($settings, $customcertnode);
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 * @return void
 */
function mod_customcert_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    navigation_callbacks::myprofile_navigation($tree, $user, $iscurrentuser, $course);
}

/**
 * Handles editing the 'name' of the element in a list.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable
 */
function mod_customcert_inplace_editable($itemtype, $itemid, $newvalue) {
    return ajax_callbacks::inplace_editable($itemtype, (int)$itemid, $newvalue);
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_customcert_get_fontawesome_icon_map() {
    return feature_callbacks::get_fontawesome_icon_map();
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
 * @param stdClass $customcert Certificate record.
 * @param stdClass|null $user Target user - falls back to global $USER if not specified.
 * @param string|null $courselang Course language, if available.
 * @return string Language code to use.
 */
function mod_customcert_get_language_to_use(stdClass $customcert, ?stdClass $user = null, ?string $courselang = null): string {
    return feature_callbacks::get_language_to_use($customcert, $user, $courselang);
}

/**
 * Apply a runtime language switch for the current execution context.
 *
 * @param string $language The language code (e.g. 'es_co')
 * @return bool True if language was switched.
 */
function mod_customcert_apply_runtime_language(string $language): bool {
    return feature_callbacks::apply_runtime_language($language);
}

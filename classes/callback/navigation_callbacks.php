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
 * Navigation and profile callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

/**
 * Handles settings navigation and user profile navigation callbacks for customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_callbacks {
    /**
     * This function extends the settings navigation block for the site.
     *
     * It is safe to rely on PAGE here as we will only ever be within the module
     * context when this is called.
     *
     * @param \settings_navigation $settings
     * @param \navigation_node $customcertnode
     * @return mixed Result of trim_if_empty().
     */
    public static function extend_settings_navigation(
        \settings_navigation $settings,
        \navigation_node $customcertnode
    ) {
        global $DB, $PAGE;

        $keys = $customcertnode->get_children_key_list();
        $beforekey = null;
        $i = array_search('modedit', $keys);
        if ($i === false && array_key_exists(0, $keys)) {
            $beforekey = $keys[0];
        } else if (array_key_exists($i + 1, $keys)) {
            $beforekey = $keys[$i + 1];
        }

        if (has_capability('mod/customcert:manage', $settings->get_page()->cm->context)) {
            // Get the template id.
            $templateid = $DB->get_field('customcert', 'templateid', ['id' => $settings->get_page()->cm->instance]);
            $node = \navigation_node::create(
                get_string('editcustomcert', 'customcert'),
                new \moodle_url('/mod/customcert/edit.php', ['tid' => $templateid]),
                \navigation_node::TYPE_SETTING,
                null,
                'mod_customcert_edit',
                new \pix_icon('t/edit', '')
            );
            $customcertnode->add_node($node, $beforekey);
        }

        if (has_capability('mod/customcert:verifycertificate', $settings->get_page()->cm->context)) {
            $node = \navigation_node::create(
                get_string('verifycertificate', 'customcert'),
                new \moodle_url(
                    '/mod/customcert/verify_certificate.php',
                    ['contextid' => $settings->get_page()->cm->context->id]
                ),
                \navigation_node::TYPE_SETTING,
                null,
                'mod_customcert_verify_certificate',
                new \pix_icon('t/check', '')
            );
            $customcertnode->add_node($node, $beforekey);
        }

        return $customcertnode->trim_if_empty();
    }

    /**
     * Add nodes to myprofile page.
     *
     * @param \core_user\output\myprofile\tree $tree Tree object
     * @param \stdClass $user user object
     * @param bool $iscurrentuser
     * @param \stdClass $course Course object
     * @return void
     */
    public static function myprofile_navigation(
        \core_user\output\myprofile\tree $tree,
        \stdClass $user,
        bool $iscurrentuser,
        $course
    ): void {
        global $USER;

        if (
            ($user->id != $USER->id)
                && !has_capability('mod/customcert:viewallcertificates', \context_system::instance())
        ) {
            return;
        }

        $params = [
            'userid' => $user->id,
        ];
        if ($course) {
            $params['course'] = $course->id;
        }
        $url = new \moodle_url('/mod/customcert/my_certificates.php', $params);
        $node = new \core_user\output\myprofile\node(
            'miscellaneous',
            'mycustomcerts',
            get_string('mycertificates', 'customcert'),
            null,
            $url
        );
        $tree->add_node($node);
    }
}

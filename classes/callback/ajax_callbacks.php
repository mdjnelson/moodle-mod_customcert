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
 * Fragment and inplace editable callback implementations.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert\callback;

use core\output\inplace_editable;
use mod_customcert\edit_element_form;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_repository;
use mod_customcert\template;

/**
 * Handles fragment and inplace editable callbacks for customcert.
 *
 * @package    mod_customcert
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ajax_callbacks {
    /**
     * Serve the edit element as a fragment.
     *
     * @param array $args List of named arguments for the fragment loader.
     * @return string
     */
    public static function output_fragment_editelement(array $args): string {
        $factory = element_factory::build_with_defaults();
        $elementrepo = new element_repository($factory);

        // Require both elementid and templateid to be supplied.
        if (empty($args['elementid']) || empty($args['templateid'])) {
            throw new \moodle_exception('nopermissions', 'error', '', 'editelement');
        }

        // Verify the supplied templateid belongs to the supplied fragment context.
        $templaterepo = new template_repository();
        $templaterecord = $templaterepo->get_by_id_or_fail((int)$args['templateid']);
        $authorisedcontext = \context::instance_by_id((int)$args['context']->id);
        if ((int)$templaterecord->contextid !== (int)$authorisedcontext->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'editelement');
        }

        // Load the element, verifying it belongs to the authorised template.
        $element = $elementrepo->get_for_template_or_fail((int)$args['templateid'], (int)$args['elementid']);

        $pageurl = new \moodle_url('/mod/customcert/rearrange.php', ['pid' => $element->pageid]);
        $form = new edit_element_form($pageurl, ['element' => $element]);

        return $form->render();
    }

    /**
     * Handles editing the 'name' of the element in a list.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param string $newvalue
     * @return inplace_editable
     */
    public static function inplace_editable(string $itemtype, int $itemid, string $newvalue): ?inplace_editable {
        global $PAGE;

        if ($itemtype === 'elementname') {
            $factory = element_factory::build_with_defaults();
            $elementrepo = new element_repository($factory);

            // This callback receives only $itemid with no template context; ownership is resolved by walking
            // element -> page -> template immediately after. customcert-allow-raw-element-lookup.
            $element = $elementrepo->get_by_id_or_fail((int)$itemid); // @codingStandardsIgnoreLine
            $page = (new page_repository())->get_by_id_or_fail((int)$element->pageid);

            // Set the template object.
            $template = template::from_record((new template_repository())->get_by_id_or_fail((int)$page->templateid));
            // Perform checks.
            if ($cm = $template->get_cm()) {
                require_login($cm->course, false, $cm);
            } else {
                $PAGE->set_context(\context_system::instance());
                require_login();
            }
            // Make sure the user has the required capabilities.
            $template->require_manage();

            // Clean input and update the record.
            $newname = clean_param($newvalue, PARAM_TEXT);
            $elementrepo->update_name((int)$element->id, $newname, (int)$template->get_contextid());

            return new inplace_editable(
                'mod_customcert',
                'elementname',
                $element->id,
                true,
                $newname,
                $newname
            );
        }

        return null;
    }
}

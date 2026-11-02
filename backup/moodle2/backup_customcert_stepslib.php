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
 * Define all the backup steps that will be used by the backup_customcert_activity_task.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete customcert structure for backup, with file and id annotations.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_customcert_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure of the backup file.
     *
     * @return backup_nested_element
     */
    #[\Override]
    protected function define_structure() {

        $customcert = new backup_nested_element('customcert', ['id'], [
            'templateid', 'name', 'intro', 'introformat', 'requiredtime', 'verifyany',
            'deliveryoption', 'usecustomfilename', 'customfilenamepattern', 'emailstudents',
            'emailteachers', 'emailothers', 'issueautomatically', 'completionemailed', 'protection',
            'language', 'timecreated', 'timemodified']);

        $template = new backup_nested_element('template', ['id'], [
            'name', 'contextid', 'timecreated', 'timemodified']);

        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', ['id'], [
            'templateid', 'width', 'height', 'leftmargin', 'rightmargin',
            'sequence', 'timecreated', 'timemodified']);

        // The elements.
        // Note: Legacy columns 'width', 'font', 'fontsize' and 'colour' were removed. Values live in JSON 'data'.
        $element = new backup_nested_element('element', ['id'], [
            'pageid', 'name', 'element', 'data', 'posx', 'posy', 'refpoint', 'sequence',
            'alignment', 'timecreated', 'timemodified']);

        $issues = new backup_nested_element('issues');
        $issue = new backup_nested_element('issue', ['id'], [
            'customcertid', 'userid', 'timecreated', 'emailed', 'studentemailed', 'code']);

        $customcert->add_child($issues);
        $issues->add_child($issue);
        $customcert->add_child($template);
        $template->add_child($pages);
        $pages->add_child($page);
        $page->add_child($element);

        $customcert->set_source_table('customcert', ['id' => backup::VAR_ACTIVITYID]);

        $template->set_source_table('customcert_templates', ['contextid' => backup::VAR_CONTEXTID]);

        $page->set_source_table('customcert_pages', ['templateid' => backup::VAR_PARENTID]);

        $element->set_source_table('customcert_elements', ['pageid' => backup::VAR_PARENTID]);

        if ($this->get_setting_value('userinfo')) {
            $issue->set_source_table('customcert_issues', ['customcertid' => backup::VAR_ACTIVITYID]);
        }

        $issue->annotate_ids('user', 'userid');

        $customcert->annotate_files('mod_customcert', 'intro', null);
        $customcert->annotate_files('mod_customcert', 'image', null, context_course::instance($this->get_courseid())->id);

        return $this->prepare_activity_structure($customcert);
    }
}

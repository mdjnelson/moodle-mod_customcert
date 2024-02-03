<?php
// This file is part of Moodle - http://moodle.org/
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
 * Class represents a local file of an issued certificate.
 *
 * @package    mod_customcert
 * @copyright  2023 Giorgio Consorti <g.consorti@lynxlab.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use file_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Class represents a local file of an issued certificate.
 *
 * @package    mod_customcert
 * @copyright  023 Giorgio Consorti <g.consorti@lynxlab.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localfile {

    /**
     * The template representing the content of the file.
     *
     * @var \mod_customcert\template
     */
    protected $template;

    /**
     * The component name for the file storage.
     */
    const component = 'mod_customcert';

    /**
     * The filearea name for the file storage.
     */
    const filearea =  'customcert_issues';

    /**
     * The constructor.
     *
     * @param \mod_customcert\template $template
     */
    public function __construct(\mod_customcert\template $template) {
        $this->template = $template;
    }

    /**
     * Save the PDF to the file storage.
     *
     * @param string $pdfcontent string content of the pdf
     * @param integer|null $userid the id of the user whose certificate we want to save
     * @return stored_file|false the stored_file object on success, false on error
     */
    public function savePDF(string $pdfcontent, ?int $userid = null) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($userid)) {
            $userid = $USER->id;
        }

        try {
            $file = $this->getPDF($userid);
            if (!$file) {
                // Create file containing the pdf
                $fs = get_file_storage();
                $file =  $fs->create_file_from_string($this->buildFileInfo($userid), $pdfcontent);
            }
            return $file;
        } catch (file_exception $e) {
            // maybe log the exception
            return false;
        }
    }

    /**
     * Get the PDF from the file storage.
     *
     * @param integer|null $userid the id of the user whose certificate we want to get
     * @return \stored_file|false the stored_file object on success, false on error
     */
    public function getPDF(?int $userid = null) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $fileinfo = $this->buildFileInfo($userid);
        $fs = get_file_storage();
        return $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
               $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
    }

    /**
     * Delete the PDF from the file storage.
     *
     * @param integer|null $userid the id of the user whose certificate we want to get
     * @return bool true on success
     */
    public function deletePDF(?int $userid = null) {
        global $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        try {
            $file = $this->getPDF($userid);
            if ($file) {
                return $file->delete();
            }
            return false;
        } catch (file_exception $e) {
            // maybe log the exception
            return false;
        }
    }

    /**
     * Send the PDF to the browser or return it as a string.
     *
     * @param int $userid the id of the user whose certificate we want to view
     * @param string $deliveryoption the delivery option of the customcert
     * @param bool $return Do we want to return the contents of the PDF?
     * @return string|void Can return the PDF in string format if specified.
     */
    public function sendPDF(?int $userid = NULL, string $deliveryoption = certificate::DELIVERY_OPTION_DOWNLOAD, bool $return = false) {
        global $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $file = $this->getPDF($userid);
        if ($file) {
            if ($return) {
                return $file->get_content();
            } else {
                // send the file to the browser
                send_stored_file(
                    $file,
                    0,
                    0,
                    $deliveryoption == certificate::DELIVERY_OPTION_DOWNLOAD,
                    ['filename' => $file->get_filename()]
                );
                die();
            }
        }
    }

    /**
     * Check if a pdf exists in the file storage area.
     *
     * @param \stdClass $cm the course module
     * @param integer|null $userid the id of the user whose PDF we want to check
     * @param integer|null $templateid the template id of the customcert we want to check
     * @return \stored_file|false the stored_file object on success, false on error
     */
    public static function existsPDF($cm, ?int $userid = null, ?int $templateid = null) {

        $fileinfo =  self::buildFileInfoArr($cm, $userid, $templateid);
        $fs = get_file_storage();
        return $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
               $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
    }

    /**
     * Build the fileinfo array needed by the file storage.
     *
     * @param integer|null $userid the id of the user whose fileinfo array we want to generate
     * @return array the fileinfo array
     */
    protected function buildFileInfo(?int $userid = null) {

        return self::buildFileInfoArr($this->template->get_cm(), $userid, $this->template->get_id());
    }

    /**
     * Build the fileinfo array needed by the file storage, static version.
     *
     * @param \stdClass $cm the course module
     * @param integer|null $userid the id of the user whose fileinfo array we want to generate
     * @param integer|null $templateid the template id of the customcert of the array we want to generate
     * @return array the fileinfo array
     */
    private static function buildFileInfoArr ($cm, ?int $userid = null, ?int $templateid = null) {

        /** @var \moodle_database $DB */
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        if (empty($templateid)) {
            $customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
            $templateid = $customcert->templateid;
        }

        $course = $DB->get_record('course', ['id' => $cm->course]);
        $context = $DB->get_record('context', ['contextlevel' => '50', 'instanceid' => $course->id]);
        $user_info = $DB->get_record('user', ['id' => $userid]);

        return [
            'contextid' => $context->id,
            'component' => self::component,
            'filearea' => self::filearea,
            'itemid' => $templateid,
            'userid' => $USER->id,
            'author' => fullname($USER),
            'filepath' => '/' . $course->id . '/',
            'filename' => self::buildFileName($user_info->username, $templateid, $course->shortname),
        ];
    }

    /**
     * Build the PDF filename.
     *
     * @param string $username
     * @param string $templateid
     * @param string $courseShortname
     * @return string the PDF file name
     */
    public static function buildFileName($username, $templateid, $courseShortname) {
        return $username . '_cert-' . $templateid . '_course-' . $courseShortname . '.pdf';
    }
}

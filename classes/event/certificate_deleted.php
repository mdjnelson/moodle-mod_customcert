<?php
namespace mod_customcert\event;

// Prevent direct access to this file.
defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a certificate issue is deleted.
 *
 * This class defines the event for when a record is deleted from the `customcert_issues` table.
 */
class certificate_deleted extends \core\event\base {

    /**
     * Initialize event properties.
     *
     * Sets the CRUD type to 'd' (delete), the educational level to LEVEL_OTHER,
     * and the object table this event is related to.
     */
    protected function init() {
        $this->data['crud'] = 'd'; // d = delete
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'customcert_issues';
    }

    /**
     * Returns the localized name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcertificatedeleted', 'mod_customcert');
    }

    /**
     * Returns a description of the event for logging/debugging.
     *
     * @return string
     */
    public function get_description() {
        return "The certificate issue with id '{$this->objectid}' was deleted. It belonged to user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns a relevant URL for viewing more information about the event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }
}

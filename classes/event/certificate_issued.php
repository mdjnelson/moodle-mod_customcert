<?php
namespace mod_customcert\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event class for when a custom certificate is issued to a user.
 *
 * This event is triggered when a new row is inserted into the customcert_issues table.
 */
class certificate_issued extends \core\event\base {

    /**
     * Initialize the event properties.
     *
     * - 'crud' indicates the type of action: 'c' = create
     * - 'edulevel' classifies the nature of the event. LEVEL_OTHER means it's not directly teaching-related.
     * - 'objecttable' is the table this event relates to.
     */
    protected function init() {
        $this->data['crud'] = 'c'; // Indicates a 'create' operation
        $this->data['edulevel'] = self::LEVEL_OTHER; // Not teaching, participation, etc.
        $this->data['objecttable'] = 'customcert_issues'; // The DB table this event pertains to
    }

    /**
     * Returns the localized event name.
     *
     * @return string The name of the event.
     */
    public static function get_name() {
        return get_string('eventcertificateissued', 'mod_customcert');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string A detailed description of the event.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' was issued a custom certificate with issue id '{$this->objectid}'.";
    }

    /**
     * Returns the URL relevant to the event.
     *
     * @return \moodle_url A URL to view the certificate or related activity.
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }
}

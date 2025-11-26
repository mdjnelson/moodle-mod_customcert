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
 * Class for a customcert specific fullname
 * @package       mod_customcert
 * @copyright     2025 University of Vienna
 * @since         Moodle 5.0
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_customcert;

use core\context;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for a customcert specific fullname (adapted version of the original core get_fullname function)
 *
 * @package    mod_customcert
 * @copyright  2025 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fullname_certificate {
    /**
     * Return full name depending on context.
     * This function should be used for displaying purposes only as the details may not be the same as it is on database.
     *
     * @param stdClass $user the person to get details of.
     * @param context|null $context The context will be used to determine the visibility of the user's full name.
     * @param array $options can include: override - if true, will not use forced firstname/lastname settings
     * @return string Full name of the user
     */
    public static function mod_customcert_get_fullname(stdClass $user, ?context $context = null, array $options = []): string {
        global $CFG, $SESSION;
        
        // Clone the user so that it does not mess up the original object.
        $user = clone($user);
        
        // Override options.
        $override = $options["override"] ?? false;
        
        if (!isset($user->firstname) && !isset($user->lastname)) {
            return '';
        }
        
        // Get all of the name fields.
        $allnames = \core_user\fields::get_name_fields();
        if ($CFG->debugdeveloper) {
            $missingfields = [];
            foreach ($allnames as $allname) {
                if (!property_exists($user, $allname)) {
                    $missingfields[] = $allname;
                }
            }
            if (!empty($missingfields)) {
                debugging('The following name fields are missing from the user object: ' . implode(', ', $missingfields));
            }
        }
        
        $template = null;
        // If the fullnamedisplay setting is available, set the template to that.
        $fullnamealternative = get_config('customcert', 'fullnameformat');
        if ($fullnamealternative) {
            $template = $fullnamealternative;
        }
        
        // If the template is empty, or set to language, return the language string.
        if ((empty($template) || $template == 'language') && !$override) {
            return get_string('fullnamedisplay', null, $user);
        }
        
        // Check to see if we are displaying according to the alternative full name format.
        if ($override) {
            if (empty($CFG->alternativefullnameformat) || $CFG->alternativefullnameformat == 'language') {
                // Default to show just the user names according to the fullnamedisplay string.
                return get_string('fullnamedisplay', null, $user);
            } else {
                // If the override is true, then change the template to use the complete name.
                $template = $CFG->alternativefullnameformat;
            }
        }
        
        $requirednames = [];
        // With each name, see if it is in the display name template, and add it to the required names array if it is.
        foreach ($allnames as $allname) {
            if (strpos($template, $allname) !== false) {
                $requirednames[] = $allname;
            }
        }
        
        $displayname = $template;
        // Switch in the actual data into the template.
        foreach ($requirednames as $altname) {
            if (isset($user->$altname)) {
                // Using empty() on the below if statement causes breakages.
                if ((string)$user->$altname == '') {
                    $displayname = str_replace($altname, 'EMPTY', $displayname);
                } else {
                    $displayname = str_replace($altname, $user->$altname, $displayname);
                }
            } else {
                $displayname = str_replace($altname, 'EMPTY', $displayname);
            }
        }
        // Tidy up any misc. characters (Not perfect, but gets most characters).
        // Don't remove the "u" at the end of the first expression unless you want garbled characters when combining hiragana or
        // katakana and parenthesis.
        $patterns = [];
        // This regular expression replacement is to fix problems such as 'James () Kirk' Where 'Tiberius' (middlename) has not been
        // filled in by a user.
        // The special characters are Japanese brackets that are common enough to make allowances for them (not covered by :punct:).
        $patterns[] = '/[[:punct:]「」]*EMPTY[[:punct:]「」]*/u';
        // This regular expression is to remove any double spaces in the display name.
        $patterns[] = '/\s{2,}/u';
        foreach ($patterns as $pattern) {
            $displayname = preg_replace($pattern, ' ', $displayname);
        }
        
        // Trimming $displayname will help the next check to ensure that we don't have a display name with spaces.
        $displayname = trim($displayname);
        if (empty($displayname)) {
            // Going with just the first name if no alternate fields are filled out. May be changed later depending on what
            // people in general feel is a good setting to fall back on.
            $displayname = $user->firstname;
        }
        return $displayname;
    }
}

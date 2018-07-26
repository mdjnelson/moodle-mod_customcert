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
 * Strings for component 'customcertelement_daterange', language 'en'.
 *
 * @package    customcertelement_daterange
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

$string['completiondate'] = 'Completion date';
$string['courseenddate'] = 'Course end date';
$string['coursegradedate'] = 'Course grade date';
$string['coursestartdate'] = 'Course start date';
$string['dateitem'] = 'Date item';
$string['dateitem_help'] = 'This will be the date that is printed on the certificate';
$string['dateranges'] = 'Dateranges';
$string['fallbackstring'] = 'Fallback string';
$string['fallbackstring_help'] = 'This string will be displayed if no daterange applied to a date. If Fallback string is not set, then there will be no output at all.';
$string['help'] = 'Configure a string representation for each daterange. Set start and end dates as well as a string you would like to transform each range to. Make sure your ranges do not overlap, otherwise the first detected daterange will be applied. If no daterange applied to a date, then Fallback string will be displayed. If Fallback string is not set, then there will be no output at all.';
$string['issueddate'] = 'Issued date';
$string['maxranges'] = 'Maximum number ranges';
$string['maxranges_desc'] = 'Set a maximum number of date ranges per each element';
$string['pluginname'] = 'Date range';
$string['privacy:metadata'] = 'The Date range plugin does not store any personal data.';
$string['start'] = 'Start';
$string['end'] = 'End';
$string['datestring'] = 'String representation';
$string['daterange'] = 'Daterange {$a}';
$string['error:enabled'] = 'You must have at least one datarange enabled';
$string['error:datestring'] = 'You must provide string representation for the enabled datarange';
$string['error:enddate'] = 'End date must be after Start date';
$string['preview'] = 'Preview {$a}';

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
 * CI guardrail: detect unsafe raw element lookups in public/request-facing files.
 *
 * This script scans a small explicit list of public handler files for direct
 * element_repository::get_by_id_or_fail() calls that bypass scoped ownership
 * checks. Public handlers that already have an authorised template should use:
 *
 *   element_repository::get_for_template_or_fail($templateid, $elementid)
 *
 * instead of loading an element by raw ID and manually verifying ownership.
 *
 * Usage (from the mod/customcert directory):
 *   php tools/check_element_ownership_lookups.php
 *
 * Exit codes:
 *   0 - no unsafe lookups found (or all findings are marked as allowed)
 *   1 - one or more unsafe lookups found
 *
 * To wire into CI add a step such as:
 *   php mod/customcert/tools/check_element_ownership_lookups.php
 *
 * To allow an intentional raw lookup, add the following marker on the same line
 * or the immediately preceding line, with a short explanation:
 *
 *   // customcert-allow-raw-element-lookup: <reason why scoped helper cannot be used>
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This is a standalone CLI tool; it does not bootstrap Moodle.
define('MOODLE_INTERNAL', true);

// Root of the mod_customcert plugin (one level up from this script).
define('PLUGIN_ROOT', dirname(__DIR__));

/**
 * Public/request-facing files to scan (relative to PLUGIN_ROOT).
 *
 * Only these explicit top-level request entry points are checked.
 * Repository internals, service classes, tests, and fixtures are intentionally
 * excluded to avoid false positives. This tool is not a general static analyser.
 *
 * lib.php is included because it contains the mod_customcert_inplace_editable()
 * callback which performs a raw element lookup. That lookup is marked with the
 * inline allow marker because no authorised template is available at that point.
 * New element-management request handlers should not be added to lib.php; they
 * should live in the other scanned request entry points listed below.
 *
 * Scoped lookups (element_repository::get_for_template_or_fail) should be used
 * when an authorised template/context is already available.
 */
// @codingStandardsIgnoreLine
const FILES_TO_SCAN = [
    'ajax.php',
    'download_all_certificates.php',
    'edit.php',
    'edit_element.php',
    'export_template.php',
    'import_template.php',
    'index.php',
    'load_template.php',
    'manage_templates.php',
    'my_certificates.php',
    'rearrange.php',
    'report.php',
    'upload_image.php',
    'verify_certificate.php',
    'view.php',
    'classes/external.php',
    'lib.php',
];

/**
 * Regex pattern that identifies a suspicious raw element lookup.
 *
 * Matches things like:
 *   $elementrepo->get_by_id_or_fail($elementid)
 *   $elementrepo->get_by_id_or_fail((int)$elementid)
 *   $this->elements->get_by_id_or_fail(...)
 * but NOT template/page/certificate/issue repository calls.
 */
const UNSAFE_PATTERN = '/\$(?:elementrepo|this->elements)\s*->\s*get_by_id_or_fail\s*\(/';

/**
 * Inline allow marker used to suppress a finding for a known-safe raw lookup.
 *
 * Place this marker on the same line as the raw lookup, or on the immediately
 * preceding line, with a short explanation of why the scoped helper cannot be
 * used at that point.
 *
 * Example:
 *   // customcert-allow-raw-element-lookup: no authorized template available here.
 *   $element = $elementrepo->get_by_id_or_fail($itemid);
 */
const ALLOW_MARKER = 'customcert-allow-raw-element-lookup';

// Scanner.

$violations = [];

foreach (FILES_TO_SCAN as $relpath) {
    $fullpath = PLUGIN_ROOT . '/' . $relpath;

    if (!is_file($fullpath)) {
        fwrite(STDERR, "check_element_ownership_lookups: configured file not found: {$relpath}\n");
        exit(1);
    }

    $lines = file($fullpath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "check_element_ownership_lookups: could not read {$relpath}\n");
        exit(1);
    }

    foreach ($lines as $lineno0 => $linetext) {
        $lineno = $lineno0 + 1; // 1-based.

        if (!preg_match(UNSAFE_PATTERN, $linetext)) {
            continue;
        }

        // Check for inline allow marker on this line or the preceding line.
        $prevline = ($lineno0 > 0) ? $lines[$lineno0 - 1] : '';
        if (strpos($linetext, ALLOW_MARKER) !== false || strpos($prevline, ALLOW_MARKER) !== false) {
            continue;
        }

        $violations[] = [
            'file' => $relpath,
            'line' => $lineno,
            'text' => trim($linetext),
        ];
    }
}

// Report.

if (empty($violations)) {
    echo "check_element_ownership_lookups: OK - no unsafe raw element lookups found.\n";
    exit(0);
}

fwrite(STDERR, "\n");
fwrite(STDERR, "check_element_ownership_lookups: FAILED\n");
fwrite(STDERR, str_repeat('-', 72) . "\n");

foreach ($violations as $v) {
    fwrite(STDERR, "Unsafe raw element lookup found in public handler:\n");
    fwrite(STDERR, sprintf("  %s:%d\n", $v['file'], $v['line']));
    fwrite(STDERR, sprintf("  %s\n", $v['text']));
    fwrite(STDERR, "\n");
}

fwrite(STDERR, str_repeat('-', 72) . "\n");
fwrite(STDERR, "Use element_repository::get_for_template_or_fail(\$templateid, \$elementid)\n");
fwrite(STDERR, "when the caller already has an authorised template.\n");
fwrite(STDERR, "If this lookup is genuinely unavoidable, add the following marker on the\n");
fwrite(STDERR, "same line or the immediately preceding line:\n");
fwrite(STDERR, "  // customcert-allow-raw-element-lookup: <reason>\n");
fwrite(STDERR, "\n");

exit(1);

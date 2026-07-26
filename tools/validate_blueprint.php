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
 * CI policy checks for blueprint.json.
 *
 * Validates JSON syntax and enforces repository-specific rules that the
 * upstream JSON Schema cannot enforce (e.g. no swallowed exceptions,
 * presence of a studentname element). Run via:
 *
 *   php tools/validate_blueprint.php
 *
 * Exits 0 on success, 1 on failure.
 *
 * @package   mod_customcert
 * @copyright 2025 onwards Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MOODLE_INTERNAL', true);

define('BLUEPRINT_PATH', __DIR__ . '/../blueprint.json');

// @codingStandardsIgnoreLine
$errors = [];

// 1. File must exist and be readable.
if (!is_readable(BLUEPRINT_PATH)) {
    echo "validate_blueprint: FAIL — blueprint.json not found or not readable\n";
    exit(1);
}

// 2. Must be valid JSON.
$raw = file_get_contents(BLUEPRINT_PATH);
$bp  = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'validate_blueprint: FAIL — invalid JSON: ' . json_last_error_msg() . "\n";
    exit(1);
}

// 3. Required top-level keys.
foreach (['$schema', 'preferredVersions', 'steps'] as $key) {
    if (!array_key_exists($key, $bp)) {
        $errors[] = "Missing required top-level key: $key";
    }
}

// 4. steps must be a non-empty array.
if (empty($bp['steps']) || !is_array($bp['steps'])) {
    $errors[] = 'steps must be a non-empty array';
}

// 5. Inspect the runPhpCode step(s).
$phpsteps = array_filter($bp['steps'] ?? [], fn($s) => ($s['step'] ?? '') === 'runPhpCode');

foreach ($phpsteps as $step) {
    $code = $step['code'] ?? '';

    // 5a. No echo/print-without-rethrow exception swallowing.
    // Matches catch blocks that only echo/print the error without rethrowing.
    if (preg_match('/catch\s*\([^)]*\)\s*\{[^}]*(?:echo|print)\s[^}]*\}/s', $code)) {
        $errors[] = 'runPhpCode contains a catch block that swallows the exception '
            . '(echo/print without rethrow) — remove the catch or rethrow';
    }

    // 5b. A studentname element must be present so the certificate shows the real user name.
    if (stripos($code, 'studentname') === false) {
        $errors[] = "runPhpCode does not include a 'studentname' element";
    }
}

// 6. No duplicate setLandingPage steps.
$landingsteps = array_filter($bp['steps'] ?? [], fn($s) => ($s['step'] ?? '') === 'setLandingPage');

if (count($landingsteps) > 1) {
    $errors[] = 'blueprint.json contains more than one setLandingPage step';
}

if (empty($errors)) {
    echo "validate_blueprint: OK\n";
    exit(0);
}

echo "validate_blueprint: FAIL\n";
foreach ($errors as $e) {
    echo "  - $e\n";
}
exit(1);

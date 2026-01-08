<?php

defined('MOODLE_INTERNAL') || die();

use mod_customcert\local\hook_callbacks;
use core\hook\di_configuration;

$callbacks = [
    [
        'hook' => di_configuration::class,
        'callback' => [hook_callbacks::class, 'di_configuration'],
    ],
];

<?php

namespace mod_customcert\export;

use core\notification;
use mod_customcert\export\contracts\i_backup_logger;

class recovering_logger implements i_backup_logger {
    private array $warnings = [];
    private array $infos = [];

    public function warning($message): void {
        if (CLI_SCRIPT) {
            mtrace($message);
            return;
        }

        $this->warnings[] = $message;
    }

    public function info($message): void {
        if (CLI_SCRIPT) {
            mtrace($message);
            return;
        }

        $this->infos[] = $message;
    }

    public function print_notification(): void {
        foreach ($this->warnings as $message) {
            notification::warning($message);
        }

        foreach ($this->infos as $info) {
            notification::info($info);
        }
    }
}

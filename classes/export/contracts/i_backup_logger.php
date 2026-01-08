<?php

namespace mod_customcert\export\contracts;

interface i_backup_logger {
    public function warning($message): void;
    public function info($message): void;
    public function print_notification(): void;
}

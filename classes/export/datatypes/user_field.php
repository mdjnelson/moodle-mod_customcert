<?php

namespace mod_customcert\export\datatypes;

use core\di;
use moodle_database;

class user_field implements i_field {
    private moodle_database $db;

    public function __construct() {
        $this->db = di::get(moodle_database::class);
    }

    public function import(array $data) {
        $userid = $data['userid'];
        $username = $data['fullname'];

        $user = $this->db->get_record('user', ['id' => $userid]);

        if (!$user) {
            throw new format_exception("User with $userid does not exist");
        }

        if (fullname($user) != $username) {
            throw new format_exception("User with $userid is not the same as in backup.");
        }

        return $userid;
    }

    public function export($value): array {
        if (empty($value)) {
            return [];
        }

        $userid = (int) $value;
        $user = $this->db->get_record('user', ['id' => $userid]);

        if (!$user) {
            return [];
        }

        return [
            'userid' => $userid,
            'fullname' => fullname($user),
        ];
    }
}

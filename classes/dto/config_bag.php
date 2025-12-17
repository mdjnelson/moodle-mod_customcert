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
 * A configuration class used to store and manage configuration settings for elements.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\dto;

/**
 * Lightweight immutable configuration container for element settings.
 *
 * Scaffolding only; provides typed accessors and JSON helpers.
 */
final class config_bag {
    /** @var array<string,mixed> */
    private array $data;

    /**
     * Constructor.
     *
     * @param array $data The raw config data.
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Create an empty config bag with default version.
     *
     * @return self
     */
    public static function empty(): self {
        return new self([]);
    }

    /**
     * Create a config bag from a PHP array.
     *
     * @param array $data Raw config data.
     * @return self
     */
    public static function from_array(array $data): self {
        return new self($data);
    }

    /**
     * Create a config bag from a JSON string.
     *
     * @param string $json JSON-encoded configuration.
     * @return self
     */
    public static function from_json(string $json): self {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        return new self($decoded);
    }

    /**
     * Export configuration as an array.
     *
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Export configuration as a JSON string.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode($this->data);
    }

    /**
     * Return a copy with the version updated.
     *
     * @param int $version New version number.
     * @return self
     */
    public function with_version(int $version): self {
        $copy = $this->data;
        $copy['_v'] = $version;

        return new self($copy);
    }

    /**
     * Check whether the config contains a given key.
     *
     * @param string $key The key to check.
     * @return bool
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get a raw value from the configuration.
     *
     * @param string $key Array key to retrieve.
     * @param mixed $default Default value when key does not exist.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get a string value from the configuration.
     *
     * @param string $key Config key.
     * @param string|null $default Default value.
     * @return string|null
     */
    public function get_string(string $key, ?string $default = null): ?string {
        $val = $this->data[$key] ?? $default;
        return $val === null ? null : (string)$val;
    }

    /**
     * Get an integer value from the configuration.
     *
     * @param string $key Config key.
     * @param int|null $default Default value.
     * @return int|null
     */
    public function get_int(string $key, ?int $default = null): ?int {
        $val = $this->data[$key] ?? $default;
        return $val === null ? null : (int)$val;
    }

    /**
     * Get a float value from the configuration.
     *
     * @param string $key Config key.
     * @param float|null $default Default value.
     * @return float|null
     */
    public function get_float(string $key, ?float $default = null): ?float {
        $val = $this->data[$key] ?? $default;
        return $val === null ? null : (float)$val;
    }

    /**
     * Get a boolean value from the configuration.
     *
     * @param string $key Config key.
     * @param bool|null $default Default value.
     * @return bool|null
     */
    public function get_bool(string $key, ?bool $default = null): ?bool {
        $val = $this->data[$key] ?? $default;
        return $val === null ? null : (bool)$val;
    }

    /**
     * Return a new bag with a modified key/value pair.
     *
     * @param string $key Config key to set.
     * @param mixed $value Value to assign.
     * @return self
     */
    public function with(string $key, mixed $value): self {
        $copy = $this->data;
        $copy[$key] = $value;

        return new self($copy);
    }
}

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
 * Typed payload for the digitalsignature element.
 *
 * @package    customcertelement_digitalsignature
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_digitalsignature;

use mod_customcert\element\element_payload_interface;

/**
 * Typed payload for the digitalsignature element.
 *
 * File metadata fields are nullable; they are absent when no certificate file has been
 * selected. to_array() omits null file metadata to preserve the existing JSON structure.
 *
 * @package    customcertelement_digitalsignature
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class digitalsignature_payload implements element_payload_interface {
    /**
     * Construct a digitalsignature_payload.
     *
     * @param string $signaturename Visible name of the signer.
     * @param string $signaturepassword Password protecting the signature certificate.
     * @param string $signaturelocation Location of the signer.
     * @param string $signaturereason Reason for signing.
     * @param string $signaturecontactinfo Contact information for the signer.
     * @param int $width Width in mm.
     * @param int $height Height in mm.
     * @param int|null $contextid Image file context ID, or null if no image selected.
     * @param string|null $filearea Image file area, or null if no image selected.
     * @param int|null $itemid Image file item ID, or null if no image selected.
     * @param string|null $filepath Image file path, or null if no image selected.
     * @param string|null $filename Image file name, or null if no image selected.
     * @param int|null $signaturecontextid Signature file context ID, or null if no signature file selected.
     * @param string|null $signaturefilearea Signature file area, or null if no signature file selected.
     * @param int|null $signatureitemid Signature file item ID, or null if no signature file selected.
     * @param string|null $signaturefilepath Signature file path, or null if no signature file selected.
     * @param string|null $signaturefilename Signature file name, or null if no signature file selected.
     */
    public function __construct(
        /** @var string Visible name of the signer. */
        public readonly string $signaturename,
        /** @var string Password protecting the signature certificate. */
        public readonly string $signaturepassword,
        /** @var string Location of the signer. */
        public readonly string $signaturelocation,
        /** @var string Reason for signing. */
        public readonly string $signaturereason,
        /** @var string Contact information for the signer. */
        public readonly string $signaturecontactinfo,
        /** @var int Width in mm. */
        public readonly int $width,
        /** @var int Height in mm. */
        public readonly int $height,
        /** @var int|null Image file context ID, or null if no image selected. */
        public readonly ?int $contextid,
        /** @var string|null Image file area, or null if no image selected. */
        public readonly ?string $filearea,
        /** @var int|null Image file item ID, or null if no image selected. */
        public readonly ?int $itemid,
        /** @var string|null Image file path, or null if no image selected. */
        public readonly ?string $filepath,
        /** @var string|null Image file name, or null if no image selected. */
        public readonly ?string $filename,
        /** @var int|null Signature file context ID, or null if no signature file selected. */
        public readonly ?int $signaturecontextid,
        /** @var string|null Signature file area, or null if no signature file selected. */
        public readonly ?string $signaturefilearea,
        /** @var int|null Signature file item ID, or null if no signature file selected. */
        public readonly ?int $signatureitemid,
        /** @var string|null Signature file path, or null if no signature file selected. */
        public readonly ?string $signaturefilepath,
        /** @var string|null Signature file name, or null if no signature file selected. */
        public readonly ?string $signaturefilename,
    ) {
    }

    /**
     * Construct a digitalsignature_payload from a decoded data array.
     *
     * @param array $data Associative array, typically from json_decode($raw, true).
     * @return static
     */
    public static function from_array(array $data): static {
        $hasfile = isset(
            $data['contextid'],
            $data['filearea'],
            $data['itemid'],
            $data['filepath'],
            $data['filename']
        );
        $hassigfile = isset(
            $data['signaturecontextid'],
            $data['signaturefilearea'],
            $data['signatureitemid'],
            $data['signaturefilepath'],
            $data['signaturefilename']
        );
        return new static(
            signaturename: (string)($data['signaturename'] ?? ''),
            signaturepassword: (string)($data['signaturepassword'] ?? ''),
            signaturelocation: (string)($data['signaturelocation'] ?? ''),
            signaturereason: (string)($data['signaturereason'] ?? ''),
            signaturecontactinfo: (string)($data['signaturecontactinfo'] ?? ''),
            width: (int)($data['width'] ?? 0),
            height: (int)($data['height'] ?? 0),
            contextid: $hasfile ? (int)$data['contextid'] : null,
            filearea: $hasfile ? (string)$data['filearea'] : null,
            itemid: $hasfile ? (int)$data['itemid'] : null,
            filepath: $hasfile ? (string)$data['filepath'] : null,
            filename: $hasfile ? (string)$data['filename'] : null,
            signaturecontextid: $hassigfile ? (int)$data['signaturecontextid'] : null,
            signaturefilearea: $hassigfile ? (string)$data['signaturefilearea'] : null,
            signatureitemid: $hassigfile ? (int)$data['signatureitemid'] : null,
            signaturefilepath: $hassigfile ? (string)$data['signaturefilepath'] : null,
            signaturefilename: $hassigfile ? (string)$data['signaturefilename'] : null,
        );
    }

    /**
     * Serialize the payload to an associative array suitable for json_encode().
     *
     * Null file metadata keys are omitted to preserve the existing JSON structure.
     *
     * @return array
     */
    public function to_array(): array {
        $data = [
            'signaturename' => $this->signaturename,
            'signaturepassword' => $this->signaturepassword,
            'signaturelocation' => $this->signaturelocation,
            'signaturereason' => $this->signaturereason,
            'signaturecontactinfo' => $this->signaturecontactinfo,
            'width' => $this->width,
            'height' => $this->height,
        ];
        if ($this->contextid !== null) {
            $data['contextid'] = $this->contextid;
            $data['filearea'] = $this->filearea;
            $data['itemid'] = $this->itemid;
            $data['filepath'] = $this->filepath;
            $data['filename'] = $this->filename;
        }
        if ($this->signaturecontextid !== null) {
            $data['signaturecontextid'] = $this->signaturecontextid;
            $data['signaturefilearea'] = $this->signaturefilearea;
            $data['signatureitemid'] = $this->signatureitemid;
            $data['signaturefilepath'] = $this->signaturefilepath;
            $data['signaturefilename'] = $this->signaturefilename;
        }
        return $data;
    }

    /**
     * Enforce the all-or-none file metadata invariant for both file groups.
     *
     * Either all five image file metadata fields must be set, or none of them.
     * The same applies to the five signature file metadata fields independently.
     *
     * @return void
     * @throws \coding_exception If only some fields within a file group are present.
     */
    public function validate(): void {
        $imagefields = [$this->contextid, $this->filearea, $this->itemid, $this->filepath, $this->filename];
        $imageset = array_filter($imagefields, fn($v) => $v !== null);
        if (count($imageset) > 0 && count($imageset) < 5) {
            throw new \coding_exception('digitalsignature_payload: image file metadata fields must all be set or all be null.');
        }
        $sigfields = [
            $this->signaturecontextid,
            $this->signaturefilearea,
            $this->signatureitemid,
            $this->signaturefilepath,
            $this->signaturefilename,
        ];
        $sigset = array_filter($sigfields, fn($v) => $v !== null);
        if (count($sigset) > 0 && count($sigset) < 5) {
            throw new \coding_exception('digitalsignature_payload: signature file metadata fields must all be set or all be null.');
        }
    }
}

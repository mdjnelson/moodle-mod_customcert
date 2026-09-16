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
 * Position and refpoint math helpers for the certificate rearranger.
 *
 * Pure functions ported from the legacy YUI/AMD rearranger so drag, keyboard
 * nudging, and save/load round-trips stay consistent with existing certificates.
 *
 * @module     mod_customcert/position
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    ALIGN_CENTER,
    ALIGN_LEFT,
    ALIGN_RIGHT,
    PIXELSINMM,
    REF_POINT_TOPCENTER,
    REF_POINT_TOPLEFT,
    REF_POINT_TOPRIGHT,
} from './constants';
import type {CertificatePage} from './types';

/** Pixel rectangle relative to the PDF page origin (top-left). */
export type PixelRect = {
    left: number;
    top: number;
    width: number;
    height: number;
};

/**
 * Convert millimetres to CSS pixels.
 *
 * @param mm Distance in millimetres.
 * @returns Distance in pixels.
 */
export function mmToPx(mm: number): number {
    return mm * PIXELSINMM;
}

/**
 * Convert CSS pixels to millimetres, rounded to the nearest integer.
 *
 * @param px Distance in pixels.
 * @returns Distance in millimetres.
 */
export function pxToMm(px: number): number {
    return Math.round(px / PIXELSINMM);
}

/**
 * CSS class encoding the element's reference point.
 *
 * @param refpoint Reference-point constant.
 * @returns Class name used by styles.css.
 */
export function refpointClassName(refpoint: number): string {
    switch (refpoint) {
        case REF_POINT_TOPCENTER:
            return 'refpoint-center';
        case REF_POINT_TOPRIGHT:
            return 'refpoint-right';
        case REF_POINT_TOPLEFT:
        default:
            return 'refpoint-left';
    }
}

/**
 * CSS class encoding the element's text alignment.
 *
 * @param alignment Alignment letter L/C/R.
 * @returns Class name used by styles.css.
 */
export function alignmentClassName(alignment: string): string {
    switch (alignment) {
        case ALIGN_CENTER:
            return 'align-center';
        case ALIGN_RIGHT:
            return 'align-right';
        case ALIGN_LEFT:
        default:
            return 'align-left';
    }
}

/**
 * Effective rendered width in pixels, capped by the optional max width in mm.
 *
 * @param measuredWidthPx Measured DOM width in pixels.
 * @param maxWidthMm Optional max width in millimetres.
 * @returns Width to use for refpoint offset calculations.
 */
export function effectiveNodeWidthPx(measuredWidthPx: number, maxWidthMm: number | null | undefined): number {
    const maxWidthPx = maxWidthMm ? mmToPx(maxWidthMm) : 0;
    if (maxWidthPx && measuredWidthPx > maxWidthPx) {
        return maxWidthPx;
    }
    return measuredWidthPx;
}

/**
 * Convert a stored mm position (at the refpoint) into a left offset in pixels
 * from the PDF origin for absolutely positioning the element box.
 *
 * Mirrors the legacy YUI setpositions()/rearrange-area _setPosition() logic.
 *
 * @param posxMm Stored X position in mm (at the refpoint).
 * @param refpoint Reference-point constant.
 * @param nodeWidthPx Element width in pixels (already effective/capped).
 * @returns Left offset in pixels relative to the PDF origin.
 */
export function leftPxFromPosxMm(posxMm: number, refpoint: number, nodeWidthPx: number): number {
    let posx = mmToPx(posxMm);

    switch (refpoint) {
        case REF_POINT_TOPCENTER:
            posx -= nodeWidthPx / 2;
            break;
        case REF_POINT_TOPRIGHT:
            // Legacy code subtracted nodewidth then added 2px for the right refpoint.
            posx = posx - nodeWidthPx + 2;
            break;
        default:
            break;
    }

    return posx;
}

/**
 * Convert a left pixel offset (PDF-relative) back into a stored mm X position
 * at the element's refpoint.
 *
 * Mirrors the legacy YUI savepositions()/rearrange-area _setPositionInForm() logic.
 *
 * @param leftPx Left offset in pixels relative to the PDF origin.
 * @param refpoint Reference-point constant.
 * @param nodeWidthPx Element width in pixels.
 * @returns Stored X position in millimetres.
 */
export function posxMmFromLeftPx(leftPx: number, refpoint: number, nodeWidthPx: number): number {
    let posx = leftPx;

    switch (refpoint) {
        case REF_POINT_TOPCENTER:
            posx += nodeWidthPx / 2;
            break;
        case REF_POINT_TOPRIGHT:
            posx += nodeWidthPx;
            break;
        default:
            break;
    }

    return pxToMm(posx);
}

/**
 * Convert a stored mm Y position into a top offset in pixels from the PDF origin.
 *
 * @param posyMm Stored Y position in millimetres.
 * @returns Top offset in pixels relative to the PDF origin.
 */
export function topPxFromPosyMm(posyMm: number): number {
    return mmToPx(posyMm);
}

/**
 * Convert a top pixel offset (PDF-relative) back into a stored mm Y position.
 *
 * @param topPx Top offset in pixels relative to the PDF origin.
 * @returns Stored Y position in millimetres.
 */
export function posyMmFromTopPx(topPx: number): number {
    return pxToMm(topPx);
}

/**
 * PDF page boundaries in pixels relative to the PDF origin (0,0 top-left).
 *
 * @param page Page geometry in millimetres.
 * @returns Left/right/top/bottom edges in pixels.
 */
export function pageBoundariesPx(page: CertificatePage): {
    left: number;
    right: number;
    top: number;
    bottom: number;
} {
    const widthPx = mmToPx(page.width);
    const heightPx = mmToPx(page.height);
    const left = mmToPx(page.leftmargin || 0);
    const right = widthPx - mmToPx(page.rightmargin || 0);

    return {
        left,
        right,
        top: 0,
        bottom: heightPx,
    };
}

/**
 * Whether any part of the element rectangle sits outside the usable PDF area.
 *
 * @param rect Element rectangle relative to the PDF origin.
 * @param page Page geometry in millimetres.
 * @returns True when the element is out of bounds.
 */
export function isOutOfBounds(rect: PixelRect, page: CertificatePage): boolean {
    const bounds = pageBoundariesPx(page);
    const right = rect.left + rect.width;
    const bottom = rect.top + rect.height;

    if (rect.left < bounds.left || right > bounds.right) {
        return true;
    }
    if (rect.top < bounds.top || bottom > bounds.bottom) {
        return true;
    }
    return false;
}

/**
 * Clamp a proposed element rectangle so it stays fully inside the usable PDF area.
 *
 * @param rect Proposed rectangle relative to the PDF origin.
 * @param page Page geometry in millimetres.
 * @returns Clamped rectangle.
 */
export function clampRectToPage(rect: PixelRect, page: CertificatePage): PixelRect {
    const bounds = pageBoundariesPx(page);
    let {left, top, width, height} = rect;

    if (left < bounds.left) {
        left = bounds.left;
    }
    if (left + width > bounds.right) {
        left = Math.max(bounds.left, bounds.right - width);
    }
    if (top < bounds.top) {
        top = bounds.top;
    }
    if (top + height > bounds.bottom) {
        top = Math.max(bounds.top, bounds.bottom - height);
    }

    return {left, top, width, height};
}

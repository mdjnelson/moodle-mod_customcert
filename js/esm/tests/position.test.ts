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
 * Tests for certificate rearranger position helpers.
 *
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
} from '../src/constants';
import {
    alignmentClassName,
    clampRectToPage,
    effectiveNodeWidthPx,
    isOutOfBounds,
    leftPxFromPosxMm,
    mmToPx,
    pageBoundariesPx,
    posxMmFromLeftPx,
    posyMmFromTopPx,
    pxToMm,
    refpointClassName,
    topPxFromPosyMm,
} from '../src/position';
import type {CertificatePage} from '../src/types';

const page: CertificatePage = {
    width: 210,
    height: 297,
    leftmargin: 10,
    rightmargin: 15,
};

describe('position helpers', () => {
    describe('unit conversion', () => {
        it('converts millimetres to pixels using the legacy constant', () => {
            expect(mmToPx(1)).toBeCloseTo(PIXELSINMM);
            expect(mmToPx(10)).toBeCloseTo(10 * PIXELSINMM);
        });

        it('converts pixels to rounded millimetres', () => {
            expect(pxToMm(PIXELSINMM)).toBe(1);
            expect(pxToMm(PIXELSINMM * 10.4)).toBe(10);
            expect(pxToMm(PIXELSINMM * 10.5)).toBe(11);
        });
    });

    describe('class name helpers', () => {
        it('maps refpoints to CSS classes', () => {
            expect(refpointClassName(REF_POINT_TOPLEFT)).toBe('refpoint-left');
            expect(refpointClassName(REF_POINT_TOPCENTER)).toBe('refpoint-center');
            expect(refpointClassName(REF_POINT_TOPRIGHT)).toBe('refpoint-right');
            expect(refpointClassName(99)).toBe('refpoint-left');
        });

        it('maps alignments to CSS classes', () => {
            expect(alignmentClassName(ALIGN_LEFT)).toBe('align-left');
            expect(alignmentClassName(ALIGN_CENTER)).toBe('align-center');
            expect(alignmentClassName(ALIGN_RIGHT)).toBe('align-right');
            expect(alignmentClassName('X')).toBe('align-left');
        });
    });

    describe('refpoint offsets', () => {
        it('keeps left refpoint at the stored x position', () => {
            const left = leftPxFromPosxMm(50, REF_POINT_TOPLEFT, 100);
            expect(left).toBeCloseTo(mmToPx(50));
        });

        it('offsets center refpoint by half the node width', () => {
            const left = leftPxFromPosxMm(50, REF_POINT_TOPCENTER, 100);
            expect(left).toBeCloseTo(mmToPx(50) - 50);
        });

        it('offsets right refpoint by the node width minus 2px', () => {
            const left = leftPxFromPosxMm(50, REF_POINT_TOPRIGHT, 100);
            expect(left).toBeCloseTo(mmToPx(50) - 100 + 2);
        });

        it('round-trips left refpoint positions through mm and px', () => {
            const nodeWidth = 80;
            const originalMm = 42;
            const left = leftPxFromPosxMm(originalMm, REF_POINT_TOPLEFT, nodeWidth);
            expect(posxMmFromLeftPx(left, REF_POINT_TOPLEFT, nodeWidth)).toBe(originalMm);
        });

        it('round-trips center refpoint positions through mm and px', () => {
            const nodeWidth = 80;
            const originalMm = 42;
            const left = leftPxFromPosxMm(originalMm, REF_POINT_TOPCENTER, nodeWidth);
            expect(posxMmFromLeftPx(left, REF_POINT_TOPCENTER, nodeWidth)).toBe(originalMm);
        });

        it('converts y positions without refpoint adjustment', () => {
            expect(topPxFromPosyMm(20)).toBeCloseTo(mmToPx(20));
            expect(posyMmFromTopPx(mmToPx(20))).toBe(20);
        });
    });

    describe('effective width', () => {
        it('returns the measured width when no max width is set', () => {
            expect(effectiveNodeWidthPx(120, null)).toBe(120);
            expect(effectiveNodeWidthPx(120, undefined)).toBe(120);
            expect(effectiveNodeWidthPx(120, 0)).toBe(120);
        });

        it('caps the measured width when it exceeds the max width in mm', () => {
            const maxMm = 10;
            expect(effectiveNodeWidthPx(mmToPx(20), maxMm)).toBeCloseTo(mmToPx(maxMm));
            expect(effectiveNodeWidthPx(mmToPx(5), maxMm)).toBeCloseTo(mmToPx(5));
        });
    });

    describe('page bounds', () => {
        it('computes usable boundaries including margins', () => {
            const bounds = pageBoundariesPx(page);
            expect(bounds.left).toBeCloseTo(mmToPx(10));
            expect(bounds.right).toBeCloseTo(mmToPx(210) - mmToPx(15));
            expect(bounds.top).toBe(0);
            expect(bounds.bottom).toBeCloseTo(mmToPx(297));
        });

        it('detects elements outside the usable area', () => {
            expect(isOutOfBounds({left: 0, top: 0, width: 10, height: 10}, page)).toBe(true);
            expect(isOutOfBounds({
                left: mmToPx(10),
                top: 0,
                width: 10,
                height: 10,
            }, page)).toBe(false);
            expect(isOutOfBounds({
                left: mmToPx(10),
                top: -1,
                width: 10,
                height: 10,
            }, page)).toBe(true);
        });

        it('clamps rectangles into the usable area', () => {
            const clamped = clampRectToPage({left: 0, top: -20, width: 50, height: 40}, page);
            expect(clamped.left).toBeCloseTo(mmToPx(10));
            expect(clamped.top).toBe(0);
            expect(clamped.width).toBe(50);
            expect(clamped.height).toBe(40);
        });
    });
});

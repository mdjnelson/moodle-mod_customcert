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
 * Tests for pointer-driven click-vs-drag behaviour on the certificate element.
 *
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, render, screen} from '@testing-library/react';
import CertificateElement from '../src/CertificateElement';
import type {CertificateElement as CertificateElementData, CertificatePage} from '../src/types';

const page: CertificatePage = {
    width: 210,
    height: 297,
    leftmargin: 0,
    rightmargin: 0,
};

const element: CertificateElementData = {
    id: 21,
    name: 'Course name',
    posx: 20,
    posy: 30,
    width: null,
    refpoint: 0,
    alignment: 'L',
    html: '<span class="preview">Course name</span>',
};

/**
 * Dispatches a pointerdown/(optional move)/pointerup sequence on a node, mirroring a real
 * mouse interaction rather than a synthetic fireEvent.click().
 *
 * @param node Target DOM node.
 * @param options Coordinates for the down/up events and an optional intermediate move.
 */
function firePointerSequence(node: Element, options: {
    downX: number;
    downY: number;
    moveX?: number;
    moveY?: number;
    upX: number;
    upY: number;
    cancel?: boolean;
}) {
    const pointerId = 1;
    act(() => {
        node.dispatchEvent(new MouseEvent('pointerdown', {
            bubbles: true,
            cancelable: true,
            clientX: options.downX,
            clientY: options.downY,
            // @ts-expect-error jsdom's MouseEvent does not type pointerId.
            pointerId,
            button: 0,
        }));
        if (options.moveX !== undefined && options.moveY !== undefined) {
            node.dispatchEvent(new MouseEvent('pointermove', {
                bubbles: true,
                cancelable: true,
                clientX: options.moveX,
                clientY: options.moveY,
                // @ts-expect-error jsdom's MouseEvent does not type pointerId.
                pointerId,
            }));
        }
        node.dispatchEvent(new MouseEvent(options.cancel ? 'pointercancel' : 'pointerup', {
            bubbles: true,
            cancelable: true,
            clientX: options.upX,
            clientY: options.upY,
            // @ts-expect-error jsdom's MouseEvent does not type pointerId.
            pointerId,
            button: 0,
        }));
        if (!options.cancel) {
            // Real browsers always fire a click after a pointerdown/pointerup pair on the same
            // target; it is up to the component to ignore it when a drag actually occurred.
            node.dispatchEvent(new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                clientX: options.upX,
                clientY: options.upY,
                button: 0,
            }));
        }
    });
}

describe('CertificateElement pointer interaction', () => {
    beforeEach(() => {
        mockString('draggableelement', 'mod_customcert', 'draggable element');
        mockString('elementdefaultname', 'mod_customcert', 'Element 21');

        // Jsdom does not implement pointer capture; provide harmless no-op stubs.
        HTMLElement.prototype.setPointerCapture = jest.fn();
        HTMLElement.prototype.releasePointerCapture = jest.fn();
        HTMLElement.prototype.hasPointerCapture = jest.fn(() => true);
        jest.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
            left: 0,
            top: 0,
            right: 100,
            bottom: 20,
            width: 100,
            height: 20,
            x: 0,
            y: 0,
            toJSON: () => ({}),
        });
    });

    it('opens the editor on a stationary pointer down/up', () => {
        const onEdit = jest.fn();
        const onPositionChange = jest.fn();
        render(
            <CertificateElement
                element={element}
                page={page}
                onPositionChange={onPositionChange}
                onEdit={onEdit}
            />,
        );
        const node = screen.getByRole('button', {name: 'Course name'});

        firePointerSequence(node, {downX: 50, downY: 10, upX: 50, upY: 10});

        expect(onEdit).toHaveBeenCalledWith(21);
        expect(onPositionChange).not.toHaveBeenCalled();
    });

    it('still opens the editor when the pointer moves slightly during a click', () => {
        const onEdit = jest.fn();
        const onPositionChange = jest.fn();
        render(
            <CertificateElement
                element={element}
                page={page}
                onPositionChange={onPositionChange}
                onEdit={onEdit}
            />,
        );
        const node = screen.getByRole('button', {name: 'Course name'});

        // Small incidental movement (well under the drag threshold) during a real click.
        firePointerSequence(node, {downX: 50, downY: 10, moveX: 52, moveY: 11, upX: 52, upY: 11});

        expect(onEdit).toHaveBeenCalledWith(21);
        expect(onPositionChange).not.toHaveBeenCalled();
    });

    it('treats a deliberate large pointer movement as a drag, not a click', () => {
        const onEdit = jest.fn();
        const onPositionChange = jest.fn();
        render(
            <CertificateElement
                element={element}
                page={page}
                onPositionChange={onPositionChange}
                onEdit={onEdit}
            />,
        );
        const node = screen.getByRole('button', {name: 'Course name'});

        firePointerSequence(node, {downX: 50, downY: 10, moveX: 90, moveY: 40, upX: 90, upY: 40});

        expect(onEdit).not.toHaveBeenCalled();
        expect(onPositionChange).toHaveBeenCalledWith(21, expect.any(Number), expect.any(Number));
    });

    it('commits a drag but does not open the editor when movement is only observed at pointerup', () => {
        const onEdit = jest.fn();
        const onPositionChange = jest.fn();
        render(
            <CertificateElement
                element={element}
                page={page}
                onPositionChange={onPositionChange}
                onEdit={onEdit}
            />,
        );
        const node = screen.getByRole('button', {name: 'Course name'});

        // No intermediate pointermove is dispatched; the movement beyond the drag threshold
        // is only visible from the pointerdown/pointerup coordinates.
        firePointerSequence(node, {downX: 50, downY: 10, upX: 90, upY: 40});

        expect(onPositionChange).toHaveBeenCalledWith(21, expect.any(Number), expect.any(Number));
        expect(onEdit).not.toHaveBeenCalled();
    });

    it('does not commit a position or open the editor when the pointer gesture is cancelled', () => {
        const onEdit = jest.fn();
        const onPositionChange = jest.fn();
        render(
            <CertificateElement
                element={element}
                page={page}
                onPositionChange={onPositionChange}
                onEdit={onEdit}
            />,
        );
        const node = screen.getByRole('button', {name: 'Course name'});

        firePointerSequence(node, {
            downX: 50,
            downY: 10,
            moveX: 90,
            moveY: 40,
            upX: 90,
            upY: 40,
            cancel: true,
        });

        expect(onEdit).not.toHaveBeenCalled();
        expect(onPositionChange).not.toHaveBeenCalled();
    });
});

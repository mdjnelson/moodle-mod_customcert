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
 * Draggable / keyboard-operable certificate element preview.
 *
 * @module     mod_customcert/CertificateElement
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {getString} from '@moodle/lms/core/stringUtils';
import {
    DRAG_THRESHOLD_PX,
    KEYBOARD_NUDGE_MM,
    KEYBOARD_NUDGE_MM_LARGE,
} from './constants';
import {
    alignmentClassName,
    clampRectToPage,
    effectiveNodeWidthPx,
    isOutOfBounds,
    leftPxFromPosxMm,
    mmToPx,
    posxMmFromLeftPx,
    posyMmFromTopPx,
    refpointClassName,
    topPxFromPosyMm,
} from './position';
import type {CertificateElement as CertificateElementData, CertificatePage} from './types';

export type CertificateElementProps = {
    /** Element data including server-rendered preview HTML. */
    element: CertificateElementData;
    /** Page geometry used for bounds checks. */
    page: CertificatePage;
    /** Called when the user finishes a drag or keyboard move. */
    onPositionChange: (id: number, posx: number, posy: number) => void;
    /** Called when the user activates the element for editing. */
    onEdit: (id: number) => void;
};

type DragState = {
    pointerId: number;
    startClientX: number;
    startClientY: number;
    originLeft: number;
    originTop: number;
    width: number;
    height: number;
};

/**
 * One certificate element on the rearranger canvas.
 *
 * @param props Component props.
 * @returns Element node.
 */
export default function CertificateElement({
    element,
    page,
    onPositionChange,
    onEdit,
}: CertificateElementProps) {
    const nodeRef = useRef<HTMLDivElement | null>(null);
    const dragRef = useRef<DragState | null>(null);
    const skipClickRef = useRef(false);
    const [nodeSize, setNodeSize] = useState({width: 0, height: 0});
    const [dragOffset, setDragOffset] = useState<{left: number; top: number} | null>(null);
    const [draggableLabel, setDraggableLabel] = useState('draggable element');
    const [defaultName, setDefaultName] = useState(`Element ${element.id}`);

    useEffect(() => {
        let cancelled = false;
        getString('draggableelement', 'mod_customcert').then((value) => {
            if (!cancelled) {
                setDraggableLabel(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        let cancelled = false;
        getString('elementdefaultname', 'mod_customcert', element.id).then((value) => {
            if (!cancelled) {
                setDefaultName(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        return () => {
            cancelled = true;
        };
    }, [element.id]);

    const measure = useCallback(() => {
        const node = nodeRef.current;
        if (!node) {
            return;
        }
        const rect = node.getBoundingClientRect();
        setNodeSize({width: rect.width, height: rect.height});
    }, []);

    useLayoutEffect(() => {
        measure();
    }, [measure, element.html, element.width, element.refpoint]);

    useEffect(() => {
        const node = nodeRef.current;
        if (!node || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver(() => measure());
        observer.observe(node);
        return () => observer.disconnect();
    }, [measure]);

    const widthPx = effectiveNodeWidthPx(nodeSize.width, element.width);
    const baseLeft = leftPxFromPosxMm(element.posx, element.refpoint, widthPx || nodeSize.width);
    const baseTop = topPxFromPosyMm(element.posy);
    const left = dragOffset ? dragOffset.left : baseLeft;
    const top = dragOffset ? dragOffset.top : baseTop;

    const commitPosition = useCallback((nextLeft: number, nextTop: number, width: number, height: number) => {
        const rect = {left: nextLeft, top: nextTop, width, height};
        if (isOutOfBounds(rect, page)) {
            // Match legacy behaviour: reject the move and keep the previous position.
            setDragOffset(null);
            return;
        }
        const posx = posxMmFromLeftPx(nextLeft, element.refpoint, width);
        const posy = posyMmFromTopPx(nextTop);
        setDragOffset(null);
        onPositionChange(element.id, posx, posy);
    }, [element.id, element.refpoint, onPositionChange, page]);

    const handlePointerDown = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }
        const node = nodeRef.current;
        if (!node) {
            return;
        }
        event.preventDefault();
        node.setPointerCapture(event.pointerId);
        const rect = node.getBoundingClientRect();
        dragRef.current = {
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            originLeft: left,
            originTop: top,
            width: rect.width,
            height: rect.height,
        };
        skipClickRef.current = false;
        setDragOffset({left, top});
    }, [left, top]);

    const handlePointerMove = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }
        const deltaX = event.clientX - drag.startClientX;
        const deltaY = event.clientY - drag.startClientY;
        if (Math.abs(deltaX) > DRAG_THRESHOLD_PX || Math.abs(deltaY) > DRAG_THRESHOLD_PX) {
            skipClickRef.current = true;
        }
        setDragOffset({
            left: drag.originLeft + deltaX,
            top: drag.originTop + deltaY,
        });
    }, []);

    const handlePointerUp = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }
        dragRef.current = null;
        const node = nodeRef.current;
        if (node?.hasPointerCapture(event.pointerId)) {
            node.releasePointerCapture(event.pointerId);
        }
        const deltaX = event.clientX - drag.startClientX;
        const deltaY = event.clientY - drag.startClientY;
        const nextLeft = drag.originLeft + deltaX;
        const nextTop = drag.originTop + deltaY;
        if (Math.abs(deltaX) > DRAG_THRESHOLD_PX || Math.abs(deltaY) > DRAG_THRESHOLD_PX) {
            // Movement past the threshold may only be observed here (e.g. no intermediate
            // pointermove was processed before pointerup); ensure the subsequent click is
            // still ignored so a completed drag never also opens the editor.
            skipClickRef.current = true;
            commitPosition(nextLeft, nextTop, drag.width, drag.height);
        } else {
            setDragOffset(null);
        }
    }, [commitPosition]);

    /**
     * A cancelled pointer gesture (e.g. the browser takes over for a system gesture) must
     * never be treated as a completed drag: release capture, clear drag state and the
     * temporary offset, and do not commit any position.
     */
    const handlePointerCancel = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }
        dragRef.current = null;
        skipClickRef.current = false;
        const node = nodeRef.current;
        if (node?.hasPointerCapture(event.pointerId)) {
            node.releasePointerCapture(event.pointerId);
        }
        setDragOffset(null);
    }, []);

    const handleClick = useCallback(() => {
        if (skipClickRef.current) {
            skipClickRef.current = false;
            return;
        }
        onEdit(element.id);
    }, [element.id, onEdit]);

    const handleKeyDown = useCallback((event: ReactKeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onEdit(element.id);
            return;
        }

        const nudgeMm = event.shiftKey ? KEYBOARD_NUDGE_MM_LARGE : KEYBOARD_NUDGE_MM;
        const nudgePx = mmToPx(nudgeMm);
        let nextLeft = left;
        let nextTop = top;
        let handled = false;

        switch (event.key) {
            case 'ArrowLeft':
                nextLeft -= nudgePx;
                handled = true;
                break;
            case 'ArrowRight':
                nextLeft += nudgePx;
                handled = true;
                break;
            case 'ArrowUp':
                nextTop -= nudgePx;
                handled = true;
                break;
            case 'ArrowDown':
                nextTop += nudgePx;
                handled = true;
                break;
            default:
                break;
        }

        if (!handled) {
            return;
        }

        event.preventDefault();
        const width = nodeSize.width || widthPx;
        const height = nodeSize.height || 1;
        const clamped = clampRectToPage({left: nextLeft, top: nextTop, width, height}, page);
        const effectiveWidth = effectiveNodeWidthPx(width, element.width);
        const posx = posxMmFromLeftPx(clamped.left, element.refpoint, effectiveWidth);
        const posy = posyMmFromTopPx(clamped.top);
        onPositionChange(element.id, posx, posy);
    }, [
        element.id,
        element.refpoint,
        element.width,
        left,
        nodeSize.height,
        nodeSize.width,
        onEdit,
        onPositionChange,
        page,
        top,
        widthPx,
    ]);

    const className = [
        'element',
        refpointClassName(element.refpoint),
        alignmentClassName(element.alignment),
        dragOffset ? 'is-dragging' : '',
    ].filter(Boolean).join(' ');

    const label = element.name
        ? `${element.name}`
        : defaultName;

    return (
        <div
            ref={nodeRef}
            id={`element-${element.id}`}
            className={className}
            data-refpoint={element.refpoint}
            role="button"
            tabIndex={0}
            aria-label={label}
            aria-roledescription={draggableLabel}
            aria-grabbed={dragOffset ? true : undefined}
            style={{
                left,
                top,
                position: 'absolute',
                maxWidth: element.width ? `${element.width}mm` : undefined,
                touchAction: 'none',
                cursor: dragOffset ? 'grabbing' : 'move',
            }}
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerUp}
            onPointerCancel={handlePointerCancel}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
            // The server-rendered preview HTML from each element plugin's render_html() stays
            // opaque here and is purely visual; it is kept as a direct child of this wrapper (as
            // it was before this fix) so third-party CSS relying on that DOM relationship
            // (e.g. ".element > img") keeps working. Pointer interaction on descendant preview
            // content (e.g. an <img>, which browsers make natively draggable) is instead disabled
            // via the "pointer-events: none" CSS rule on ".element *".
            dangerouslySetInnerHTML={{__html: element.html}}
        />
    );
}

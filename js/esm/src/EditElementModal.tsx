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
 * Accessible edit dialogue for a certificate element.
 *
 * Loads the server-rendered editelement fragment and keeps the frontend
 * agnostic of concrete customcertelement_* types.
 *
 * @module     mod_customcert/EditElementModal
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type MouseEvent as ReactMouseEvent,
} from 'react';
import {getString} from '@moodle/lms/core/stringUtils';
import {
    loadEditElementFragment,
    notifyException,
    replaceNodeContents,
    resetFormDirtyStates,
    saveElement,
    serializeForm,
} from './repository';
import type {CertificateElement} from './types';

export type EditElementModalProps = {
    /** Template id being edited. */
    templateid: number;
    /** Context id used for fragment loading. */
    contextid: number;
    /** Element currently being edited. */
    element: CertificateElement;
    /** Latest posx/posy in mm to seed into the form (after drag). */
    currentPosx: number;
    /** Latest posy in mm to seed into the form (after drag). */
    currentPosy: number;
    /** Called when the dialogue should close without applying changes. */
    onClose: () => void;
    /** Called after a successful save with updated element fields. */
    onSaved: (update: Partial<CertificateElement> & {id: number}) => void;
};

/**
 * Modal dialogue that hosts the PHP editelement form fragment.
 *
 * @param props Component props.
 * @returns Modal markup.
 */
export default function EditElementModal({
    templateid,
    contextid,
    element,
    currentPosx,
    currentPosy,
    onClose,
    onSaved,
}: EditElementModalProps) {
    const titleId = useId();
    const contentId = useId();
    const dialogRef = useRef<HTMLDivElement | null>(null);
    const contentRef = useRef<HTMLDivElement | null>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);
    const [title, setTitle] = useState('Edit element');
    const [closeLabel, setCloseLabel] = useState('Close');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    // Ref keeps close/cancel handlers (escape, backdrop, form cancel) in sync with the
    // latest saving state without re-subscribing document-level listeners on every change.
    const savingRef = useRef(false);

    useEffect(() => {
        savingRef.current = saving;
    }, [saving]);

    useEffect(() => {
        let cancelled = false;
        getString('editelement', 'mod_customcert').then((value) => {
            if (!cancelled) {
                setTitle(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback title.
        });
        getString('close', 'core').then((value) => {
            if (!cancelled) {
                setCloseLabel(value);
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
        previousFocusRef.current = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                if (!savingRef.current) {
                    onClose();
                }
            }
        };
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            previousFocusRef.current?.focus();
        };
    }, [onClose]);

    useEffect(() => {
        let cancelled = false;

        const load = async() => {
            setLoading(true);
            try {
                const fragment = await loadEditElementFragment(contextid, templateid, element.id);
                if (cancelled || !contentRef.current) {
                    return;
                }
                await replaceNodeContents(contentRef.current, fragment.html, fragment.javascript);

                const posxInput = contentRef.current.querySelector<HTMLInputElement>('#id_posx');
                const posyInput = contentRef.current.querySelector<HTMLInputElement>('#id_posy');
                if (posxInput) {
                    posxInput.value = String(currentPosx);
                }
                if (posyInput) {
                    posyInput.value = String(currentPosy);
                }

                setLoading(false);
                // Move focus into the dialogue once content is ready.
                window.setTimeout(() => {
                    const focusable = contentRef.current?.querySelector<HTMLElement>(
                        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
                    );
                    (focusable ?? dialogRef.current)?.focus();
                }, 0);
            } catch (error) {
                if (!cancelled) {
                    await notifyException(error);
                    onClose();
                }
            }
        };

        void load();
        return () => {
            cancelled = true;
        };
    }, [contextid, templateid, element.id, currentPosx, currentPosy, onClose]);

    const handleBackdropClick = useCallback((event: ReactMouseEvent<HTMLDivElement>) => {
        if (event.target === event.currentTarget && !savingRef.current) {
            onClose();
        }
    }, [onClose]);

    const handleDialogKeyDown = useCallback((event: ReactKeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'Tab' || !dialogRef.current) {
            return;
        }
        const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
        )).filter((node) => !node.hasAttribute('disabled') && node.tabIndex !== -1);

        if (focusable.length === 0) {
            event.preventDefault();
            dialogRef.current.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement as HTMLElement | null;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }, []);

    const handleContentClick = useCallback(async(event: ReactMouseEvent<HTMLDivElement>) => {
        const target = event.target as HTMLElement | null;
        if (!target) {
            return;
        }

        const cancelButton = target.closest('#id_cancel');
        if (cancelButton) {
            event.preventDefault();
            if (!savingRef.current) {
                onClose();
            }
            return;
        }

        const saveButton = target.closest('#id_savechanges');
        if (!saveButton || saving) {
            return;
        }

        event.preventDefault();
        const form = contentRef.current?.querySelector<HTMLFormElement>('#editelementform');
        if (!form) {
            return;
        }

        setSaving(true);
        try {
            const values = serializeForm(form);
            // Server returns generic layout properties (width/pos/refpoint/alignment/html)
            // so React state stays in sync without a page reload.
            const saved = await saveElement(templateid, element.id, values);
            // Only tell Moodle the form is no longer dirty once the save has actually
            // succeeded; a failed save must leave the dirty state intact.
            await resetFormDirtyStates();
            onSaved({
                id: saved.id,
                html: saved.html,
                width: saved.width,
                refpoint: saved.refpoint,
                posx: saved.posx,
                posy: saved.posy,
                alignment: saved.alignment,
            });
        } catch (error) {
            await notifyException(error);
            setSaving(false);
        }
    }, [element.id, onSaved, saving, templateid]);

    return (
        <div
            className="modal show d-block"
            tabIndex={-1}
            role="presentation"
            style={{backgroundColor: 'rgba(0, 0, 0, 0.5)'}}
            onClick={handleBackdropClick}
        >
            <div className="modal-dialog modal-dialog-scrollable modal-lg" role="presentation">
                <div
                    ref={dialogRef}
                    className="modal-content"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={titleId}
                    tabIndex={-1}
                    onKeyDown={handleDialogKeyDown}
                >
                    <div className="modal-header">
                        <h2 id={titleId} className="modal-title h5 mb-0">{title}</h2>
                        <button
                            type="button"
                            className="btn-close"
                            aria-label={closeLabel}
                            onClick={onClose}
                            disabled={saving}
                        />
                    </div>
                    <div className="modal-body">
                        {loading && (
                            <div className="text-center p-3" role="status">
                                <div className="spinner-border" aria-hidden="true" />
                            </div>
                        )}
                        <div
                            id={contentId}
                            ref={contentRef}
                            onClick={handleContentClick}
                            // Content is server-rendered element forms; keep the container always mounted
                            // so replaceNodeContents can target it as soon as the fragment returns.
                            hidden={loading}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

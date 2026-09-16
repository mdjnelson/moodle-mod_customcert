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
 * React certificate rearranger.
 *
 * Owns the interactive PDF preview: drag/keyboard positioning and click-to-edit.
 * Element preview HTML and edit forms remain server-rendered so third-party
 * customcertelement_* plugins keep working without frontend changes.
 *
 * @module     mod_customcert/Rearrange
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {getString} from '@moodle/lms/core/stringUtils';
import {redirect} from '@moodle/lms/core/location';
import CertificateElement from './CertificateElement';
import EditElementModal from './EditElementModal';
import {mmToPx} from './position';
import {notifyException, savePositions} from './repository';
import type {
    CertificateElement as CertificateElementData,
    ElementPositionValue,
    RearrangeProps,
} from './types';

/**
 * Top-level rearranger mounted via Moodle's React auto-init mechanism.
 *
 * @param props Props supplied from rearrange.php.
 * @returns Rearranger UI.
 */
export default function Rearrange(props: RearrangeProps) {
    const {
        templateid,
        contextid,
        page,
        elements: initialElements,
        editurl,
        rearrangeurl,
    } = props;

    const [elements, setElements] = useState<CertificateElementData[]>(initialElements);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    // Ref guards against duplicate saves before React re-renders disabled buttons.
    const savingRef = useRef(false);
    const [saveAndCloseLabel, setSaveAndCloseLabel] = useState('Save and close');
    const [saveAndContinueLabel, setSaveAndContinueLabel] = useState('Save and continue');
    const [cancelLabel, setCancelLabel] = useState('Cancel');
    const [pageLabel, setPageLabel] = useState('Certificate page');

    useEffect(() => {
        let cancelled = false;
        getString('saveandclose', 'mod_customcert').then((value) => {
            if (!cancelled) {
                setSaveAndCloseLabel(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        getString('saveandcontinue', 'mod_customcert').then((value) => {
            if (!cancelled) {
                setSaveAndContinueLabel(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        getString('cancel', 'core').then((value) => {
            if (!cancelled) {
                setCancelLabel(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        getString('certificatepage', 'mod_customcert').then((value) => {
            if (!cancelled) {
                setPageLabel(value);
            }
            return undefined;
        }).catch(() => {
            // Keep the English fallback label.
        });
        return () => {
            cancelled = true;
        };
    }, []);

    const pageStyle = useMemo(() => ({
        width: `${page.width}mm`,
        height: `${page.height}mm`,
        position: 'relative' as const,
    }), [page.height, page.width]);

    const editingElement = editingId === null
        ? null
        : elements.find((element) => element.id === editingId) ?? null;

    const handlePositionChange = useCallback((id: number, posx: number, posy: number) => {
        setElements((current) => current.map((element) => (
            element.id === id ? {...element, posx, posy} : element
        )));
    }, []);

    const handleEdit = useCallback((id: number) => {
        setEditingId(id);
    }, []);

    const handleCloseEdit = useCallback(() => {
        setEditingId(null);
    }, []);

    const handleSaved = useCallback((update: Partial<CertificateElementData> & {id: number}) => {
        setElements((current) => current.map((element) => (
            element.id === update.id ? {...element, ...update} : element
        )));
        setEditingId(null);
    }, []);

    const collectPositions = useCallback((): ElementPositionValue[] => {
        return elements.map((element) => ({
            id: element.id,
            posx: element.posx,
            posy: element.posy,
        }));
    }, [elements]);

    const handleSavePositions = useCallback(async(redirectUrl: string) => {
        if (savingRef.current) {
            return;
        }
        savingRef.current = true;
        setSaving(true);
        try {
            await savePositions(templateid, collectPositions());
            redirect(redirectUrl);
        } catch (error) {
            savingRef.current = false;
            setSaving(false);
            await notifyException(error);
        }
    }, [collectPositions, templateid]);

    const handleSaveAndClose = useCallback(() => {
        void handleSavePositions(editurl);
    }, [editurl, handleSavePositions]);

    const handleSaveAndContinue = useCallback(() => {
        void handleSavePositions(rearrangeurl);
    }, [handleSavePositions, rearrangeurl]);

    const handleCancel = useCallback(() => {
        redirect(editurl);
    }, [editurl]);

    // Left/right margin guides matching the usable area.
    const leftGuide = mmToPx(page.leftmargin || 0);
    const rightGuide = mmToPx(page.width) - mmToPx(page.rightmargin || 0);

    return (
        <>
            <div className="buttons">
                <button
                    type="button"
                    className="btn btn-secondary savepositionsbtn"
                    onClick={handleSaveAndClose}
                    disabled={saving}
                >
                    {saveAndCloseLabel}
                </button>
                <button
                    type="button"
                    className="btn btn-secondary applypositionsbtn"
                    onClick={handleSaveAndContinue}
                    disabled={saving}
                >
                    {saveAndContinueLabel}
                </button>
                <button
                    type="button"
                    className="btn btn-secondary cancelbtn"
                    onClick={handleCancel}
                    disabled={saving}
                >
                    {cancelLabel}
                </button>
            </div>
            <div
                id="pdf"
                className="rearrange-pdf"
                data-templateid={templateid}
                data-contextid={contextid}
                style={pageStyle}
                role="group"
                aria-label={pageLabel}
            >
                {(page.leftmargin > 0) && (
                    <div
                        className="rearrange-margin-guide rearrange-margin-guide-left"
                        style={{left: leftGuide}}
                        aria-hidden="true"
                    />
                )}
                {(page.rightmargin > 0) && (
                    <div
                        className="rearrange-margin-guide rearrange-margin-guide-right"
                        style={{left: rightGuide}}
                        aria-hidden="true"
                    />
                )}
                {elements.map((element) => (
                    <CertificateElement
                        key={element.id}
                        element={element}
                        page={page}
                        onPositionChange={handlePositionChange}
                        onEdit={handleEdit}
                    />
                ))}
            </div>
            {editingElement && (
                <EditElementModal
                    templateid={templateid}
                    contextid={contextid}
                    element={editingElement}
                    currentPosx={editingElement.posx}
                    currentPosy={editingElement.posy}
                    onClose={handleCloseEdit}
                    onSaved={handleSaved}
                />
            )}
        </>
    );
}

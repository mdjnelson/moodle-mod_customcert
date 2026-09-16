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
 * Tests that the element edit dialogue cannot be dismissed while a save is in flight.
 *
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import EditElementModal from '../src/EditElementModal';
import * as repository from '../src/repository';
import type {CertificateElement, SaveElementResult} from '../src/types';

const element: CertificateElement = {
    id: 12,
    name: 'Student name',
    posx: 40,
    posy: 60,
    width: 50,
    refpoint: 1,
    alignment: 'C',
    html: '<span class="preview">Student name</span>',
};

const savedElement: SaveElementResult = {
    id: 12,
    posx: 40,
    posy: 60,
    width: 50,
    refpoint: 1,
    alignment: 'C',
    html: '<span class="preview">Student name</span>',
};

let resetAllFormDirtyStates: jest.Mock;

describe('EditElementModal', () => {
    beforeEach(() => {
        mockString('editelement', 'mod_customcert', 'Edit element');
        mockString('close', 'core', 'Close');
        mockAmdModule('core/templates', {
            replaceNodeContents: jest.fn(async(target: Element, html: string) => {
                if (typeof target !== 'string') {
                    target.innerHTML = html;
                }
            }),
        });
        resetAllFormDirtyStates = jest.fn();
        mockAmdModule('core_form/changechecker', {
            resetAllFormDirtyStates,
        });
        mockAmdModule('core/notification', {
            exception: jest.fn(),
        });
        jest.spyOn(repository, 'loadEditElementFragment').mockResolvedValue({
            html: `
                <form id="editelementform">
                    <input type="hidden" name="name" value="Student name" />
                    <button type="submit" id="id_savechanges">Save</button>
                    <button type="button" id="id_cancel">Form cancel</button>
                </form>
            `,
            javascript: '',
        });
    });

    /**
     * Renders the modal with a save() that never resolves, so `saving` stays true for the
     * duration of the test, and returns the resolver so it can be settled afterwards.
     *
     * @returns onClose spy and the pending save resolver.
     */
    async function renderWithPendingSave() {
        let resolveSave: ((value: SaveElementResult) => void) | undefined;
        jest.spyOn(repository, 'saveElement').mockImplementation(
            () => new Promise<SaveElementResult>((resolve) => {
                resolveSave = resolve;
            }),
        );
        const onClose = jest.fn();
        const onSaved = jest.fn();

        render(
            <EditElementModal
                templateid={7}
                contextid={42}
                element={element}
                currentPosx={40}
                currentPosy={60}
                onClose={onClose}
                onSaved={onSaved}
            />,
        );

        const saveChanges = await screen.findByRole('button', {name: 'Save'});
        await act(async() => {
            fireEvent.click(saveChanges);
        });

        return {onClose, resolveSave: resolveSave!};
    }

    it('closes on Escape when no save is in progress', async() => {
        const onClose = jest.fn();
        render(
            <EditElementModal
                templateid={7}
                contextid={42}
                element={element}
                currentPosx={40}
                currentPosy={60}
                onClose={onClose}
                onSaved={jest.fn()}
            />,
        );
        await screen.findByRole('button', {name: 'Save'});

        fireEvent.keyDown(document, {key: 'Escape'});

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('ignores Escape while a save is in flight', async() => {
        const {onClose, resolveSave} = await renderWithPendingSave();

        fireEvent.keyDown(document, {key: 'Escape'});
        expect(onClose).not.toHaveBeenCalled();

        await act(async() => {
            resolveSave(savedElement);
        });
    });

    it('ignores a backdrop click while a save is in flight', async() => {
        const {onClose, resolveSave} = await renderWithPendingSave();

        const backdrop = document.querySelector('.modal.show');
        expect(backdrop).not.toBeNull();
        fireEvent.click(backdrop!);
        expect(onClose).not.toHaveBeenCalled();

        await act(async() => {
            resolveSave(savedElement);
        });
    });

    it('ignores the form Cancel button while a save is in flight', async() => {
        const {onClose, resolveSave} = await renderWithPendingSave();

        fireEvent.click(screen.getByRole('button', {name: 'Form cancel'}));
        expect(onClose).not.toHaveBeenCalled();

        await act(async() => {
            resolveSave(savedElement);
        });
    });

    it('keeps the close button disabled while a save is in flight', async() => {
        const {resolveSave} = await renderWithPendingSave();

        expect(screen.getByRole('button', {name: 'Close'})).toBeDisabled();

        await act(async() => {
            resolveSave(savedElement);
        });
    });

    it('reports a successful save via onSaved without closing itself', async() => {
        let resolveSave: ((value: SaveElementResult) => void) | undefined;
        jest.spyOn(repository, 'saveElement').mockImplementation(
            () => new Promise<SaveElementResult>((resolve) => {
                resolveSave = resolve;
            }),
        );
        const onClose = jest.fn();
        const onSaved = jest.fn();

        render(
            <EditElementModal
                templateid={7}
                contextid={42}
                element={element}
                currentPosx={40}
                currentPosy={60}
                onClose={onClose}
                onSaved={onSaved}
            />,
        );
        const saveChanges = await screen.findByRole('button', {name: 'Save'});
        await act(async() => {
            fireEvent.click(saveChanges);
        });

        await act(async() => {
            resolveSave!(savedElement);
        });

        // A successful save reports the result via onSaved (the parent, e.g. Rearrange,
        // decides whether to close); the modal must not close itself.
        await waitFor(() => {
            expect(onSaved).toHaveBeenCalledWith(expect.objectContaining({id: 12}));
        });
        expect(onClose).not.toHaveBeenCalled();
    });

    it('does not reset the form dirty state when saveElement() rejects', async() => {
        jest.spyOn(repository, 'saveElement').mockRejectedValue(new Error('save failed'));

        render(
            <EditElementModal
                templateid={7}
                contextid={42}
                element={element}
                currentPosx={40}
                currentPosy={60}
                onClose={jest.fn()}
                onSaved={jest.fn()}
            />,
        );
        const saveChanges = await screen.findByRole('button', {name: 'Save'});
        await act(async() => {
            fireEvent.click(saveChanges);
        });

        expect(resetAllFormDirtyStates).not.toHaveBeenCalled();
    });

    it('resets the form dirty state once saveElement() succeeds', async() => {
        jest.spyOn(repository, 'saveElement').mockResolvedValue(savedElement);

        render(
            <EditElementModal
                templateid={7}
                contextid={42}
                element={element}
                currentPosx={40}
                currentPosy={60}
                onClose={jest.fn()}
                onSaved={jest.fn()}
            />,
        );
        const saveChanges = await screen.findByRole('button', {name: 'Save'});
        await act(async() => {
            fireEvent.click(saveChanges);
        });

        await waitFor(() => {
            expect(resetAllFormDirtyStates).toHaveBeenCalledTimes(1);
        });
    });
});

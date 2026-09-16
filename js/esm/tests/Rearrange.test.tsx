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
 * Tests for the React certificate rearranger.
 *
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import * as Ajax from '@moodle/lms/core/ajax';
import Rearrange from '../src/Rearrange';
import type {RearrangeProps, SaveElementResult} from '../src/types';
import * as repository from '../src/repository';

const baseProps: RearrangeProps = {
    templateid: 7,
    contextid: 42,
    pageid: 3,
    page: {
        width: 210,
        height: 297,
        leftmargin: 10,
        rightmargin: 10,
    },
    elements: [
        {
            id: 11,
            name: 'Title text',
            posx: 20,
            posy: 30,
            width: null,
            refpoint: 0,
            alignment: 'L',
            html: '<span class="preview">Certificate title</span>',
        },
        {
            id: 12,
            name: 'Student name',
            posx: 40,
            posy: 60,
            width: 50,
            refpoint: 1,
            alignment: 'C',
            html: '<span class="preview">Student name</span>',
        },
    ],
    editurl: '/mod/customcert/edit.php?tid=7',
    rearrangeurl: '/mod/customcert/rearrange.php?pid=3',
};

const savedElement: SaveElementResult = {
    id: 12,
    posx: 55,
    posy: 70,
    width: 120,
    refpoint: 2,
    alignment: 'R',
    html: '<span class="preview">Updated student name</span>',
};

describe('Rearrange', () => {
    beforeEach(() => {
        mockString('saveandclose', 'mod_customcert', 'Save and close');
        mockString('saveandcontinue', 'mod_customcert', 'Save and continue');
        mockString('cancel', 'core', 'Cancel');
        mockString('editelement', 'mod_customcert', 'Edit element');
        mockString('close', 'core', 'Close');
        mockString('certificatepage', 'mod_customcert', 'Certificate page');
        mockString('draggableelement', 'mod_customcert', 'draggable element');
        mockString('elementdefaultname', 'mod_customcert', 'Element');
        mockAmdModule('core/templates', {
            replaceNodeContents: jest.fn(async(element: Element, html: string) => {
                if (typeof element !== 'string') {
                    element.innerHTML = html;
                }
            }),
        });
        mockAmdModule('core_form/changechecker', {
            resetAllFormDirtyStates: jest.fn(),
        });
        mockAmdModule('core/notification', {
            exception: jest.fn(),
        });
        mockAmdModule('core/fragment', {
            processCollectedJavascript: jest.fn((js: string) => js),
        });
    });

    it('renders the PDF canvas and server-provided element previews', async() => {
        render(<Rearrange {...baseProps} />);
        await screen.findByRole('button', {name: 'Save and close'});

        expect(document.getElementById('pdf')).toBeInTheDocument();
        expect(screen.getByText('Certificate title')).toBeInTheDocument();
        expect(screen.getByText('Student name')).toBeInTheDocument();

        const first = document.getElementById('element-11');
        expect(first).toHaveAttribute('data-refpoint', '0');
        expect(first).toHaveClass('element');
        expect(first).toHaveClass('refpoint-left');
        expect(first).toHaveAttribute('tabindex', '0');
        expect(first).toHaveAttribute('role', 'button');
    });

    it('exposes accessible names for keyboard users', async() => {
        render(<Rearrange {...baseProps} />);
        await screen.findByRole('button', {name: 'Save and close'});

        expect(screen.getByRole('button', {name: 'Title text'})).toBeInTheDocument();
        expect(screen.getByRole('button', {name: 'Student name'})).toBeInTheDocument();
    });

    it('nudges an element with arrow keys without hardcoding element types', async() => {
        render(<Rearrange {...baseProps} />);
        await screen.findByRole('button', {name: 'Save and close'});
        const node = screen.getByRole('button', {name: 'Title text'});
        const beforeLeft = node.style.left;

        fireEvent.keyDown(node, {key: 'ArrowRight'});

        // Position style should change after a keyboard nudge.
        expect(node.style.left).not.toBe(beforeLeft);
    });

    it('renders Save and close, Save and continue, and Cancel controls in React', async() => {
        render(<Rearrange {...baseProps} />);

        await waitFor(() => {
            expect(screen.getByRole('button', {name: 'Save and close'})).toBeInTheDocument();
        });
        expect(screen.getByRole('button', {name: 'Save and continue'})).toBeInTheDocument();
        expect(screen.getByRole('button', {name: 'Cancel'})).toBeInTheDocument();
    });

    it('saves positions then navigates to editurl on Save and close', async() => {
        const savePositions = jest.spyOn(repository, 'savePositions').mockResolvedValue(undefined);
        expectRedirect({url: baseProps.editurl});

        render(<Rearrange {...baseProps} />);
        await waitFor(() => {
            expect(screen.getByRole('button', {name: 'Save and close'})).toBeInTheDocument();
        });

        await act(async() => {
            fireEvent.click(screen.getByRole('button', {name: 'Save and close'}));
        });

        expect(savePositions).toHaveBeenCalledTimes(1);
        expect(savePositions).toHaveBeenCalledWith(7, expect.arrayContaining([
            expect.objectContaining({id: 11, posx: 20, posy: 30}),
            expect.objectContaining({id: 12, posx: 40, posy: 60}),
        ]));
    });

    it('saves positions then navigates to rearrangeurl on Save and continue', async() => {
        const savePositions = jest.spyOn(repository, 'savePositions').mockResolvedValue(undefined);
        expectRedirect({url: baseProps.rearrangeurl});

        render(<Rearrange {...baseProps} />);
        await waitFor(() => {
            expect(screen.getByRole('button', {name: 'Save and continue'})).toBeInTheDocument();
        });

        await act(async() => {
            fireEvent.click(screen.getByRole('button', {name: 'Save and continue'}));
        });

        expect(savePositions).toHaveBeenCalledTimes(1);
    });

    it('navigates to editurl without saving on Cancel', async() => {
        const savePositions = jest.spyOn(repository, 'savePositions').mockResolvedValue(undefined);
        expectRedirect({url: baseProps.editurl});

        render(<Rearrange {...baseProps} />);
        await waitFor(() => {
            expect(screen.getByRole('button', {name: 'Cancel'})).toBeInTheDocument();
        });

        await act(async() => {
            fireEvent.click(screen.getByRole('button', {name: 'Cancel'}));
        });

        expect(savePositions).not.toHaveBeenCalled();
    });

    it('prevents duplicate saves while a save is in flight', async() => {
        let resolveSave: (() => void) | undefined;
        const savePositions = jest.spyOn(repository, 'savePositions').mockImplementation(
            () => new Promise<void>((resolve) => {
                resolveSave = resolve;
            }),
        );
        expectRedirect({url: baseProps.editurl});

        render(<Rearrange {...baseProps} />);
        const saveButton = await screen.findByRole('button', {name: 'Save and close'});

        await act(async() => {
            fireEvent.click(saveButton);
            fireEvent.click(saveButton);
        });

        expect(savePositions).toHaveBeenCalledTimes(1);

        await act(async() => {
            resolveSave?.();
        });
    });

    it('disables Cancel while a position save is in flight so navigation cannot happen', async() => {
        let resolveSave: (() => void) | undefined;
        jest.spyOn(repository, 'savePositions').mockImplementation(
            () => new Promise<void>((resolve) => {
                resolveSave = resolve;
            }),
        );
        expectRedirect({url: baseProps.editurl});

        render(<Rearrange {...baseProps} />);
        const saveButton = await screen.findByRole('button', {name: 'Save and close'});
        const cancelButton = screen.getByRole('button', {name: 'Cancel'});
        expect(cancelButton).not.toBeDisabled();

        await act(async() => {
            fireEvent.click(saveButton);
        });

        expect(cancelButton).toBeDisabled();

        await act(async() => {
            resolveSave?.();
        });
    });

    it('merges generic layout properties from save into element state without reload', async() => {
        jest.spyOn(Ajax, 'fetchOne').mockImplementation(async(request) => {
            if (request.methodname === 'core_get_fragment') {
                return {
                    html: `
                        <form id="editelementform">
                            <input type="hidden" name="name" value="Student name" />
                            <button type="submit" id="id_savechanges">Save</button>
                        </form>
                    `,
                    javascript: '',
                };
            }
            if (request.methodname === 'mod_customcert_save_element') {
                return savedElement;
            }
            throw new Error(`Unexpected fetch request to method: ${request.methodname}`);
        });

        render(<Rearrange {...baseProps} />);

        const elementNode = screen.getByRole('button', {name: 'Student name'});
        await act(async() => {
            fireEvent.click(elementNode);
        });

        const closeButton = await screen.findByRole('button', {name: 'Close'});
        expect(closeButton).toHaveAttribute('aria-label', 'Close');

        const saveChanges = await screen.findByRole('button', {name: 'Save'});
        await act(async() => {
            fireEvent.click(saveChanges);
        });

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        const updated = document.getElementById('element-12');
        expect(updated).not.toBeNull();
        expect(updated).toHaveAttribute('data-refpoint', '2');
        expect(updated).toHaveClass('refpoint-right');
        expect(updated).toHaveClass('align-right');
        expect(updated?.style.maxWidth).toBe('120mm');
        expect(screen.getByText('Updated student name')).toBeInTheDocument();
    });
});

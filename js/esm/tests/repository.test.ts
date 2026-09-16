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
 * Tests for rearranger repository helpers.
 *
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Ajax from '@moodle/lms/core/ajax';
import {loadEditElementFragment, serializeForm} from '../src/repository';

describe('loadEditElementFragment', () => {
    it('processes the collected fragment javascript via core/fragment before returning it', async() => {
        jest.spyOn(Ajax, 'fetchOne').mockResolvedValue({
            html: '<div>Element form</div>',
            javascript: '<script>collected script markup</script>',
        });
        const processCollectedJavascript = jest.fn().mockReturnValue('processed js');
        mockAmdModule('core/fragment', {
            processCollectedJavascript,
        });

        const result = await loadEditElementFragment(42, 7, 12);

        expect(processCollectedJavascript).toHaveBeenCalledWith('<script>collected script markup</script>');
        expect(result.javascript).toBe('processed js');
        expect(result.javascript).not.toBe('<script>collected script markup</script>');
    });
});

describe('serializeForm', () => {
    it('collects string name/value pairs from a form', () => {
        document.body.innerHTML = `
            <form id="editelementform">
                <input type="hidden" name="sesskey" value="abc" />
                <input type="text" name="name" value="Title" />
                <select name="refpoint">
                    <option value="0">Left</option>
                    <option value="1" selected>Center</option>
                </select>
                <input type="checkbox" name="flag" value="1" checked />
            </form>
        `;

        const form = document.getElementById('editelementform') as HTMLFormElement;
        const values = serializeForm(form);

        expect(values).toEqual(expect.arrayContaining([
            {name: 'sesskey', value: 'abc'},
            {name: 'name', value: 'Title'},
            {name: 'refpoint', value: '1'},
            {name: 'flag', value: '1'},
        ]));
    });
});

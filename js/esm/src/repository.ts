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
 * Data-access helpers for the certificate rearranger.
 *
 * Wraps existing mod_customcert web services, the editelement fragment, and the
 * legacy ajax.php position saver without changing their contracts.
 *
 * @module     mod_customcert/repository
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {requireAsync} from '@moodle/lms/core/amd';
import config from '@moodle/lms/core/config';
import type {ElementFormValue, ElementPositionValue} from './types';

/** Result of loading a Moodle fragment. */
export type FragmentResult = {
    html: string;
    javascript: string;
};

type NotificationModule = {
    exception: (error: unknown) => void;
};

type TemplatesModule = {
    replaceNodeContents: (
        element: Element | string,
        newHTML: string,
        newJS?: string,
    ) => Promise<unknown> | unknown;
    replaceNode: (
        element: Element | string,
        newHTML: string,
        newJS?: string,
    ) => Promise<unknown> | unknown;
};

type FormChangeCheckerModule = {
    resetAllFormDirtyStates: () => void;
};

type FragmentModule = {
    processCollectedJavascript: (js: string) => string;
};

/**
 * Display a Moodle exception notification for a failed request.
 *
 * @param error Error-like value from a rejected promise or fetch failure.
 */
export async function notifyException(error: unknown): Promise<void> {
    const notification = await requireAsync<NotificationModule>('core/notification');
    notification.exception(error);
}

/**
 * Inject HTML into a node and run any accompanying template JavaScript.
 *
 * Uses core/templates so fragment forms initialise the same way as the legacy AMD code.
 *
 * @param element Target element or selector.
 * @param html HTML to inject.
 * @param js Optional JS returned with the fragment/template.
 */
export async function replaceNodeContents(
    element: Element | string,
    html: string,
    js = '',
): Promise<void> {
    const templates = await requireAsync<TemplatesModule>('core/templates');
    await templates.replaceNodeContents(element, html, js);
}

/**
 * Reset dirty form state after a successful save so navigation is not blocked.
 */
export async function resetFormDirtyStates(): Promise<void> {
    const changechecker = await requireAsync<FormChangeCheckerModule>('core_form/changechecker');
    changechecker.resetAllFormDirtyStates();
}

/**
 * Load the editelement fragment for a certificate element.
 *
 * @param contextid Context id owning the template.
 * @param templateid Template id.
 * @param elementid Element id to edit.
 * @returns Fragment HTML and JavaScript.
 */
export async function loadEditElementFragment(
    contextid: number,
    templateid: number,
    elementid: number,
): Promise<FragmentResult> {
    const result = await fetchOne<{html: string; javascript: string}>({
        methodname: 'core_get_fragment',
        args: {
            component: 'mod_customcert',
            callback: 'editelement',
            contextid,
            args: [
                {name: 'elementid', value: String(elementid)},
                {name: 'templateid', value: String(templateid)},
            ],
        },
    });

    const fragment = await requireAsync<FragmentModule>('core/fragment');

    return {
        html: result.html ?? '',
        // The fragment JavaScript returned by core_get_fragment (potentially including
        // <script> tags) must be normalised the same way core/fragment.loadFragment()
        // does before it can be safely passed to core/templates.
        javascript: fragment.processCollectedJavascript(result.javascript ?? ''),
    };
}

/**
 * Fetch server-rendered preview HTML for an element instance.
 *
 * @param templateid Template id.
 * @param elementid Element id.
 * @returns Preview HTML string.
 */
export async function getElementHtml(templateid: number, elementid: number): Promise<string> {
    return fetchOne<string>({
        methodname: 'mod_customcert_get_element_html',
        args: {
            templateid,
            elementid,
        },
    });
}

/**
 * Persist element form values via mod_customcert_save_element.
 *
 * @param templateid Template id.
 * @param elementid Element id.
 * @param values Serialized form name/value pairs.
 * @returns True on success.
 */
export async function saveElement(
    templateid: number,
    elementid: number,
    values: ElementFormValue[],
): Promise<boolean> {
    return fetchOne<boolean>({
        methodname: 'mod_customcert_save_element',
        args: {
            templateid,
            elementid,
            values,
        },
    });
}

/**
 * Save rearranged element positions through the existing ajax.php endpoint.
 *
 * @param templateid Template id.
 * @param values Element id/posx/posy triples in millimetres.
 */
export async function savePositions(
    templateid: number,
    values: ElementPositionValue[],
): Promise<void> {
    const body = new URLSearchParams();
    body.set('tid', String(templateid));
    body.set('sesskey', config.sesskey);
    body.set('values', JSON.stringify(values));

    const response = await fetch(`${config.wwwroot}/mod/customcert/ajax.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body.toString(),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const message = await response.text();
        throw new Error(`${response.status} ${response.statusText}: ${message}`);
    }
}

/**
 * Collect name/value pairs from a form element the same way jQuery serializeArray does.
 *
 * @param form Form element to serialise.
 * @returns Name/value pairs suitable for save_element.
 */
export function serializeForm(form: HTMLFormElement): ElementFormValue[] {
    const formData = new FormData(form);
    const values: ElementFormValue[] = [];

    formData.forEach((value, name) => {
        // FormData can contain File values; element edit forms only post strings.
        if (typeof value === 'string') {
            values.push({name, value});
        }
    });

    return values;
}

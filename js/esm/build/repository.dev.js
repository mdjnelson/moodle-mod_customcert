var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import { fetchOne } from "@moodle/lms/core/ajax";
import { requireAsync } from "@moodle/lms/core/amd";
import config from "@moodle/lms/core/config";
async function notifyException(error) {
  const notification = await requireAsync("core/notification");
  notification.exception(error);
}
__name(notifyException, "notifyException");
async function replaceNodeContents(element, html, js = "") {
  const templates = await requireAsync("core/templates");
  await templates.replaceNodeContents(element, html, js);
}
__name(replaceNodeContents, "replaceNodeContents");
async function resetFormDirtyStates() {
  const changechecker = await requireAsync("core_form/changechecker");
  changechecker.resetAllFormDirtyStates();
}
__name(resetFormDirtyStates, "resetFormDirtyStates");
async function loadEditElementFragment(contextid, templateid, elementid) {
  const result = await fetchOne({
    methodname: "core_get_fragment",
    args: {
      component: "mod_customcert",
      callback: "editelement",
      contextid,
      args: [
        { name: "elementid", value: String(elementid) },
        { name: "templateid", value: String(templateid) }
      ]
    }
  });
  const fragment = await requireAsync("core/fragment");
  return {
    html: result.html ?? "",
    // The fragment JavaScript returned by core_get_fragment (potentially including
    // <script> tags) must be normalised the same way core/fragment.loadFragment()
    // does before it can be safely passed to core/templates.
    javascript: fragment.processCollectedJavascript(result.javascript ?? "")
  };
}
__name(loadEditElementFragment, "loadEditElementFragment");
async function getElementHtml(templateid, elementid) {
  return fetchOne({
    methodname: "mod_customcert_get_element_html",
    args: {
      templateid,
      elementid
    }
  });
}
__name(getElementHtml, "getElementHtml");
async function saveElement(templateid, elementid, values) {
  return fetchOne({
    methodname: "mod_customcert_save_element",
    args: {
      templateid,
      elementid,
      values
    }
  });
}
__name(saveElement, "saveElement");
async function savePositions(templateid, values) {
  const body = new URLSearchParams();
  body.set("tid", String(templateid));
  body.set("sesskey", config.sesskey);
  body.set("values", JSON.stringify(values));
  const response = await fetch(`${config.wwwroot}/mod/customcert/ajax.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: body.toString(),
    credentials: "same-origin"
  });
  if (!response.ok) {
    const message = await response.text();
    throw new Error(`${response.status} ${response.statusText}: ${message}`);
  }
}
__name(savePositions, "savePositions");
function serializeForm(form) {
  const formData = new FormData(form);
  const values = [];
  formData.forEach((value, name) => {
    if (typeof value === "string") {
      values.push({ name, value });
    }
  });
  return values;
}
__name(serializeForm, "serializeForm");
export {
  getElementHtml,
  loadEditElementFragment,
  notifyException,
  replaceNodeContents,
  resetFormDirtyStates,
  saveElement,
  savePositions,
  serializeForm
};
//# sourceMappingURL=repository.dev.js.map

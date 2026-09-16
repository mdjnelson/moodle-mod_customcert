var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
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
  useState
} from "react";
import { getString } from "@moodle/lms/core/stringUtils";
import {
  loadEditElementFragment,
  notifyException,
  replaceNodeContents,
  resetFormDirtyStates,
  saveElement,
  serializeForm
} from "./repository";
function EditElementModal({
  templateid,
  contextid,
  element,
  currentPosx,
  currentPosy,
  onClose,
  onSaved
}) {
  const titleId = useId();
  const contentId = useId();
  const dialogRef = useRef(null);
  const contentRef = useRef(null);
  const previousFocusRef = useRef(null);
  const [title, setTitle] = useState("Edit element");
  const [closeLabel, setCloseLabel] = useState("Close");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const savingRef = useRef(false);
  useEffect(() => {
    savingRef.current = saving;
  }, [saving]);
  useEffect(() => {
    let cancelled = false;
    getString("editelement", "mod_customcert").then((value) => {
      if (!cancelled) {
        setTitle(value);
      }
      return void 0;
    }).catch(() => {
    });
    getString("close", "core").then((value) => {
      if (!cancelled) {
        setCloseLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    return () => {
      cancelled = true;
    };
  }, []);
  useEffect(() => {
    previousFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const handleKeyDown = /* @__PURE__ */ __name((event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        if (!savingRef.current) {
          onClose();
        }
      }
    }, "handleKeyDown");
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      previousFocusRef.current?.focus();
    };
  }, [onClose]);
  useEffect(() => {
    let cancelled = false;
    const load = /* @__PURE__ */ __name(async () => {
      setLoading(true);
      try {
        const fragment = await loadEditElementFragment(contextid, templateid, element.id);
        if (cancelled || !contentRef.current) {
          return;
        }
        await replaceNodeContents(contentRef.current, fragment.html, fragment.javascript);
        const posxInput = contentRef.current.querySelector("#id_posx");
        const posyInput = contentRef.current.querySelector("#id_posy");
        if (posxInput) {
          posxInput.value = String(currentPosx);
        }
        if (posyInput) {
          posyInput.value = String(currentPosy);
        }
        setLoading(false);
        window.setTimeout(() => {
          const focusable = contentRef.current?.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
          );
          (focusable ?? dialogRef.current)?.focus();
        }, 0);
      } catch (error) {
        if (!cancelled) {
          await notifyException(error);
          onClose();
        }
      }
    }, "load");
    void load();
    return () => {
      cancelled = true;
    };
  }, [contextid, templateid, element.id, currentPosx, currentPosy, onClose]);
  const handleBackdropClick = useCallback((event) => {
    if (event.target === event.currentTarget && !savingRef.current) {
      onClose();
    }
  }, [onClose]);
  const handleDialogKeyDown = useCallback((event) => {
    if (event.key !== "Tab" || !dialogRef.current) {
      return;
    }
    const focusable = Array.from(dialogRef.current.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )).filter((node) => !node.hasAttribute("disabled") && node.tabIndex !== -1);
    if (focusable.length === 0) {
      event.preventDefault();
      dialogRef.current.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && active === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  }, []);
  const handleContentClick = useCallback(async (event) => {
    const target = event.target;
    if (!target) {
      return;
    }
    const cancelButton = target.closest("#id_cancel");
    if (cancelButton) {
      event.preventDefault();
      if (!savingRef.current) {
        onClose();
      }
      return;
    }
    const saveButton = target.closest("#id_savechanges");
    if (!saveButton || saving) {
      return;
    }
    event.preventDefault();
    const form = contentRef.current?.querySelector("#editelementform");
    if (!form) {
      return;
    }
    setSaving(true);
    try {
      const values = serializeForm(form);
      const saved = await saveElement(templateid, element.id, values);
      await resetFormDirtyStates();
      onSaved({
        id: saved.id,
        html: saved.html,
        width: saved.width,
        refpoint: saved.refpoint,
        posx: saved.posx,
        posy: saved.posy,
        alignment: saved.alignment
      });
    } catch (error) {
      await notifyException(error);
      setSaving(false);
    }
  }, [element.id, onSaved, saving, templateid]);
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "modal show d-block",
      tabIndex: -1,
      role: "presentation",
      style: { backgroundColor: "rgba(0, 0, 0, 0.5)" },
      onClick: handleBackdropClick,
      children: /* @__PURE__ */ jsxDEV("div", { className: "modal-dialog modal-dialog-scrollable modal-lg", role: "presentation", children: /* @__PURE__ */ jsxDEV(
        "div",
        {
          ref: dialogRef,
          className: "modal-content",
          role: "dialog",
          "aria-modal": "true",
          "aria-labelledby": titleId,
          tabIndex: -1,
          onKeyDown: handleDialogKeyDown,
          children: [
            /* @__PURE__ */ jsxDEV("div", { className: "modal-header", children: [
              /* @__PURE__ */ jsxDEV("h2", { id: titleId, className: "modal-title h5 mb-0", children: title }, void 0, false, {
                fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
                lineNumber: 285,
                columnNumber: 25
              }, this),
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn-close",
                  "aria-label": closeLabel,
                  onClick: onClose,
                  disabled: saving
                },
                void 0,
                false,
                {
                  fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
                  lineNumber: 286,
                  columnNumber: 25
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
              lineNumber: 284,
              columnNumber: 21
            }, this),
            /* @__PURE__ */ jsxDEV("div", { className: "modal-body", children: [
              loading && /* @__PURE__ */ jsxDEV("div", { className: "text-center p-3", role: "status", children: /* @__PURE__ */ jsxDEV("div", { className: "spinner-border", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
                lineNumber: 297,
                columnNumber: 33
              }, this) }, void 0, false, {
                fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
                lineNumber: 296,
                columnNumber: 29
              }, this),
              /* @__PURE__ */ jsxDEV(
                "div",
                {
                  id: contentId,
                  ref: contentRef,
                  onClick: handleContentClick,
                  hidden: loading
                },
                void 0,
                false,
                {
                  fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
                  lineNumber: 300,
                  columnNumber: 25
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
              lineNumber: 294,
              columnNumber: 21
            }, this)
          ]
        },
        void 0,
        true,
        {
          fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
          lineNumber: 275,
          columnNumber: 17
        },
        this
      ) }, void 0, false, {
        fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
        lineNumber: 274,
        columnNumber: 13
      }, this)
    },
    void 0,
    false,
    {
      fileName: "public/mod/customcert/js/esm/src/EditElementModal.tsx",
      lineNumber: 267,
      columnNumber: 9
    },
    this
  );
}
__name(EditElementModal, "EditElementModal");
export {
  EditElementModal as default
};
//# sourceMappingURL=EditElementModal.dev.js.map

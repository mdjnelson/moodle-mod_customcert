var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { getString } from "@moodle/lms/core/stringUtils";
import { redirect } from "@moodle/lms/core/location";
import CertificateElement from "./CertificateElement";
import EditElementModal from "./EditElementModal";
import { mmToPx } from "./position";
import { notifyException, savePositions } from "./repository";
function Rearrange(props) {
  const {
    templateid,
    contextid,
    page,
    elements: initialElements,
    editurl,
    rearrangeurl
  } = props;
  const [elements, setElements] = useState(initialElements);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  const savingRef = useRef(false);
  const [saveAndCloseLabel, setSaveAndCloseLabel] = useState("Save and close");
  const [saveAndContinueLabel, setSaveAndContinueLabel] = useState("Save and continue");
  const [cancelLabel, setCancelLabel] = useState("Cancel");
  const [pageLabel, setPageLabel] = useState("Certificate page");
  useEffect(() => {
    let cancelled = false;
    getString("saveandclose", "mod_customcert").then((value) => {
      if (!cancelled) {
        setSaveAndCloseLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    getString("saveandcontinue", "mod_customcert").then((value) => {
      if (!cancelled) {
        setSaveAndContinueLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    getString("cancel", "core").then((value) => {
      if (!cancelled) {
        setCancelLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    getString("certificatepage", "mod_customcert").then((value) => {
      if (!cancelled) {
        setPageLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    return () => {
      cancelled = true;
    };
  }, []);
  const pageStyle = useMemo(() => ({
    width: `${page.width}mm`,
    height: `${page.height}mm`,
    position: "relative"
  }), [page.height, page.width]);
  const editingElement = editingId === null ? null : elements.find((element) => element.id === editingId) ?? null;
  const handlePositionChange = useCallback((id, posx, posy) => {
    setElements((current) => current.map((element) => element.id === id ? { ...element, posx, posy } : element));
  }, []);
  const handleEdit = useCallback((id) => {
    setEditingId(id);
  }, []);
  const handleCloseEdit = useCallback(() => {
    setEditingId(null);
  }, []);
  const handleSaved = useCallback((update) => {
    setElements((current) => current.map((element) => element.id === update.id ? { ...element, ...update } : element));
    setEditingId(null);
  }, []);
  const collectPositions = useCallback(() => {
    return elements.map((element) => ({
      id: element.id,
      posx: element.posx,
      posy: element.posy
    }));
  }, [elements]);
  const handleSavePositions = useCallback(async (redirectUrl) => {
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
  const leftGuide = mmToPx(page.leftmargin || 0);
  const rightGuide = mmToPx(page.width) - mmToPx(page.rightmargin || 0);
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "buttons", children: [
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-secondary savepositionsbtn",
          onClick: handleSaveAndClose,
          disabled: saving,
          children: saveAndCloseLabel
        },
        void 0,
        false,
        {
          fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
          lineNumber: 180,
          columnNumber: 17
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-secondary applypositionsbtn",
          onClick: handleSaveAndContinue,
          disabled: saving,
          children: saveAndContinueLabel
        },
        void 0,
        false,
        {
          fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
          lineNumber: 188,
          columnNumber: 17
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-secondary cancelbtn",
          onClick: handleCancel,
          disabled: saving,
          children: cancelLabel
        },
        void 0,
        false,
        {
          fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
          lineNumber: 196,
          columnNumber: 17
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
      lineNumber: 179,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        id: "pdf",
        className: "rearrange-pdf",
        "data-templateid": templateid,
        "data-contextid": contextid,
        style: pageStyle,
        role: "group",
        "aria-label": pageLabel,
        children: [
          page.leftmargin > 0 && /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "rearrange-margin-guide rearrange-margin-guide-left",
              style: { left: leftGuide },
              "aria-hidden": "true"
            },
            void 0,
            false,
            {
              fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
              lineNumber: 215,
              columnNumber: 21
            },
            this
          ),
          page.rightmargin > 0 && /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "rearrange-margin-guide rearrange-margin-guide-right",
              style: { left: rightGuide },
              "aria-hidden": "true"
            },
            void 0,
            false,
            {
              fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
              lineNumber: 222,
              columnNumber: 21
            },
            this
          ),
          elements.map((element) => /* @__PURE__ */ jsxDEV(
            CertificateElement,
            {
              element,
              page,
              onPositionChange: handlePositionChange,
              onEdit: handleEdit
            },
            element.id,
            false,
            {
              fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
              lineNumber: 229,
              columnNumber: 21
            },
            this
          ))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
        lineNumber: 205,
        columnNumber: 13
      },
      this
    ),
    editingElement && /* @__PURE__ */ jsxDEV(
      EditElementModal,
      {
        templateid,
        contextid,
        element: editingElement,
        currentPosx: editingElement.posx,
        currentPosy: editingElement.posy,
        onClose: handleCloseEdit,
        onSaved: handleSaved
      },
      void 0,
      false,
      {
        fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
        lineNumber: 239,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/mod/customcert/js/esm/src/Rearrange.tsx",
    lineNumber: 178,
    columnNumber: 9
  }, this);
}
__name(Rearrange, "Rearrange");
export {
  Rearrange as default
};
//# sourceMappingURL=Rearrange.dev.js.map

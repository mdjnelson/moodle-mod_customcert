var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
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
  useState
} from "react";
import { getString } from "@moodle/lms/core/stringUtils";
import {
  DRAG_THRESHOLD_PX,
  KEYBOARD_NUDGE_MM,
  KEYBOARD_NUDGE_MM_LARGE
} from "./constants";
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
  topPxFromPosyMm
} from "./position";
function CertificateElement({
  element,
  page,
  onPositionChange,
  onEdit
}) {
  const nodeRef = useRef(null);
  const dragRef = useRef(null);
  const skipClickRef = useRef(false);
  const [nodeSize, setNodeSize] = useState({ width: 0, height: 0 });
  const [dragOffset, setDragOffset] = useState(null);
  const [draggableLabel, setDraggableLabel] = useState("draggable element");
  const [defaultName, setDefaultName] = useState(`Element ${element.id}`);
  useEffect(() => {
    let cancelled = false;
    getString("draggableelement", "mod_customcert").then((value) => {
      if (!cancelled) {
        setDraggableLabel(value);
      }
      return void 0;
    }).catch(() => {
    });
    return () => {
      cancelled = true;
    };
  }, []);
  useEffect(() => {
    let cancelled = false;
    getString("elementdefaultname", "mod_customcert", element.id).then((value) => {
      if (!cancelled) {
        setDefaultName(value);
      }
      return void 0;
    }).catch(() => {
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
    setNodeSize({ width: rect.width, height: rect.height });
  }, []);
  useLayoutEffect(() => {
    measure();
  }, [measure, element.html, element.width, element.refpoint]);
  useEffect(() => {
    const node = nodeRef.current;
    if (!node || typeof ResizeObserver === "undefined") {
      return void 0;
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
  const commitPosition = useCallback((nextLeft, nextTop, width, height) => {
    const rect = { left: nextLeft, top: nextTop, width, height };
    if (isOutOfBounds(rect, page)) {
      setDragOffset(null);
      return;
    }
    const posx = posxMmFromLeftPx(nextLeft, element.refpoint, width);
    const posy = posyMmFromTopPx(nextTop);
    setDragOffset(null);
    onPositionChange(element.id, posx, posy);
  }, [element.id, element.refpoint, onPositionChange, page]);
  const handlePointerDown = useCallback((event) => {
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
      height: rect.height
    };
    skipClickRef.current = false;
    setDragOffset({ left, top });
  }, [left, top]);
  const handlePointerMove = useCallback((event) => {
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
      top: drag.originTop + deltaY
    });
  }, []);
  const handlePointerUp = useCallback((event) => {
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
      skipClickRef.current = true;
      commitPosition(nextLeft, nextTop, drag.width, drag.height);
    } else {
      setDragOffset(null);
    }
  }, [commitPosition]);
  const handlePointerCancel = useCallback((event) => {
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
  const handleKeyDown = useCallback((event) => {
    if (event.key === "Enter" || event.key === " ") {
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
      case "ArrowLeft":
        nextLeft -= nudgePx;
        handled = true;
        break;
      case "ArrowRight":
        nextLeft += nudgePx;
        handled = true;
        break;
      case "ArrowUp":
        nextTop -= nudgePx;
        handled = true;
        break;
      case "ArrowDown":
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
    const clamped = clampRectToPage({ left: nextLeft, top: nextTop, width, height }, page);
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
    widthPx
  ]);
  const className = [
    "element",
    refpointClassName(element.refpoint),
    alignmentClassName(element.alignment),
    dragOffset ? "is-dragging" : ""
  ].filter(Boolean).join(" ");
  const label = element.name ? `${element.name}` : defaultName;
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: nodeRef,
      id: `element-${element.id}`,
      className,
      "data-refpoint": element.refpoint,
      role: "button",
      tabIndex: 0,
      "aria-label": label,
      "aria-roledescription": draggableLabel,
      "aria-grabbed": dragOffset ? true : void 0,
      style: {
        left,
        top,
        position: "absolute",
        maxWidth: element.width ? `${element.width}mm` : void 0,
        touchAction: "none",
        cursor: dragOffset ? "grabbing" : "move"
      },
      onPointerDown: handlePointerDown,
      onPointerMove: handlePointerMove,
      onPointerUp: handlePointerUp,
      onPointerCancel: handlePointerCancel,
      onClick: handleClick,
      onKeyDown: handleKeyDown,
      dangerouslySetInnerHTML: { __html: element.html }
    },
    void 0,
    false,
    {
      fileName: "public/mod/customcert/js/esm/src/CertificateElement.tsx",
      lineNumber: 330,
      columnNumber: 9
    },
    this
  );
}
__name(CertificateElement, "CertificateElement");
export {
  CertificateElement as default
};
//# sourceMappingURL=CertificateElement.dev.js.map

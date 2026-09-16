var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Position and refpoint math helpers for the certificate rearranger.
 *
 * Pure functions ported from the legacy YUI/AMD rearranger so drag, keyboard
 * nudging, and save/load round-trips stay consistent with existing certificates.
 *
 * @module     mod_customcert/position
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
  ALIGN_CENTER,
  ALIGN_LEFT,
  ALIGN_RIGHT,
  PIXELSINMM,
  REF_POINT_TOPCENTER,
  REF_POINT_TOPLEFT,
  REF_POINT_TOPRIGHT
} from "./constants";
function mmToPx(mm) {
  return mm * PIXELSINMM;
}
__name(mmToPx, "mmToPx");
function pxToMm(px) {
  return Math.round(px / PIXELSINMM);
}
__name(pxToMm, "pxToMm");
function refpointClassName(refpoint) {
  switch (refpoint) {
    case REF_POINT_TOPCENTER:
      return "refpoint-center";
    case REF_POINT_TOPRIGHT:
      return "refpoint-right";
    case REF_POINT_TOPLEFT:
    default:
      return "refpoint-left";
  }
}
__name(refpointClassName, "refpointClassName");
function alignmentClassName(alignment) {
  switch (alignment) {
    case ALIGN_CENTER:
      return "align-center";
    case ALIGN_RIGHT:
      return "align-right";
    case ALIGN_LEFT:
    default:
      return "align-left";
  }
}
__name(alignmentClassName, "alignmentClassName");
function effectiveNodeWidthPx(measuredWidthPx, maxWidthMm) {
  const maxWidthPx = maxWidthMm ? mmToPx(maxWidthMm) : 0;
  if (maxWidthPx && measuredWidthPx > maxWidthPx) {
    return maxWidthPx;
  }
  return measuredWidthPx;
}
__name(effectiveNodeWidthPx, "effectiveNodeWidthPx");
function leftPxFromPosxMm(posxMm, refpoint, nodeWidthPx) {
  let posx = mmToPx(posxMm);
  switch (refpoint) {
    case REF_POINT_TOPCENTER:
      posx -= nodeWidthPx / 2;
      break;
    case REF_POINT_TOPRIGHT:
      posx = posx - nodeWidthPx + 2;
      break;
    default:
      break;
  }
  return posx;
}
__name(leftPxFromPosxMm, "leftPxFromPosxMm");
function posxMmFromLeftPx(leftPx, refpoint, nodeWidthPx) {
  let posx = leftPx;
  switch (refpoint) {
    case REF_POINT_TOPCENTER:
      posx += nodeWidthPx / 2;
      break;
    case REF_POINT_TOPRIGHT:
      posx += nodeWidthPx;
      break;
    default:
      break;
  }
  return pxToMm(posx);
}
__name(posxMmFromLeftPx, "posxMmFromLeftPx");
function topPxFromPosyMm(posyMm) {
  return mmToPx(posyMm);
}
__name(topPxFromPosyMm, "topPxFromPosyMm");
function posyMmFromTopPx(topPx) {
  return pxToMm(topPx);
}
__name(posyMmFromTopPx, "posyMmFromTopPx");
function pageBoundariesPx(page) {
  const widthPx = mmToPx(page.width);
  const heightPx = mmToPx(page.height);
  const left = mmToPx(page.leftmargin || 0);
  const right = widthPx - mmToPx(page.rightmargin || 0);
  return {
    left,
    right,
    top: 0,
    bottom: heightPx
  };
}
__name(pageBoundariesPx, "pageBoundariesPx");
function isOutOfBounds(rect, page) {
  const bounds = pageBoundariesPx(page);
  const right = rect.left + rect.width;
  const bottom = rect.top + rect.height;
  if (rect.left < bounds.left || right > bounds.right) {
    return true;
  }
  if (rect.top < bounds.top || bottom > bounds.bottom) {
    return true;
  }
  return false;
}
__name(isOutOfBounds, "isOutOfBounds");
function clampRectToPage(rect, page) {
  const bounds = pageBoundariesPx(page);
  let { left, top, width, height } = rect;
  if (left < bounds.left) {
    left = bounds.left;
  }
  if (left + width > bounds.right) {
    left = Math.max(bounds.left, bounds.right - width);
  }
  if (top < bounds.top) {
    top = bounds.top;
  }
  if (top + height > bounds.bottom) {
    top = Math.max(bounds.top, bounds.bottom - height);
  }
  return { left, top, width, height };
}
__name(clampRectToPage, "clampRectToPage");
export {
  alignmentClassName,
  clampRectToPage,
  effectiveNodeWidthPx,
  isOutOfBounds,
  leftPxFromPosxMm,
  mmToPx,
  pageBoundariesPx,
  posxMmFromLeftPx,
  posyMmFromTopPx,
  pxToMm,
  refpointClassName,
  topPxFromPosyMm
};
//# sourceMappingURL=position.dev.js.map

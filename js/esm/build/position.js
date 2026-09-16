import{ALIGN_CENTER as p,ALIGN_LEFT as l,ALIGN_RIGHT as x,PIXELSINMM as b,REF_POINT_TOPCENTER as f,REF_POINT_TOPLEFT as h,REF_POINT_TOPRIGHT as a}from"./constants";/**
 * Position and refpoint math helpers for the certificate rearranger.
 *
 * Pure functions ported from the legacy YUI/AMD rearranger so drag, keyboard
 * nudging, and save/load round-trips stay consistent with existing certificates.
 *
 * @module     mod_customcert/position
 * @copyright  2026 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function u(t){return t*b}function c(t){return Math.round(t/b)}function P(t){switch(t){case f:return"refpoint-center";case a:return"refpoint-right";case h:default:return"refpoint-left"}}function T(t){switch(t){case p:return"align-center";case x:return"align-right";case l:default:return"align-left"}}function d(t,n){const e=n?u(n):0;return e&&t>e?e:t}function N(t,n,e){let r=u(t);switch(n){case f:r-=e/2;break;case a:r=r-e+2;break;default:break}return r}function R(t,n,e){let r=t;switch(n){case f:r+=e/2;break;case a:r+=e;break;default:break}return c(r)}function E(t){return u(t)}function I(t){return c(t)}function s(t){const n=u(t.width),e=u(t.height),r=u(t.leftmargin||0),o=n-u(t.rightmargin||0);return{left:r,right:o,top:0,bottom:e}}function w(t,n){const e=s(n),r=t.left+t.width,o=t.top+t.height;return t.left<e.left||r>e.right||t.top<e.top||o>e.bottom}function F(t,n){const e=s(n);let{left:r,top:o,width:i,height:m}=t;return r<e.left&&(r=e.left),r+i>e.right&&(r=Math.max(e.left,e.right-i)),o<e.top&&(o=e.top),o+m>e.bottom&&(o=Math.max(e.top,e.bottom-m)),{left:r,top:o,width:i,height:m}}export{T as alignmentClassName,F as clampRectToPage,d as effectiveNodeWidthPx,w as isOutOfBounds,N as leftPxFromPosxMm,u as mmToPx,s as pageBoundariesPx,R as posxMmFromLeftPx,I as posyMmFromTopPx,c as pxToMm,P as refpointClassName,E as topPxFromPosyMm};

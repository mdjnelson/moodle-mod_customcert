# Proposed policy for issuing certificates with missing elements

A certificate may reference an element plugin that is absent or incompatible. The designer
keeps the record and displays a placeholder. When the same template renders a real PDF,
the placeholder contributes no content. A certificate can therefore appear to issue
successfully while required text, branding, or a signature is missing.

## Proposed behaviour

Block new issuance and delivery of regenerated PDFs when an element implementation
cannot be resolved. Preserve preview and designer access so managers can recover the
template. A registered element that intentionally renders no content remains valid.

Show the recipient a message that the certificate is temporarily unavailable. Give
template managers and scheduled task logs the affected template and element type so
they can restore the plugin or repair the template. Avoid exposing raw element payloads.

| Path | Proposed outcome |
| --- | --- |
| Designer and preview | Keep the records and allow diagnosis or repair. |
| New issue | Check the template before writing the issue record or firing its creation event. |
| Existing issue and regenerated PDF | Keep the issue record and block incomplete output until repair. |
| Scheduled issuance and email | Report the affected template and continue with other templates. Do not mark an unsent email as delivered. |
| Installed element with deliberately empty output | Issue normally. |

## Implementation boundary

Reuse element resolution for the preflight so an unregistered plugin and a rejected or
failed implementation are classified consistently. The check needs to cover interactive,
scheduled, and programmatic issuance, as well as actual PDF generation for existing issues.
Avoid checking the same template once per recipient during a batch. Preview is a separate
path and should remain tolerant.

Existing sites that depend on partially rendered certificates would see a change in
behaviour. The release guidance should explain how managers can identify and repair an
affected template. Existing issue records and historical data migration are preserved.

Before implementing, agree on this policy and the user-facing diagnostic. Regression
coverage should exercise a missing plugin, an incompatible implementation, a working
element, and an intentionally invisible element. Verify that a failed new issue produces
neither a success record nor a delivery, and that other templates continue to issue.

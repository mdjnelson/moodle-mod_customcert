# Changelog

All notable changes to this project will be documented in this file.

Note - All hash comments refer to the issue number. Eg. #169 refers to https://github.com/mdjnelson/moodle-mod_customcert/issues/169.

## [5.2.0] - 2026-XX-YY

### Requirements
- Minimum supported version is **Moodle 5.2** (**PHP 8.3+**).

### Added

#### Major element API changes

Custom Certificate elements now separate element payload, layout, and rendering concerns.

Element data that was previously stored in dedicated columns (`font`, `fontsize`, `colour`, `width`) is now stored inside the element JSON payload in `customcert_elements.data`. Layout values (`posx`, `posy`, `refpoint`, `alignment`) are managed by the element repository/layout service rather than by individual element implementations.

Third-party elements should migrate to the new element interfaces under `mod_customcert\element`.

The legacy `mod_customcert\element` base class remains available for compatibility during this major release, but legacy hooks with replacement interfaces are deprecated and will be removed in a future major version.

> **Important**: Third-party element plugins that extend `mod_customcert\element` and override `render()` or `render_html()` **must update their method signatures** to match the new typed signatures before upgrading to 5.2. Plugins with old untyped signatures for these two methods will fail at class-load time. The deprecated legacy hooks (`render_form_elements`, `definition_after_data`, `validate_form_elements`, `save_form_elements`, `save_unique_data`, `after_restore`, `copy_element`) retain untyped base signatures and are intended to remain compatible with old-style untyped overrides. `can_add()` retains its typed `bool` return and is not deprecated; third-party overrides must declare `public static function can_add(): bool`. See the "Element rendering signatures" section below for the required `render`/`render_html` signatures.

New elements should use:
- `element_interface` — for the core element contract (identity, payload, type)
- `form_element_interface` — required for registered certificate elements; provides edit-form lifecycle hooks including `build_form()`
- `stylable_element_interface` — only when the element supports standard visual styling (`font`, `fontsize`, `colour`, `width`)
- `layout_element_interface` — exposes repository-managed layout values (`posx`, `posy`, `refpoint`, `alignment`) for common PDF/HTML rendering helpers
- `persistable_element_interface` — normalises submitted form data into the JSON payload
- `validatable_element_interface` — validates submitted form data
- `preparable_form_interface` — populates edit-form fields from stored payload
- `restorable_element_interface` — remaps internal references after backup restore
- Repository/service APIs for persistence and layout changes

Elements using the common renderer helpers (`element_helper::render_content()` / `render_html_content()`) must also implement both `stylable_element_interface` and `layout_element_interface`. Elements with fully custom rendering may implement `renderable_element_interface` directly without these.

An `unknown_element` fallback is used when a requested element type is not registered; it renders gracefully rather than throwing.

No deprecated bundled element implementations are shipped in this release. Deprecated element hooks remain only on the legacy `mod_customcert\element` base class to support third-party plugin migration.

> **Legacy internal state removed**: Third-party elements that accessed protected internal state directly will need to update. In particular, direct access to `$this->element`, `$this->font`, `$this->fontsize`, `$this->colour`, `$this->width`, or magic property fallback (`__get()`) must be replaced with the new getters, payload helpers, and repository-managed layout APIs. Visual values now live in `customcert_elements.data`; layout values are managed separately by the repository/layout services.

#### Template export and import
- Templates can now be exported as a ZIP archive and imported on any Moodle site running this plugin.
- **Export**: from the *Manage templates* page, click the download icon next to any template to download a `.zip` file containing the template structure (pages and elements) and any associated files (e.g. images used by elements).
- **Import**: from the *Manage templates* page, click *Import template* to upload a previously exported `.zip` and recreate the template in the current context.
- New export/import service layer under `mod_customcert\export`:
  - `template_file_manager` — orchestrates ZIP creation and extraction, delegates to `template` for DB writes, and rolls back copied files on failure.
  - `template` / `page` / `element` — transactional DB import of template structure; each level delegates to the next.
  - `template_appendix_manager` — handles copying element-associated files in and out of the ZIP (manifest-driven, with path-traversal and size-limit guards).
  - `template_import_logger_interface` / `template_logger` — collects warnings produced during import (e.g. unknown element types) and surfaces them as Moodle notifications.

#### Service layer
- New service-layer APIs for template/page/element CRUD:
  - `mod_customcert\service\template_service` — orchestrates template/page/element create, update, delete, and move operations (plus DTOs such as `page_update`)
  - `mod_customcert\service\template_repository` — low-level DB access for `customcert_templates`
  - `mod_customcert\service\page_repository` — low-level DB access for `customcert_pages`
  - `mod_customcert\service\element_repository` — low-level DB access for `customcert_elements`
  - `mod_customcert\service\template_duplication_service` — transactional copy of a template with all its pages and elements
  - `mod_customcert\service\template_load_service` — replaces an existing template's pages/elements with content from another template
  - `mod_customcert\service\item_move_service` — handles moving pages and elements by swapping sequences
  - `mod_customcert\service\pdf_generation_service` — PDF generation, preview, and filename computation
- New certificate issuance and delivery services:
  - `mod_customcert\service\certificate_issue_service` — issues certificates and generates unique codes
  - `mod_customcert\service\certificate_download_service` — handles bulk certificate download for instances and site-wide
  - `mod_customcert\service\certificate_time_service` — computes course time for certificate conditions
  - `mod_customcert\service\certificate_email_service` — sends certificate emails to students and teachers
  - `mod_customcert\service\certificate_issuer_service` — orchestrates the scheduled issuance and email pipeline
- New repository classes:
  - `mod_customcert\service\issue_repository` — queries `customcert_issues`
  - `mod_customcert\service\certificate_repository` — queries certificates per user
  - `mod_customcert\service\issue_email_repository` — tracks email send state per issue
- New rendering infrastructure:
  - `mod_customcert\service\element_renderer` — interface for the v2 element rendering pipeline (PDF and HTML surfaces)
  - `mod_customcert\service\pdf_renderer` — `element_renderer` implementation for PDF output
  - `mod_customcert\service\html_renderer` — `element_renderer` implementation for the designer preview

#### Other additions
- `mod_customcert\template::from_record(\stdClass $record)` is now the **preferred** entry point for instantiating a template from a database record; `mod_customcert\template::create(string $name, int $contextid)` is the preferred entry point for creating a new template. The constructor signature has changed to accept `int $id, string $name, int $contextid` individually (previously it accepted a raw `\stdClass $record`) — new code should use `from_record()` rather than calling `new template(...)` directly, though the constructor remains functional.
- `mod_customcert\element\legacy_element_adapter` — internal compatibility adapter used by the factory to expose legacy elements extending `mod_customcert\element` through the new element interfaces. Plugin authors should migrate elements to the new interfaces directly rather than depending on the adapter.
- Static utility methods on `certificate` have been moved to dedicated service/repository classes:
  - `certificate::get_issues()` / `certificate::get_number_of_issues()` / `certificate::get_conditional_issues_sql()` → `issue_repository`
  - `certificate::get_number_of_certificates_for_user()` / `certificate::get_certificates_for_user()` → `certificate_repository`
  - `certificate::get_fonts()` / `certificate::get_font_sizes()` → `element_helper`
  - `certificate::set_protection()` → `form_service`
  - `certificate::upload_files()` → `form_service`
- **Expanded automated tests** covering element API behaviour and upgrade/restore data migration paths.

### Changed

#### Element storage and data format
- `customcert_elements` no longer has `font`, `fontsize`, `colour`, or `width` columns; these values are now stored inside the JSON payload in `customcert_elements.data`.
- `customcert_elements.data` is now treated as a JSON payload:
  - Legacy scalar values are migrated into a JSON envelope (e.g. legacy `"Hello"` → `{ "value": "Hello" }`).
  - Upgrade and restore routines automatically migrate existing data.

Migration impact for element plugins:
- Do NOT read/write `$element->font`, `$element->fontsize`, `$element->colour`, `$element->width` from DB fields (they no longer exist).
- Use element getters (`get_font()`, `get_fontsize()`, `get_colour()`, `get_width()`) which now read from the JSON payload.
- Element plugins should return all relevant keys from `persistable_element_interface::normalise_data()`, including the standard visual keys (`font`, `fontsize`, `colour`, `width`) where applicable; they are no longer merged centrally.

Reserved JSON keys (visuals):
- The following keys are used by core as standard visual fields in `customcert_elements.data`:
  - `width`, `font`, `fontsize`, `colour`
- Element plugins that expose these visual attributes should return them from `normalise_data()` explicitly. Plugins should not use these keys for unrelated element-specific payload data.

#### Editing + persistence flow
- Element edit handlers normalise element-specific data into a JSON payload via `normalise_data()`; visual fields (`font`, `fontsize`, `colour`, `width`) must be returned explicitly by the element's own `normalise_data()` implementation so the stored payload is self-contained.
- Saving elements is hardened to avoid corrupting stored JSON payloads when a caller provides a JSON list/array.

#### Element construction + adapters
- Factory now prefers `constructable_element_interface::from_record(\stdClass $record)` when present on the element class.
- Legacy constructors remain supported as a fallback (`new Class($record)`).
- Factory returns `mod_customcert\element\element_interface`; legacy elements may be wrapped by `mod_customcert\element\legacy_element_adapter`.
  - Avoid `instanceof` checks against legacy concrete classes; prefer `$element->get_type()` and/or `$element->get_inner()`.

#### Element rendering signatures

> **Breaking change for third-party element plugins**: The `render()` and `render_html()` abstract methods on `mod_customcert\element` now have strict typed signatures and return types. Third-party element plugins that override these methods with the old untyped signatures will fail at class-load time with a PHP fatal error, even before the adapter layer can help. Plugins must update their method signatures before upgrading to 5.2. See the before/after signatures below.

- Element render methods are now typed and accept an optional renderer.

Before (legacy):

    public function render($pdf, $preview, $user) { /* ... */ }
    public function render_html() { /* ... */ }

After (5.2+):

    use mod_customcert\service\element_renderer;

    public function render(
        pdf $pdf,
        bool $preview,
        \stdClass $user,
        ?element_renderer $renderer = null
    ): void {}

    public function render_html(?element_renderer $renderer = null): string {}

- `element_helper::render_content()` remains unchanged for backward compatibility, but element plugins must update their method signatures as shown above.

#### Restore / backup compatibility
- Restoring backups created on older versions is supported: legacy `font`/`fontsize`/`colour`/`width` values are merged into the JSON payload during restore.
- Backups created on 5.2+ store visual fields inside JSON and are not guaranteed to restore correctly on older plugin versions (no backwards compatibility guarantee).

### Fixed
- Improved robustness of upgrade/restore migrations:
  - Better handling of missing/partial legacy fields.
  - Safer normalisation of element payloads during restore.
  - Legacy border elements that stored thickness as a scalar `data` value are now migrated so width is preserved correctly.
- `certificate_issue_service::generate_code()` now throws a `moodle_exception` after 10 failed attempts to generate a unique code, preventing an infinite loop in systems with a very large number of issued certificates.
- Web service hardening:
  - `external::save_element()` ignores JSON list payloads for `data` to prevent numeric-key pollution of stored element JSON.

### Security

- Fixed authorization bypass (CVE-2026-30884, CWE-639) where a user with mod/customcert:manage in any
  single course could read and overwrite certificate elements belonging to other courses by supplying
  an arbitrary elementid. Element ownership is now validated against the authorised context/template
  before any read or write operation is performed.

### Deprecated

#### Template methods
The following `template` methods are now shims that emit developer debugging and delegate to service classes. Third-party developers should swap legacy calls for the service methods; the shims will be removed in a future release.
- `template::save()` → `template_service::update()`
- `template::add_page()` / `template::save_page()` → `template_service::add_page()` / `template_service::save_pages()`
- `template::delete()` / `template::delete_page()` / `template::delete_element()` → `template_service::delete()` / `template_service::delete_page()` / `template_service::delete_element()`
- `template::copy_to_template()` → `template_service::copy_to_template()`
- `template::move_item()` → `template_service::move_item()` (service constants are available; raw strings remain supported)
- `template::generate_pdf()` → `pdf_generation_service::generate_pdf()`

#### Certificate methods
The following `certificate` methods are now shims that emit developer debugging and should be replaced with service calls:
- `certificate::issue_certificate()` / `certificate::generate_code()` → `certificate_issue_service::issue_certificate()` / `::generate_code()`
- `certificate::download_all_issues_for_instance()` / `certificate::download_all_for_site()` → `certificate_download_service` equivalents
- `certificate::get_course_time()` → `certificate_time_service::get_course_time()`
- `certificate::get_issues()` / `certificate::get_number_of_issues()` / `certificate::get_conditional_issues_sql()` → `issue_repository`
- `certificate::get_number_of_certificates_for_user()` / `certificate::get_certificates_for_user()` → `certificate_repository`
- `certificate::get_fonts()` / `certificate::get_font_sizes()` → `element_helper`
- `certificate::set_protection()` → `form_service`
- `certificate::upload_files()` → `form_service`

#### Element APIs
Legacy element APIs are still supported but deprecated as of 5.2:
- `element::render_form_elements()` → use `form_element_interface::build_form()` and `element_helper::render_common_form_elements()` for standard fields
- `element::definition_after_data()` → use `preparable_form_interface::prepare_form()`
- `element::validate_form_elements()` → use `validatable_element_interface::validate()`
- `element::save_form_elements()` / `element::save_unique_data()` → use `persistable_element_interface::normalise_data()`
- `element::after_restore()` → use `restorable_element_interface::after_restore_from_backup()`
- `element::copy_element()` → use `mod_customcert\service\element_repository::copy_element()`
- `element::delete()` → use `mod_customcert\service\element_repository::delete()` (elements should not delete themselves; deletion is handled by the repository/service layer)
- `\mod_customcert\element_factory::get_element_instance()` remains available as a deprecated backward-compatibility shim that emits a developer debugging notice. New code should use `mod_customcert\service\element_factory::build_with_defaults()->create_from_legacy_record()` or inject `mod_customcert\service\element_factory` directly.

#### Deprecation notes
- Deprecated APIs are expected to continue working during the 5.2 line for most common usage patterns, but **some edge cases may break** — see "Plugin developer migration risks" below. New development should use Element System v2 interfaces.
- New element plugins should not rely on `customcert_elements` legacy columns or legacy element hooks.

### Removed
- DB columns removed from `customcert_elements`:
  - `font`
  - `fontsize`
  - `colour`
  - `width`

- Element removed from the plugin distribution:
  - `daterange` — if you use this element, install it separately before upgrading:
    - Clone it from: https://github.com/mdjnelson/moodle-customcertelement_daterange

### Migration Guide (Third-party element developers)

#### Plugin developer migration risks

While deprecated APIs include shims for backward compatibility, the following changes may affect third-party element plugins:

1. **JSON data format** — The `data` column now stores a JSON object payload. Plugins that previously treated `get_data()` as a plain scalar string must be updated to work with JSON. Use `get_payload()` to decode, or `get_value()` for simple scalar elements.
2. **Save path changes** — Element saves (both from the edit form and via the web service) now route through `persistence_helper::to_json_data()` and `element_repository::save()`. Plugins with custom save-time side effects in `save_form_elements()` or `save_unique_data()` should verify that these are still called via the legacy adapter path.
3. **DB columns removed** — The `font`, `fontsize`, `colour`, and `width` columns no longer exist in `customcert_elements`. Any direct SQL queries referencing these columns will fail.
4. **`normalise_data()` scalar wrapping** — If a `persistable_element_interface` element's `normalise_data()` returns a plain string that is not already a JSON object, it will be wrapped as `{"value": "..."}` before storage. Plugins that return a raw scalar from `normalise_data()` and read it back directly via `get_data()` must switch to `get_value()`.

**Recommendation:** Test your element plugin against 5.2 on a test site before release. Implement the Element System v2 interfaces (Option A below) for the most robust forward compatibility.

#### Option A (Recommended): Adopt Element System v2
Update your element class to implement interfaces as needed:

    class element extends \mod_customcert\element implements
        \mod_customcert\element\form_element_interface,
        \mod_customcert\element\persistable_element_interface,
        \mod_customcert\element\validatable_element_interface,
        \mod_customcert\element\preparable_form_interface {

        public function build_form(\MoodleQuickForm $mform): void {
            // Add element-specific fields.
            $mform->addElement('text', 'myfield', get_string('myfield', 'customcertelement_myplugin'));
            $mform->setType('myfield', PARAM_TEXT);
            $mform->addHelpButton('myfield', 'myfield', 'customcertelement_myplugin');

            // Add standard fields (font, colour, position, width, refpoint, alignment).
            \mod_customcert\element_helper::render_common_form_elements($mform);
        }

        public function normalise_data(\stdClass $formdata): array {
            return [
                'value' => (string)($formdata->myfield ?? ''),
                // Return visual fields explicitly — they are not merged centrally.
                'font'     => (string)($formdata->font ?? ''),
                'fontsize' => (int)($formdata->fontsize ?? 0),
                'colour'   => (string)($formdata->colour ?? ''),
                'width'    => (int)($formdata->width ?? 0),
            ];
        }

        public function validate(array $data): array {
            $errors = [];
            if (empty($data['myfield'])) {
                $errors['myfield'] = get_string('required');
            }
            return $errors;
        }

        public function prepare_form(\MoodleQuickForm $mform): void {
            // Example: populate form values by reading from JSON.
            $payload = $this->get_payload();
            if (is_array($payload) && array_key_exists('value', $payload)) {
                $mform->getElement('myfield')->setValue((string)$payload['value']);
            }
        }
    }

#### Option B: Keep legacy APIs temporarily (not recommended for new plugins)
Existing element plugins may continue using legacy hooks in 5.2, but must be updated to tolerate JSON data and the removal of DB columns:
- Treat `get_data()` as JSON and read your values from the decoded payload (`get_payload()`).
- If you previously depended on DB columns for `font`/`fontsize`/`colour`/`width`, switch to getters (`get_font()`, `get_fontsize()`, `get_colour()`, `get_width()`).

#### Common pitfalls
- Do not assume `get_data()` returns a scalar string; it may be JSON with multiple keys.
- Do not overwrite the whole JSON payload with a single scalar; always normalise to an object payload via Element System v2 interfaces.
- Do not store standard visual fields (`font`/`fontsize`/`colour`/`width`) in custom keys; rely on the core merge behaviour.

## [5.0.2] - 2025-12-18

### Fixed

- Ensure certificates are only emailed to users who can view the certificate and have the 'mod/customcert:receiveissue'
  capability (#732).

## [5.0.1] - 2025-11-30

### Fixed

- Fixed emails not being sent for certificates on the site home page (#693).
- Added customisable filename options for certificates (#684).
- Added missing fields to backups (#705).
- Ensure certificates are issued and emailed only once when emailing is enabled (#671).
- Fixed "grade_item" not found error due to missing required file.
- Fixed language handling for certificates and emails (#717).
- Fixed issue with code dashes being stripped on verification page (#727).
- Allow teachers enrolled as students to receive certificates (#690).
- Standardization of spacing in HTML as in the plain text version of emails (#724).

### Changed

- Hard-coded URL in email is now stored as language string allowing it to be modified/moved (#724).

### Added

- Add setting that adds a button to return to course from the certificate page (#655).
- Added awarded on date to verification page (#566).
- Added webservice to retrieve a list of issued certificates (#644).

## [5.0.0] - 2025-06-07

### Fixed

- Updated subplugins.json file to match Moodle 5.0 format (#688).

## [4.4.6] - 2025-06-07

### Added

- Added choice for code format (#668).
- Added events for adding and deleting certificate issues (#675).

## [4.4.5] - 2025-05-25

### Fixed

- Fixed enrol start date not showing (#410).
- Pass the user's mailformat preference when emailing certificates (#665).

### Added

- Added index to the 'code' column in 'customcert_issues' table (#666).

## [4.4.4] - 2025-02-23

### Fixed

- A SQL error in the issue certificate task on MSSQL and Oracle (#646).
- Issuing a certificate when there are no issues yet (#659).
- Issue in rearrange JS with Modal not closing (#648).

### Added

- ISO 8601 date format (#638).

## [4.4.3] - 2024-10-12

### Fixed

- A SQL error in the issue certificate task on Oracle (#646).
- Verification error when expiry date element is present (#647).

### Added

- Only fetch teachers during the email process when necessary, reducing the number of SQL queries if they are not included (#531). 
- Filter users before process to speed up certificate task (#634).

## [4.4.2] - 2024-09-28

### Fixed

- Mobile app: Stop using deprecated module-description.
- Fixed auto-linking filters moving text element positions if reference point is center (#629).

### Changed

- Mobile app: Update Mobile template to Ionic 7.
- Mobile app: Remove Ionic 3 template.

### Added

- Optimise email certificate task by reducing database reads/writes and introducing
  configurable settings for task efficiency (#531).
- New element `expiry` which when used will display the expiry date on the list of issued certificates 
  and the verification pages.<br />
  Any Custom Certificates that are using the `date` element and selected the expiry dates will
  automatically be upgraded to use this new element (#499).

## [4.4.1] - 2024-05-28

### Fixed

- Major issue with the pop-up window not working in the reposition element page (#483).
- Non-editing teachers being able to see the download certificate link for a user which took them to a blank page (#620).

## [4.4.0] - 2024-05-28

### Added

- Added 'Save and Continue' option when editing image elements (#613).
  This means you can add an image to the filemanager, click 'Save and Continue'
  and then select it in the drop-down avoiding renavigating to the edit element page.
- Added monologo image (#568).

## [4.2.5] - 2024-04-23

### Fixed

- Fixed issue when restoring `date`, `daterange`, `gradeitemname` and `grade` that have been linked to a manual grade item (#582).
- Removed unnecessary set_context() call causing a PHP notice to be shown (#443).
- Ensure we use $CFG->emailfromvia setting (#471).

### Changed

- The downloaded issue report can now be ordered by date sanely (#602).

### Added

- Added ability to download all certificates in the report as a teacher and bulk download all site certificates as an administrator (#205).

## [4.2.4] - 2024-02-08

### Fixed

- Do not make index unique (#601).

## [4.2.3] - 2024-01-31

### Fixed

- Stopped PHP notice caused by the email certificate task (#443).
- Fixed undefined external_format_error in the mobile app (#565).
- Fixed being unable to reposition the course field element if it is empty (#579).
- Fixed incorrect name of mustache variable in email_certificate_html.mustache (#574).
- Fixed passing incorrect course module id value to \mod_customcert\output\email_certificate (#574).
- Delete the pages after deleting the elements otherwise it was breaking in element_deleted::create_from_element() (#571).
- Do not also show the 'My certificates' profile link when the user can not view the content of that page (#585).
- Added missing foreign key relationship for 'userid' in the 'customcert_issues' table (#537).
- Handle missing gradeitems as gracefully as possible, so we don't break the email task (#592).
- Fixed logic breaking the generation of the QR code URL (#545).
- Do not allow non-editing teachers to manage the certificate (#515).
- Ensure the 'verifyany' column length is valid on all sites (#597).
- Fixed events being triggered incorrectly (#570).

### Added

- Added the unique index 'userid-customcertid' to the 'customcert_issues' table (#537).
- Added events on the reposition element page (#599).

## [4.2.2] - 2023-06-08

### Fixed

- Fix TCPDF error when viewing an example PDF from the manage templates page (#558).
- Fix images not displaying on the reposition element page (#562).

### Added

- Added new events (#518).
  - An event for when an element is created.
  - An event for when an element is updated.
  - An event for when an element is deleted.
  - An event for when a page is created.
  - An event for when a page is updated.
  - An event for when a page is deleted.
  - An event for when a template is created. 
  - An event for when a template is updated.
  - An event for when a template is deleted.

## [4.2.1] - 2023-05-30

### Fixed

- Fix course settings error on single activity format (#544).
- Remove debugging message caused by the user field element listing the Skype field (#478).
- Fix deprecated usage of rendering primary buttons (#555).
- Fix usage of deprecated `cron_setup_user` function (#547).
- Fix broken webservice functions used by the mobile app.

## [4.0.3] - 2023-02-07

### Added

- You can now optionally force the language of a certificate (#532).

## [4.0.2] - 2023-01-26

### Fixed

- Fix problem repositioning elements (#500).

## [4.0.1] - 2022-11-07

### Fixed

- Fix problem repositioning elements (#513).
- Fixed title and description shown twice (#521).

## [3.11.2] - 2022-11-07

### Fixed
- Fix places not using the multi-language filter (#433).
- Fix user IDs in the issue table not being mapped during restore (#449).
- Fix emails displaying HTML entities encoded (#457).
- Fix error message when we have custom profile fields (#465).
- Respect multiple languages in manage template page title (#467).
- Add field exist check for alignment field in upgrade script to prevent upgrades from dying.
- Stop using deprecated pipe coreToLocaleString.

### Changed
- User breadcrumbs on the my_certificates.php page changes when a course is specified (#469).

### Added
- You can now choose the course short or full name to display (#415).
- You can now select the alignment for all text elements (#121).
- Ability to add a relative date (#389).

## [3.11.1] - 2021-06-13

### Fixed
- Usage of deprecated functions (#423)

## [3.10.1] - 2021-06-13

### Added
- Usage of github actions (#407).
- The ability to show the description on the course page (#406).
- The ability to choose how to deliver the certificate (#401).

### Fixed
- Managers are now able to download their students' certificates (#412).
- Users being able to view the certificate before the required time set (#403).
- Fixed the issue with displaying PDF when debugging is ON (#420).
- Using incorrect context when sending emails (#402).
- Use `cron_setup_user` when sending emails (#414).

## [3.8.5] - 2020-11-26

### Added

- Added ability to select outcomes in the Grade element (#329).
- The Grade Item Name element now works with all grade items, whereas before it was just activities (#346).
- Added enrolment start and end dates to the date element (#328).
- Added username to userfield form element (#390).

### Changed

- Removed unnecessary and confusing 'exampledata' string.
- Do not email those who can manage the certificate (#376).
- Do not force the PDF to be downloaded, instead send the file inline to the browser (#153).
- Updated the 'emailstudents_help', 'emailteachers_help' and 'emailothers_help' strings to warn users about prematurely emailing the certificate (#276).
- Do not email out certificates that contain no elements (#276).

### Fixed

- Certificates now get marked as viewed via the mobile app (#342).
- Custom fields not displaying properly (#359).
- Fix repositioning elements page when resizing the browser (#343).
- Prevent error when duplicate issues exist when using the code element (#363).
- Implemented get_objectid_mapping for the course_module_viewed.php event to avoid warning (#374).
- Fixed exception being thrown when loading a template that has an image element but no image selected (#369).
- Fixed issue with PDF being generated without a name (#333).

## [3.8.4] - 2020-03-12

### Added

- Added extra Behat steps for new elements (#309).

### Changed

- When copying a site template the site images are also copied to the course context and then those copied images are used.
  Before, the elements would simply point to the site images. However, this meant when performing a backup/restore the
  images were not stored in the backup file (#298).

### Fixed

- Fixed the displaying of names of a custom user field (#326).
- Do not allow '0' as a value for width or height in QR code (#321).

## [3.8.3] - 2020-03-09

### Fixed

- Fixed foreign key violation (#331).

## [3.8.2] - 2019-12-16

### Added

- Added subplugins.json file (#312).
- Re-added 'code' column to user report (#264).
- Add 'userfullname' variable for email subject (#316).

### Fixed

- Do not fail if multiple certificate issues (#304) and (#295).

## [3.7.1] - 2019-06-17

### Added

- Added new custom course field element (#274).
- Added ability to specify the current date for date related elements (#289).

### Changed

- String improvements for the 'Date range' element.

### Fixed

- Use negative numbers for constants in the 'Date range' element. The reason being that we may have a module
  that has an id matching one of these positive values. Sites which are using the 'Date range' element (sites
  which are **not** using this element do **not** have to do anything) will need to re-edit each element, select
  the date item again and save. An upgrade step was not created because it is impossible to tell if the site does
  actually want the constant or if they actually want the date for the module.

## [3.6.2] - 2019-05-28

### Changed

- Always send emails from the 'noreplyuser' (#165).

### Added

- Added QR code element (#146).
- Added Date range element (#185).
- Added the number of certificates issued above the report (#266).
- Added new capability to control who can be issued a certificate (#270).

### Fixed

- Failures when running unit tests for multiple activities (#282).
- Check that a certificate is valid before downloading on 'My certificates' page (#269).

## [3.6.1] - 2018-12-31

### Changed

- Make it clear what element values are just an example when previewing the PDF (#144).

### Fixed

- Missing implementation for privacy provider (#260).
- Use course module context when calling format_string/text (#200).
- Exception being thrown when adding the 'teachername' element to site template (#261).

## [3.5.5] - 2018-12-20
### Added

- GDPR: Add support for removal of users from a context (see MDL-62560) (#252).
- Images can be made transparent (#186).
- Set default values of activity instance settings (#180).
- Allow element plugins to control if they can be added to a certificate (#225).
- Allow element plugins to have their own admin settings (#213).
- Added plaintext language variants for email bodies (#231).
- Added possibility to selectively disable activity instance settings (#179).

### Changed

- Allow verification of deleted users (#159).
- The 'element' field in the 'customcert_elements' table has been changed from a Text field to varchar(255) (#241).
- The 'Completion date' option in the 'date' element is only displayed when completion is enabled (#160).
- Instead of assuming 2 decimal points for percentages, we now make use of the decimal value setting, which the
  function `grade_format_gradevalue` does by default if no decimal value is passed.

### Fixed

- Issue with scales not displaying correctly (#242).
- The report now respects the setting 'Show user identity' (#224).
- Removed incorrect course reset logic (#223).
- Description strings referring to the wrong setting (#254).

## [3.5.4] - 2018-07-13
### Fixed

- Use custom fonts if present (#211).
- Fix broken SQL on Oracle in the email certificate task (#187).
- Fixed exception when clicking 'Add page' when template has not been saved (#154).
- Only email teachers who are enrolled within the course (#176).
- Only display teachers who are enrolled within the course in the dropdown (#171).

### Changed

- Multiple UX improvements to both the browser and mobile views (#207).
  - One big change here is combining the report and activity view page into one.
- Allow short dates with leading zeros (#210).

## [3.5.3] - 2018-06-26
### Fixed

- Respect filters in the 'My certificates' and 'Verify certificate' pages (#197).
- Fixed reference to 'mod/certificate' capability.

### Changed

- Multiple UX improvements to both the browser and mobile views (#203).

## [3.5.2] - 2018-06-07
### Fixed

- Hotfix to prevent misalignment of 'text' elements after last release (#196).

## [3.5.1] - 2018-06-06
### Added
- Mobile app support (#70).
```
    This allows students to view the activity and download
    their certificate. It also allows teachers to view the
    list of issued certificates, with the ability to revoke
    any.

    This is for the soon-to-be released Moodle Mobile v3.5.0
    (not to be confused with your Moodle site version) and
    will not work on Mobile versions earlier than this.

    If you are running a Moodle site on version 3.4 or below
    you will need to install the local_mobile plugin in order
    for this to work.

    If you are running a Moodle site on version 3.0 or below
    then you will need to upgrade.
```
- More font sizes (#148).
- Added new download icon.
```
    This was done because the core 'import' icon was mapped
    to the Font Awesome icon 'fa-level-up' which did not look
    appropriate. So, a new icon was added and that was mapped
    to the 'fa-download' icon.
```
### Fixed
- No longer display the 'action' column and user picture URL when downloading the user report (#192).
- Elements no longer ignore filters (#170).

## [3.4.1] - 2018-05-17
### Added
- GDPR Compliance (#189).

### Fixed
- Race condition on certificate issues in scheduled task (#173).
- Ensure we backup the 'verifyany' setting (#169).
- Fixed encoding content links used by restore (#166).

## [3.3.9] - 2017-11-13
### Added
- Added capability ```mod/customcert:verifyallcertificates``` that provides a user with the ability to verify any certificate
  on the site by simply visiting the ```mod/customcert/verify_certificate.php``` page, rather than having to go to the
  verification link for each certificate.
- Added site setting ```customcert/verifyallcertificates``` which when enabled allows any person (including users not logged in)
  to be able to verify any certificate on the site, rather than having to go to the verification link for each certificate.
  However, this only applies to certificates where ```Allow anyone to verify a certificate``` has been set to ```Yes``` in the
  certificate settings.
- You can now display the grade and date of all grade items, not just the course and course activities.
- Text has been added above the ```My certificates``` list to explain that it contains certificates that have been issued to
  avoid confusion as to why certificates may not be appearing.

### Changed
- The course full name is now used in emails.

### Fixed
- Added missing string used in course reset.

## [3.3.8] - 2017-09-04
### Added
- New digital signature element (uses existing functionality in the TCPDF library).
- Ability to duplicate site templates via the manage templates page.
- Ability to delete issued certificates for individual users on the course report page.

### Changed
- Removed usage of magic getter and abuse of ```$this->element```. The variable ```$this->element``` will still be
  accessible by any third-party element plugins, though this is discouraged and the appropriate ```get_xxx()```
  method should be used instead. Using ```$this->element``` in ```definition_after_data()``` will no longer work.
  Please explicitly set the value of any custom fields you have in the form.

### Fixed
- Added missing ```confirm_sesskey()``` checks.
- Minor bug fixes.

## [3.3.7] - 2017-08-11
### Added
- Added much needed Behat test coverage.

### Changed
- Minor language string changes.
- Made changes to the UI when editing a certificate.
  - Moved the 'Add element' submit button below the list of elements.
  - Added icon next to the 'Delete page' link.
  - Changed the 'Add page' button to a link, added an icon and moved it's location to the right.
  - Do not make all submit buttons primary. MDL-59740 needs to be applied to your Moodle install in order to notice the change.

### Fixed
- Issue where the date an activity was graded was not displaying at all.

## [3.3.6] - 2017-08-05
### Changed
- Renamed the column 'size' in the table 'customcert_elements' to 'fontsize' due to 'size' being a reserved word in Oracle.

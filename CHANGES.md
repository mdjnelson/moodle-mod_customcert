# Changelog

All notable changes to this project will be documented in this file.

## [3.1.15] - 2017-08-11
### Changed
- Minor language string changes.
- Made changes to the UI when editing a certificate.
  - Moved the 'Add element' submit button below the list of elements.
  - Added icon next to the 'Delete page' link.
  - Changed the 'Add page' button to a link, added an icon and moved it's location to the right.
  - Do not make all submit buttons primary. MDL-59740 needs to be applied to your Moodle install in order to notice the change.
- Fixed issue where the date an activity was graded was not displaying at all.
- Added much needed Behat test coverage.

## [3.1.14] - 2017-08-05
### Changed
- Renamed the column 'size' in the table 'customcert_elements' to 'fontsize' due to 'size' being a reserved word in Oracle.

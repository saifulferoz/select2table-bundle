# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - Unreleased

`2.0.0` could not be installed at all: its container extension alias did not match
the name Symfony derives from the bundle class, so `composer require` aborted during
`cache:clear`. Fixing that required renaming the bundle class and its configuration
key, which is why this release contains breaking changes.

### Breaking changes

- Renamed bundle class `SaifulFerozSelect2TableBundle` to `Select2TableBundle`.
- Renamed configuration key `saifulferoz_select2_table` to `select2_table`
  (file: `config/packages/select2_table.yaml`).
- Renamed Twig namespace `@SaifulFerozSelect2Table` to `@Select2Table`; the form
  theme is now registered as `@Select2Table/form/fields.html.twig`.
- Renamed container parameter `saifulferoz_select2_table.config` to `select2_table.config`.
- Dropped the service alias `Feroz\Select2TableBundle\Service\AutocompleteService`,
  which pointed at a class that never existed.
- Removed the legacy `Resources/views/Form/fields.html.twig`. It used `{% extends %}`
  on a form theme and therefore rendered nothing; `templates/form/fields.html.twig`
  has always been the file actually shipped.
- Narrowed the Symfony 7 constraint from `^7.0` to `^7.4`. Symfony 7.0–7.3 are
  affected by unresolved security advisories and cannot be installed.

See the *Upgrading* section of the README for the migration steps.

### Fixed

- **Bundle could not be installed.** The extension alias now derives automatically
  from the bundle name, because the bundle extends `AbstractBundle`.
- **Symfony 8 support.** Service configuration moved from XML to PHP; the XML
  configuration format was removed entirely in Symfony 8.
- **Symfony 8 support.** Replaced `Request::get()`, removed in Symfony 8, with
  explicit `Request::$query` access. This also clears a deprecation on Symfony 7.4.
- **Non-deterministic pagination.** Paginated queries had no `ORDER BY`, so rows
  could be repeated or skipped across pages while scrolling.
- **Inconsistent result counts.** A `callback` was applied separately to the count
  and data queries, letting the `more` flag disagree with the returned rows.
- **CI never ran.** The workflow lived in `.ci/` instead of `.github/workflows/`,
  and `SYMFONY_REQUIRE` was a no-op because `symfony/flex` was not installed.
- Reserved words such as `order` now work as table or column names, because
  identifiers are quoted via `Connection::quoteIdentifier()`.
- `property` and `text_property` no longer disagree: the label falls back to the
  first searched column when only `property` is set.

### Security

- Internal schema details (`table_name`, `primary_key`, `text_property`, `order_by`)
  are no longer published to the form view, where they could surface in rendered
  HTML or a profiler dump.
- The `html` column is now omitted from autocomplete responses unless the field sets
  `render_html: true`, so fields that do not opt in never receive the raw value.
  `render_html` remains an opt-in XSS vector by design and is documented as such.

### Added

- `order_by` option (and global default) to control result ordering.
- Test coverage for container compilation, the extension alias, form theme rendering,
  pagination determinism, callback application and the `render_html` gate.

## [2.0.0] - 2026-08-24

- Initial modernized release. **Unusable:** see the note above.

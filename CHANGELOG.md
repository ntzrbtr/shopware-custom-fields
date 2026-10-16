# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1]

### Fixed

- Removed `#[AutoconfigureTag]` from `CustomFieldSetProviderInterface`: Symfony does not evaluate it for interfaces
  outside a project's scanned resources, so providers were never tagged; the README now shows how to assign the tag
  via `_instanceof`
- Corrected the description of `CustomFieldSetProviderInterface` in the notes for 1.0.0

## [1.1.0]

### Added

- `CustomField` options `allowCustomerWrite` (default `true`), `allowCartExpose` (default `false`),
  `storeApiAware` (default `true`) and `includeInSearch` (default `false`)

## [1.0.0]

### Added

- `CustomFieldSet` and `CustomField` DTOs to define custom field sets in PHP
- `CustomFieldSetProviderInterface` with tag name constant (`netzarbeiter.shopware.custom_fields.provider`)
- `CustomFieldSetInstaller` to create, update and remove custom field sets including their entity relations;
  fields removed from a set are deleted on update

[Unreleased]: https://github.com/ntzrbtr/shopware-custom-fields/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/ntzrbtr/shopware-custom-fields/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/ntzrbtr/shopware-custom-fields/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ntzrbtr/shopware-custom-fields/releases/tag/v1.0.0

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0]

### Added

- `CustomFieldSet` and `CustomField` DTOs to define custom field sets in PHP
- `CustomFieldSetProviderInterface` (autoconfigured with tag `netzarbeiter.shopware.custom_fields.provider`)
- `CustomFieldSetInstaller` to create, update and remove custom field sets including their entity relations;
  fields removed from a set are deleted on update

[Unreleased]: https://github.com/ntzrbtr/shopware-custom-fields/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ntzrbtr/shopware-custom-fields/releases/tag/v1.0.0

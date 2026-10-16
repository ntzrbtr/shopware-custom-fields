# ntzrbtr/shopware-custom-fields

[![Tests](https://github.com/ntzrbtr/shopware-custom-fields/actions/workflows/tests.yml/badge.svg)](https://github.com/ntzrbtr/shopware-custom-fields/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/ntzrbtr/shopware-custom-fields)](https://packagist.org/packages/ntzrbtr/shopware-custom-fields)
[![License](https://img.shields.io/github/license/ntzrbtr/shopware-custom-fields)](LICENSE)

Define custom field sets for Shopware 6 in PHP and install, update and uninstall them from your plugin's lifecycle.

- Custom field sets are identified by their name; installing an existing set updates it in place (ids of existing
  fields are kept, so stored values survive).
- Fields that are no longer part of a set are removed on update.
- Relations to entities are synchronized (added and removed) as well.
- Labels can be given as a string (used for `de-DE` and `en-GB`) or as an array with both locales.
- Field positions follow the order of the fields in the set.

## Installation

```bash
composer require ntzrbtr/shopware-custom-fields
```

## Usage

### Define a custom field set

Create a provider per custom field set:

```php
use Netzarbeiter\Shopware\CustomFields\CustomField;
use Netzarbeiter\Shopware\CustomFields\CustomFieldSet;
use Netzarbeiter\Shopware\CustomFields\Provider\CustomFieldSetProviderInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class OrderDataSetProvider implements CustomFieldSetProviderInterface
{
    public function getCustomFieldSet(): CustomFieldSet
    {
        return new CustomFieldSet(
            name: 'order_data',
            entities: [OrderDefinition::ENTITY_NAME],
            label: [
                'de-DE' => 'Zusätzliche Bestelldaten',
                'en-GB' => 'Additional order data',
            ],
            customFields: [
                new CustomField(
                    name: 'order_data_exported',
                    type: CustomFieldTypes::CHECKBOX,
                    label: [
                        'de-DE' => 'Exportiert?',
                        'en-GB' => 'Exported?',
                    ]
                ),
                new CustomField(
                    name: 'order_data_note',
                    type: CustomFieldTypes::TEXT,
                    label: 'Note',
                    config: [
                        'componentName' => 'sw-textarea-field',
                    ],
                ),
            ]
        );
    }
}
```

The `config` of a field is merged recursively into the defaults (`componentName: sw-field`,
`customFieldType: text`, the label), so you can override or add any custom field config Shopware supports.

### Register the services

The installer is not registered automatically; add it to your plugin's `services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Netzarbeiter\Shopware\CustomFields\CustomFieldSetInstaller: ~

    Vendor\MyPlugin\CustomField\Provider\:
        resource: '../../CustomField/Provider'
```

With autoconfiguration enabled, every class implementing `CustomFieldSetProviderInterface` is tagged with
`netzarbeiter.shopware.custom_fields.provider` and injected into the installer.

### Install from the plugin lifecycle

While a plugin is being installed, its services are not yet part of the container, so create the installer
manually in that case and pass the providers explicitly:

```php
use Netzarbeiter\Shopware\CustomFields\CustomFieldSetInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class MyPlugin extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getCustomFieldSetInstaller()->installAll($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getCustomFieldSetInstaller()->installAll($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getCustomFieldSetInstaller()->uninstallAll($uninstallContext->getContext());
    }

    private function getCustomFieldSetInstaller(): CustomFieldSetInstaller
    {
        if ($this->container->has(CustomFieldSetInstaller::class)) {
            return $this->container->get(CustomFieldSetInstaller::class);
        }

        return new CustomFieldSetInstaller(
            $this->container->get('custom_field.repository'),
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository'),
            [
                new OrderDataSetProvider(),
            ]
        );
    }
}
```

Single sets can be handled with `install()` and `uninstall()`; `getCustomFieldSets()` returns the sets of all
providers, e.g. to build mappings for an import.

## Requirements

- PHP 8.4 or newer
- Shopware 6.7

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Releasing

1. Move the entries under `[Unreleased]` in `CHANGELOG.md` to a new version section.
2. Commit, then tag and push: `git tag v1.2.3 && git push origin v1.2.3`
3. The release workflow creates the GitHub release with the changelog entry as notes;
   Packagist picks up the new tag automatically.

## License

MIT, see [LICENSE](LICENSE).

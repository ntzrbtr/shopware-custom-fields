<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields\Provider;

use Netzarbeiter\Shopware\CustomFields\CustomFieldSet;

/**
 * Interface for custom field providers
 */
interface CustomFieldSetProviderInterface
{
    /**
     * Tag the installer collects its providers by
     *
     * Note that projects need to assign it themselves (e.g. via `_instanceof`), as Symfony only evaluates
     * autoconfiguration attributes on interfaces that are part of a project's scanned resources.
     */
    public const string TAG = 'netzarbeiter.shopware.custom_fields.provider';

    public function getCustomFieldSet(): CustomFieldSet;
}

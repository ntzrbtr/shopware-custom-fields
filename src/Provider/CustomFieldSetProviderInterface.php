<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields\Provider;

use Netzarbeiter\Shopware\CustomFields\CustomFieldSet;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for custom field providers
 */
#[AutoconfigureTag(CustomFieldSetProviderInterface::TAG)]
interface CustomFieldSetProviderInterface
{
    public const string TAG = 'netzarbeiter.shopware.custom_fields.provider';

    public function getCustomFieldSet(): CustomFieldSet;
}

<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * DTO for custom field set
 */
#[Exclude]
readonly class CustomFieldSet
{
    public string $id;

    /**
     * @param array<string> $entities
     * @param array{'de-DE': string, 'en-GB': string}|string $label
     * @param array<CustomField> $customFields
     */
    public function __construct(
        public string $name,
        public array $entities,
        public array|string $label,
        public array $customFields
    ) {
        $this->id = Uuid::randomHex();
    }

    /**
     * @return array{id: string, name: string, config: array<string, mixed>, customFields: array<array{id: string, name: string, type: string, allowCustomerWrite: bool, allowCartExpose: bool, storeApiAware: bool, includeInSearch: bool, config: array<mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'config' => [
                'label' => is_string($this->label)
                    ? [
                        'de-DE' => $this->label,
                        'en-GB' => $this->label,
                    ]
                    : $this->label,
            ],
            'customFields' => array_map(
                static function (CustomField $customField, int $position): array {
                    $data = $customField->toArray();
                    $data['config']['customFieldPosition'] = $position + 1;

                    return $data;
                },
                $this->customFields,
                array_keys($this->customFields),
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * DTO for custom field
 */
#[Exclude]
readonly class CustomField
{
    public string $id;

    /**
     * @param array{'de-DE': string, 'en-GB': string}|string $label
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $name,
        public string $type,
        public array|string $label,
        public array $config = [],
        public bool $allowCustomerWrite = true,
        public bool $allowCartExpose = false,
        public bool $storeApiAware = true,
        public bool $includeInSearch = false
    ) {
        $this->id = Uuid::randomHex();

        // Check if label is correct.
        if (is_array($this->label)) {
            foreach (['de-DE', 'en-GB'] as $key) {
                if (!array_key_exists($key, $this->label)) {
                    throw new \InvalidArgumentException('Invalid label definition (not all language keys are set)');
                }
            }
        }
    }

    /**
     * @return array{id: string, name: string, type: string, allowCustomerWrite: bool, allowCartExpose: bool, storeApiAware: bool, includeInSearch: bool, config: array<mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'allowCustomerWrite' => $this->allowCustomerWrite,
            'allowCartExpose' => $this->allowCartExpose,
            'storeApiAware' => $this->storeApiAware,
            'includeInSearch' => $this->includeInSearch,
            'config' => array_replace_recursive(
                [
                    'componentName' => 'sw-field',
                    'customFieldType' => 'text',
                    'label' => is_string($this->label)
                        ? [
                            'de-DE' => $this->label,
                            'en-GB' => $this->label,
                        ]
                        : $this->label,
                ],
                $this->config,
            ),
        ];
    }
}

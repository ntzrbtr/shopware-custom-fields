<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields\Tests;

use Netzarbeiter\Shopware\CustomFields\CustomField;
use Netzarbeiter\Shopware\CustomFields\CustomFieldSet;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldSetTest extends TestCase
{
    public function testToArrayExpandsStringLabelsAndSetsPositions(): void
    {
        $fieldSet = new CustomFieldSet(
            'test_set',
            ['product'],
            'Test Set',
            [
                new CustomField('field_a', CustomFieldTypes::TEXT, 'Field A'),
                new CustomField(
                    'field_b',
                    CustomFieldTypes::TEXT,
                    ['de-DE' => 'Feld B', 'en-GB' => 'Field B'],
                    ['componentName' => 'sw-textarea-field']
                ),
            ]
        );

        $data = $fieldSet->toArray();

        self::assertSame($fieldSet->id, $data['id']);
        self::assertSame(['de-DE' => 'Test Set', 'en-GB' => 'Test Set'], $data['config']['label']);
        self::assertSame(
            [
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'label' => ['de-DE' => 'Field A', 'en-GB' => 'Field A'],
                'customFieldPosition' => 1,
            ],
            $data['customFields'][0]['config']
        );
        self::assertSame('sw-textarea-field', $data['customFields'][1]['config']['componentName']);
        self::assertSame(['de-DE' => 'Feld B', 'en-GB' => 'Field B'], $data['customFields'][1]['config']['label']);
        self::assertSame(2, $data['customFields'][1]['config']['customFieldPosition']);
    }

    public function testCustomFieldFlagsHaveDefaultsAndCanBeOverridden(): void
    {
        $default = new CustomField('field_a', CustomFieldTypes::TEXT, 'Field A')->toArray();

        self::assertTrue($default['allowCustomerWrite']);
        self::assertFalse($default['allowCartExpose']);
        self::assertTrue($default['storeApiAware']);
        self::assertFalse($default['includeInSearch']);

        $custom = new CustomField(
            'field_b',
            CustomFieldTypes::TEXT,
            'Field B',
            allowCustomerWrite: false,
            allowCartExpose: true,
            storeApiAware: false,
            includeInSearch: true,
        )->toArray();

        self::assertFalse($custom['allowCustomerWrite']);
        self::assertTrue($custom['allowCartExpose']);
        self::assertFalse($custom['storeApiAware']);
        self::assertTrue($custom['includeInSearch']);
    }

    public function testCustomFieldRejectsIncompleteLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // @phpstan-ignore argument.type (Testing invalid input)
        new CustomField('field', CustomFieldTypes::TEXT, ['de-DE' => 'Feld']);
    }
}

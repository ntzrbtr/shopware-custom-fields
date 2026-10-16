<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields\Tests;

use Netzarbeiter\Shopware\CustomFields\CustomField;
use Netzarbeiter\Shopware\CustomFields\CustomFieldSet;
use Netzarbeiter\Shopware\CustomFields\CustomFieldSetInstaller;
use Netzarbeiter\Shopware\CustomFields\Provider\CustomFieldSetProviderInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;

// Not every test interacts with all repositories.
#[AllowMockObjectsWithoutExpectations]
class CustomFieldSetInstallerTest extends TestCase
{
    /**
     * @var MockObject&EntityRepository<CustomFieldCollection>
     */
    private MockObject&EntityRepository $customFieldRepository;

    /**
     * @var MockObject&EntityRepository<CustomFieldSetCollection>
     */
    private MockObject&EntityRepository $customFieldSetRepository;

    /**
     * @var MockObject&EntityRepository<CustomFieldSetRelationCollection>
     */
    private MockObject&EntityRepository $customFieldSetRelationRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRelationRepository = $this->createMock(EntityRepository::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetCustomFieldSetsReturnsSetsFromProviders(): void
    {
        $fieldSet = new CustomFieldSet('test_set', ['product'], 'Test Set', []);

        $sets = iterator_to_array($this->createInstaller([$this->createProvider($fieldSet)])->getCustomFieldSets());

        self::assertSame([$fieldSet], $sets);
    }

    public function testInstallCreatesNewFieldSet(): void
    {
        $fieldSet = new CustomFieldSet(
            'test_set',
            ['product'],
            'Test Set',
            [new CustomField('test_field', CustomFieldTypes::TEXT, 'Test Field')]
        );

        $this->customFieldSetRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult('custom_field_set', new CustomFieldSetCollection()));

        $this->customFieldSetRepository->expects($this->once())
            ->method('upsert')
            ->with(
                self::callback(
                    static fn (array $data): bool => count($data) === 1
                        && $data[0]['id'] === $fieldSet->id
                        && $data[0]['name'] === 'test_set'
                        && $data[0]['customFields'][0]['name'] === 'test_field'
                ),
                $this->context
            );

        $this->customFieldRepository->expects($this->never())->method('delete');

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->createSearchResult('custom_field_set_relation', new CustomFieldSetRelationCollection())
            );

        $this->customFieldSetRelationRepository->expects($this->never())->method('delete');

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('upsert')
            ->with(
                self::callback(
                    static fn (array $data): bool => count($data) === 1
                        && $data[0]['customFieldSetId'] === $fieldSet->id
                        && $data[0]['entityName'] === 'product'
                ),
                $this->context
            );

        $this->createInstaller()->install($fieldSet, $this->context);
    }

    public function testInstallUpdatesExistingFieldSet(): void
    {
        $fieldSetId = Uuid::randomHex();
        $existingFieldId = Uuid::randomHex();
        $obsoleteFieldId = Uuid::randomHex();

        $existingField = new CustomFieldEntity();
        $existingField->setId($existingFieldId);
        $existingField->setName('existing_field');

        $obsoleteField = new CustomFieldEntity();
        $obsoleteField->setId($obsoleteFieldId);
        $obsoleteField->setName('obsolete_field');

        $existingFieldSet = new CustomFieldSetEntity();
        $existingFieldSet->setId($fieldSetId);
        $existingFieldSet->setName('test_set');
        $existingFieldSet->setCustomFields(new CustomFieldCollection([$existingField, $obsoleteField]));

        $fieldSet = new CustomFieldSet(
            'test_set',
            ['product'],
            'Test Set Updated',
            [
                new CustomField('existing_field', CustomFieldTypes::TEXT, 'Existing Field'),
                new CustomField('new_field', CustomFieldTypes::TEXT, 'New Field'),
            ]
        );

        $this->customFieldSetRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->createSearchResult('custom_field_set', new CustomFieldSetCollection([$existingFieldSet]))
            );

        // Existing ids are kept, new fields get a new id.
        $this->customFieldSetRepository->expects($this->once())
            ->method('upsert')
            ->with(
                self::callback(
                    static fn (array $data): bool => $data[0]['id'] === $fieldSetId
                        && $data[0]['customFields'][0]['id'] === $existingFieldId
                        && $data[0]['customFields'][1]['name'] === 'new_field'
                        && $data[0]['customFields'][1]['id'] !== $existingFieldId
                ),
                $this->context
            );

        // Fields no longer in the set are searched for and deleted.
        $this->customFieldRepository->expects($this->once())
            ->method('searchIds')
            ->with(
                self::callback(static function (Criteria $criteria) use ($fieldSetId): bool {
                    $notFilterFound = false;
                    $fieldSetFilterFound = false;

                    foreach ($criteria->getFilters() as $filter) {
                        if ($filter instanceof NotFilter) {
                            $innerFilter = $filter->getQueries()[0];
                            if (
                                $innerFilter instanceof EqualsAnyFilter
                                && $innerFilter->getValue() === ['existing_field', 'new_field']
                            ) {
                                $notFilterFound = true;
                            }
                        }

                        if (
                            $filter instanceof EqualsFilter
                            && $filter->getField() === 'customFieldSetId'
                            && $filter->getValue() === $fieldSetId
                        ) {
                            $fieldSetFilterFound = true;
                        }
                    }

                    return $notFilterFound && $fieldSetFilterFound;
                }),
                $this->context
            )
            ->willReturn(
                new IdSearchResult(
                    1,
                    [$obsoleteFieldId => ['primaryKey' => $obsoleteFieldId, 'data' => []]],
                    new Criteria(),
                    $this->context
                )
            );

        $this->customFieldRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => $obsoleteFieldId]], $this->context);

        // Relations to entities no longer configured are deleted, the others are upserted.
        $relation = new CustomFieldSetRelationEntity();
        $relation->setId(Uuid::randomHex());
        $relation->setEntityName('old_entity');
        $relation->setCustomFieldSetId($fieldSetId);

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->createSearchResult(
                    'custom_field_set_relation',
                    new CustomFieldSetRelationCollection([$relation])
                )
            );

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => $relation->getId()]], $this->context);

        $this->customFieldSetRelationRepository->expects($this->once())
            ->method('upsert')
            ->with(
                self::callback(
                    static fn (array $data): bool => count($data) === 1 && $data[0]['entityName'] === 'product'
                ),
                $this->context
            );

        $this->createInstaller()->install($fieldSet, $this->context);
    }

    public function testUninstallDeletesFieldSet(): void
    {
        $this->customFieldSetRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(1, ['id1' => ['primaryKey' => 'id1', 'data' => []]], new Criteria(), $this->context));

        $this->customFieldSetRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'id1']], $this->context);

        $this->createInstaller()->uninstall(new CustomFieldSet('test_set', [], 'Test Set', []), $this->context);
    }

    public function testUninstallAllDeletesFieldSetsOfAllProviders(): void
    {
        $this->customFieldSetRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), $this->context));

        $this->createInstaller([
            $this->createProvider(new CustomFieldSet('set_a', [], 'Set A', [])),
            $this->createProvider(new CustomFieldSet('set_b', [], 'Set B', [])),
        ])->uninstallAll($this->context);
    }

    /**
     * @param array<CustomFieldSetProviderInterface> $providers
     */
    private function createInstaller(array $providers = []): CustomFieldSetInstaller
    {
        return new CustomFieldSetInstaller(
            $this->customFieldRepository,
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository,
            $providers
        );
    }

    private function createProvider(CustomFieldSet $fieldSet): CustomFieldSetProviderInterface
    {
        return new readonly class ($fieldSet) implements CustomFieldSetProviderInterface {
            public function __construct(private CustomFieldSet $fieldSet)
            {
            }

            public function getCustomFieldSet(): CustomFieldSet
            {
                return $this->fieldSet;
            }
        };
    }

    /**
     * @template TCollection of CustomFieldSetCollection|CustomFieldSetRelationCollection
     *
     * @param TCollection $collection
     *
     * @return EntitySearchResult<TCollection>
     */
    private function createSearchResult(string $entityName, CustomFieldSetCollection|CustomFieldSetRelationCollection $collection): EntitySearchResult
    {
        return new EntitySearchResult($entityName, $collection->count(), $collection, null, new Criteria(), $this->context);
    }
}

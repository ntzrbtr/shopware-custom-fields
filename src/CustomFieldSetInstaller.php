<?php

declare(strict_types=1);

namespace Netzarbeiter\Shopware\CustomFields;

use Netzarbeiter\Shopware\CustomFields\Provider\CustomFieldSetProviderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Installer for custom field sets
 */
#[Autoconfigure(public: true)]
readonly class CustomFieldSetInstaller
{
    /**
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     * @param EntityRepository<CustomFieldSetRelationCollection> $customFieldSetRelationRepository
     * @param iterable<CustomFieldSetProviderInterface> $providers Providers; injected via tag when used as a service,
     *                                                             passed explicitly when instantiated manually (e.g. in
     *                                                             a plugin's install method)
     */
    public function __construct(
        #[Autowire(service: 'custom_field.repository')]
        private EntityRepository $customFieldRepository,
        #[Autowire(service: 'custom_field_set.repository')]
        private EntityRepository $customFieldSetRepository,
        #[Autowire(service: 'custom_field_set_relation.repository')]
        private EntityRepository $customFieldSetRelationRepository,
        #[AutowireIterator(CustomFieldSetProviderInterface::TAG)]
        private iterable $providers = []
    ) {
    }

    /**
     * Get all custom field sets.
     *
     * @return iterable<CustomFieldSet>
     */
    public function getCustomFieldSets(): iterable
    {
        foreach ($this->providers as $provider) {
            yield $provider->getCustomFieldSet();
        }
    }

    /**
     * Install custom field sets.
     */
    public function install(CustomFieldSet $fieldSet, Context $context): void
    {
        // Create or update the custom field set.
        $entity = $this->getCustomFieldSet($fieldSet->name, $context);
        if ($entity instanceof CustomFieldSetEntity) {
            $id = $this->updateCustomFieldSet($fieldSet, $entity, $context);
        } else {
            $id = $this->createCustomFieldSet($fieldSet, $context);
        }

        // Create/update the relation between the custom field set and the entity.
        $this->updateRelation($id, $fieldSet->entities, $context);
    }

    /**
     * Install all custom field sets from providers.
     */
    public function installAll(Context $context): void
    {
        foreach ($this->providers as $provider) {
            $this->install($provider->getCustomFieldSet(), $context);
        }
    }

    /**
     * Uninstall custom field sets.
     */
    public function uninstall(CustomFieldSet $fieldSet, Context $context): void
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('name', $fieldSet->name));
        $ids = $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
        $this->customFieldSetRepository->delete(
            array_map(static fn (string $id): array => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * Uninstall all custom field sets from providers.
     */
    public function uninstallAll(Context $context): void
    {
        foreach ($this->providers as $provider) {
            $this->uninstall($provider->getCustomFieldSet(), $context);
        }
    }

    /**
     * Update custom field set.
     */
    private function updateCustomFieldSet(
        CustomFieldSet $fieldSet,
        CustomFieldSetEntity $entity,
        Context $context
    ): string {
        // Serialize.
        $data = $fieldSet->toArray();

        // Set id.
        $data['id'] = $entity->getId();

        // Prepare custom fields.
        foreach ($data['customFields'] as &$field) {
            // Get id from database.
            $field['id'] = $entity
                ->getCustomFields()
                ?->filterByProperty(
                    'name',
                    $field['name']
                )
                ->first()
                ?->getId() ?? Uuid::randomHex();
        }

        unset($field);

        // Update database.
        $this->customFieldSetRepository->upsert([$data], $context);

        // Remove fields that are no longer present.
        $fieldNames = array_values(
            array_map(
                static fn (CustomField $field): string => $field->name,
                $fieldSet->customFields
            )
        );
        $criteria = new Criteria()
            ->addFilter(
                new NotFilter(
                    MultiFilter::CONNECTION_AND,
                    [new EqualsAnyFilter('name', $fieldNames)]
                )
            )
            ->addFilter(new EqualsFilter('customFieldSetId', $entity->getId()));
        $ids = $this->customFieldRepository->searchIds($criteria, $context)->getIds();
        $this->customFieldRepository->delete(
            array_map(static fn (string $id): array => ['id' => $id], $ids),
            $context
        );

        return $entity->getId();
    }

    /**
     * Create custom field set.
     */
    private function createCustomFieldSet(CustomFieldSet $fieldSet, Context $context): string
    {
        $data = $fieldSet->toArray();

        $this->customFieldSetRepository->upsert([$data], $context);

        return $fieldSet->id;
    }

    /**
     * Update relations between custom field set and entity.
     *
     * @param array<string> $entityNames
     */
    private function updateRelation(string $id, array $entityNames, Context $context): void
    {
        // Get existing relations.
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('customFieldSetId', $id));
        // @phpstan-ignore method.deprecatedClass (Method getEntities() will exist although inheritance hierarchy of class changes)
        $relations = $this->customFieldSetRelationRepository
            ->search($criteria, $context)
            ->getEntities();

        // Delete relations we no longer need.
        $relationsToDelete = $relations->filter(
            static fn (CustomFieldSetRelationEntity $relation): bool => !in_array(
                $relation->getEntityName(),
                $entityNames,
                true
            )
        );
        if ($relationsToDelete->count()) {
            $this->customFieldSetRelationRepository->delete(
                array_values(
                    $relationsToDelete->fmap(
                        static fn (CustomFieldSetRelationEntity $relation): array => ['id' => $relation->getId()]
                    )
                ),
                $context
            );
        }

        // Upsert remaining relations.
        $relationsToUpsert = [];
        foreach ($entityNames as $entityName) {
            /** @var CustomFieldSetRelationEntity|null $relation */
            $relation = $relations->firstWhere(
                static fn (CustomFieldSetRelationEntity $relation): bool => $relation->getEntityName() === $entityName
            );

            $relationsToUpsert[] = [
                'id' => $relation?->getId() ?? Uuid::randomHex(),
                'customFieldSetId' => $id,
                'entityName' => $entityName,
            ];
        }

        $this->customFieldSetRelationRepository->upsert($relationsToUpsert, $context);
    }

    /**
     * Fetch custom field set from database by its name.
     */
    private function getCustomFieldSet(string $name, Context $context): ?CustomFieldSetEntity
    {
        $criteria = new Criteria()
            ->addAssociation('customFields')
            ->addFilter(new EqualsFilter('name', $name));

        // @phpstan-ignore method.deprecatedClass (Method getEntities() will exist although inheritance hierarchy of class changes)
        return $this->customFieldSetRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }
}

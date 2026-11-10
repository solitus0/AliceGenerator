<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Solitus0\AliceGenerator\Exception\UnavailableObjectException;
use Solitus0\AliceGenerator\ValueContext;

class DoctrineMetadataHandler extends AbstractMetadataHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $skipIdentifiers = true,
        private readonly bool $skipEmptyStrings = true,
    ) {
    }

    public function canHandle(object $object): bool
    {
        return $this->getMetadata($object) instanceof ClassMetadata;
    }

    protected function getMetadata(object $object): bool|ClassMetadata
    {
        try {
            return $this->entityManager->getClassMetadata($this->getClass($object));
        } catch (\Exception) {
            return false;
        }
    }

    public function preHandle(object $object): void
    {
        try {
            $this->entityManager->initializeObject($object);
        } catch (EntityNotFoundException $exception) {
            throw new UnavailableObjectException($exception->getMessage(), 0, $exception);
        } catch (\Throwable) {
            // Preserve the existing handling of other proxy load failures.
        }
    }

    /** @internal */
    public function findOwningFieldForInverseCollection(object $object, string $property): ?string
    {
        $metadata = $this->getMetadata($object);
        if (!$metadata instanceof ORMClassMetadata
            || !$metadata->isCollectionValuedAssociation($property)
            || !$metadata->isAssociationInverseSide($property)
        ) {
            return null;
        }

        $owningField = $metadata->getAssociationMappedByTargetField($property);
        $target = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($property));

        return $target->isSingleValuedAssociation($owningField) && !$target->isAssociationInverseSide($owningField)
            ? $owningField
            : null;
    }

    public function shouldSkipProperty(ValueContext $valueContext): bool
    {
        $classMetadata = $this->getMetadata($valueContext->getContextObject());
        if (!$classMetadata instanceof ORMClassMetadata) {
            return false;
        }

        $propName = $valueContext->getPropName();

        // 1) Optionally skip single-field identifiers
        $ignore = false;

        if (
            $this->skipIdentifiers
            && $classMetadata->isIdentifier($propName)
            && !$classMetadata->isIdentifierComposite
        ) {
            // a) Generated IDs
            $hasGenerator = $classMetadata->generatorType !== ORMClassMetadata::GENERATOR_TYPE_NONE;

            // b) UUID/ULID-like scalar types (sometimes filled outside Doctrine's generator)
            $fieldType = $classMetadata->hasField($propName)
                ? (string) $classMetadata->getTypeOfField($propName)
                : null;

            $uuidLikeTypes = [
                'guid',
                'uuid',
                'uuid_binary',
                'uuid_binary_ordered_time',
                'ulid',
                'ulid_binary',
            ];

            $isUuidLike = $fieldType !== null && in_array($fieldType, $uuidLikeTypes, true);

            if ($hasGenerator || $isUuidLike) {
                $ignore = true;
            }
        }

        // 2) Is this property mapped by Doctrine?
        $isMappedField = $classMetadata->hasField($propName);
        $isMappedAssoc = $classMetadata->hasAssociation($propName);
        $isEmbedded = !empty($classMetadata->embeddedClasses)
            && array_key_exists($propName, $classMetadata->embeddedClasses);

        $mapped = $isMappedField || $isMappedAssoc || $isEmbedded;

        // 3) Optionally skip empty strings for nullable scalar fields
        $value = $valueContext->getUnencodedValue();
        if (
            $this->skipEmptyStrings
            && $isMappedField
            && is_string($value)
            && trim($value) === ''
        ) {
            $fieldMapping = $classMetadata->getFieldMapping($propName);
            $nullable = $fieldMapping['nullable'] ?? false;

            if ($nullable) {
                return true;
            }
        }

        return $ignore || !$mapped;
    }
}

<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
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
        // Force proxy objects to load data
        if (method_exists($object, '__load')) {
            try {
                $object->__load();
            } catch (\Throwable) {
                // ignore proxy load failures
            }
        }
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
                ? (string)$classMetadata->getTypeOfField($propName)
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
        if (
            $this->skipEmptyStrings
            && $isMappedField
            && is_string($valueContext->getValue())
            && trim($valueContext->getValue()) === ''
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

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

        // 1) Skip auto-generated single-field identifiers
        $ignore = false;
        if (
            $classMetadata->isIdentifier($propName)
            && $classMetadata->generatorType !== ORMClassMetadata::GENERATOR_TYPE_NONE
            && !$classMetadata->isIdentifierComposite
        ) {
            $ignore = true;
        }

        // 2) Is this property mapped by Doctrine?
        $isMappedField = $classMetadata->hasField($propName);
        $isMappedAssoc = $classMetadata->hasAssociation($propName);
        $isEmbedded = !empty($classMetadata->embeddedClasses)
            && array_key_exists($propName, $classMetadata->embeddedClasses);

        $mapped = $isMappedField || $isMappedAssoc || $isEmbedded;

        // 3) Optional rule: skip empty strings for nullable scalar Doctrine fields
        if (
            $this->skipEmptyStrings
            && $isMappedField
            && is_string($valueContext->getValue())
            && trim($valueContext->getValue()) === ''
        ) {
            // Determine nullability from field mapping
            $fieldMapping = $classMetadata->getFieldMapping($propName);
            $nullable = $fieldMapping['nullable'] ?? false;
            if ($nullable) {
                return true;
            }
        }

        return $ignore || !$mapped;
    }
}

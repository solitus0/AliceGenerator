<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Solitus0\AliceGenerator\ValueContext;

class DoctrineMetadataHandler extends AbstractMetadataHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
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

        $ignore = false;
        if (
            $classMetadata->isIdentifier($propName)
            && $classMetadata->generatorType !== ORMClassMetadata::GENERATOR_TYPE_NONE
            && !$classMetadata->isIdentifierComposite
        ) {
            $ignore = true;
        }

        $mapped = $classMetadata->hasField($propName)
            || $classMetadata->hasAssociation($propName)
            || (!empty($classMetadata->embeddedClasses) && array_key_exists($propName, $classMetadata->embeddedClasses));

        return $ignore || !$mapped;
    }
}

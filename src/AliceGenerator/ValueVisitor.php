<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProviderInterface;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolverInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\Storage\ObjectCacheCollection;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ValueVisitor
{
    private ObjectCacheCollection $objectCacheByClass;

    private FixtureGenerationContext $generationContext;

    private array $results = [];

    private int $recursionDepth = 0;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private readonly ClassMetadataProviderInterface $classMetadataProvider,
        private readonly MetadataHandlerInterface $handler,
        private readonly MetadataResolverInterface $metadataResolver,
        private readonly ObjectHandlerRegistryInterface $objectHandlerRegistry,
        private readonly PropertyNamerInterface $propertyNamer,
        private readonly ReferenceNamerInterface $referenceNamer,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function getGenerationContext(): FixtureGenerationContext
    {
        return $this->generationContext;
    }

    public function setup(FixtureGenerationContext $generationContext): void
    {
        $this->generationContext = $generationContext;

        // Reset caches
        $this->results = [];
        $this->objectCacheByClass = new ObjectCacheCollection($this->handler);
        $this->generationContext->getConstraintsCollection()->setMetadataHandler($this->handler);
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function visitSimpleValue(mixed $value): ValueContext
    {
        $valueContext = new ValueContext($value);
        $this->visitUnknownType($valueContext);

        return $valueContext;
    }

    public function visitUnknownType(ValueContext $valueContext): void
    {
        if (is_array($valueContext->getValue())) {
            $this->visitArray($valueContext);
        } elseif (is_object($valueContext->getValue())) {
            $this->visitObject($valueContext);
        }
    }

    public function visitArray(ValueContext $valueContext): void
    {
        $array = $valueContext->getValue();

        foreach ($array as $key => &$item) {
            $itemValueContext = $this->visitSimpleValue($item);

            if ($itemValueContext->isSkipped()) {
                unset($array[$key]);
            } else {
                $array[$key] = $itemValueContext->getValue();
            }
        }

        if (count($array) === 0) {
            $valueContext->setSkipped(true);
        } else {
            $valueContext->setValue($array);
        }
    }

    public function visitObject(ValueContext $valueContext): void
    {
        $object = $valueContext->getValue();

        $objectHandled = $this->objectHandlerRegistry->runHandlers($valueContext);

        if (!$objectHandled && $this->handler->canHandle($object)) {
            if (!$this->generationContext->getConstraintsCollection()->isAllowed($object)) {
                $valueContext->setSkipped(true);

                return;
            }

            $result = $this->objectCacheByClass->find($object);
            switch ($result) {
                case ObjectCacheCollection::OBJECT_NOT_FOUND:
                    if ($this->recursionDepth <= $this->generationContext->getMaximumRecursion()) {
                        $key = $this->objectCacheByClass->add($object);
                        $reference = $this->referenceNamer->createReference($object, $key);

                        $objectAdded = $this->handlePersistedObject($object, $reference);

                        if ($objectAdded) {
                            $valueContext->setValue('@' . $reference);

                            return;
                        }

                        $this->objectCacheByClass->skip($object);
                        $valueContext->setSkipped(true);
                        return;
                    }

                    break;
                case ObjectCacheCollection::OBJECT_SKIPPED:
                    $valueContext->setSkipped(true);

                    return;
                default:
                    $valueContext->setValue('@' . $this->referenceNamer->createReference($object, $result));

                    return;
            }

            $valueContext->setSkipped(true);
        }

        if (!$valueContext->isSkipped() && !$valueContext->isModified()) {
            throw new UnknownObjectTypeException(
                sprintf(
                    'Object of unknown type "%s" encountered during generation. Unknown types can\'t be serialized ' .
                    'directly. You can create an ObjectHandler for this type, or supply metadata on the property for' .
                    'how this should be handled.',
                    get_debug_type($valueContext->getValue())
                )
            );
        }
    }

    private function handlePersistedObject(object $object, string $reference): bool
    {
        $class = $this->handler->getClass($object);
        $this->handler->preHandle($object);

        $saveValues = [];
        $this->recursionDepth++;

        $reflectionClass = new \ReflectionClass($class);
        $newObject = $reflectionClass->newInstanceWithoutConstructor();
        $propertyMetadatas = $this->classMetadataProvider->getPropertyMetadata($reflectionClass);

        foreach ($propertyMetadatas as $propertyMetadata) {
            $reflectionProperty = new \ReflectionProperty($propertyMetadata->class, $propertyMetadata->name);
            $reflectionProperty->setAccessible(true);

            $value = $reflectionProperty->isInitialized($object) ? $reflectionProperty->getValue($object) : null;
            $initialValue = $reflectionProperty->isInitialized($newObject) ? $reflectionProperty->getValue(
                $newObject
            ) : null;

            $valueContext = new ValueContext($value, $class, $object, $propertyMetadata, $this);

            if ($this->handler->shouldSkipProperty($valueContext)) {
                continue;
            }

            $this->metadataResolver->resolve($valueContext);

            if (!$valueContext->isModified() && !$valueContext->isSkipped()) {
                $value = $valueContext->getValue();
                if ($value === $initialValue) {
                    continue;
                }

                $this->visitUnknownType($valueContext);
            }

            if ($valueContext->isSkipped()) {
                continue;
            }

            if ($this->generationContext->shouldSkipNonWritableProperties()) {
                if (!$this->propertyAccessor->isWritable($object, $propertyMetadata->name)) {
                    continue;
                }
            }

            $propName = $this->propertyNamer->createName($valueContext);
            if ($propName === '' || $propName === '0') {
                throw new InvalidPropertyNameException('Property name must be a non empty string.');
            }

            // TODO sanitize logic

            $saveValues[$propName] = $valueContext->getValue();
        }

        $this->recursionDepth--;

        if ($saveValues === []) {
            return false;
        }

        $this->results[$class][$reference] = $saveValues;
        return true;
    }
}

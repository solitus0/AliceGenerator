<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureWriteRouteResolver;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProviderInterface;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolverInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRulesCollection;
use Solitus0\AliceGenerator\PropertyTransformer\ValueTransformerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\Storage\ObjectCacheCollection;

class ValueVisitor
{
    private ObjectCacheCollection $objectCacheByClass;

    private FixtureGenerationContext $generationContext;

    private PropertyTransformRulesCollection $transformRulesCollection;

    private array $results = [];

    private int $recursionDepth = 0;

    public function __construct(
        private readonly ClassMetadataProviderInterface $classMetadataProvider,
        private readonly MetadataHandlerInterface $handler,
        private readonly MetadataResolverInterface $metadataResolver,
        private readonly ObjectHandlerRegistryInterface $objectHandlerRegistry,
        private readonly PropertyNamerInterface $propertyNamer,
        private readonly ReferenceNamerInterface $referenceNamer,
        private readonly FixtureWriteRouteResolver $fixtureWriteRouteResolver,
    ) {
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
        $this->transformRulesCollection = $this->generationContext->getTransformRulesCollection();
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
        $propertyMetadata = $valueContext->getMetadata();
        if ($propertyMetadata) {
            $valueTransformer = $this->transformRulesCollection->getPropertyTransformer($propertyMetadata);
            if ($valueTransformer instanceof ValueTransformerInterface) {
                $valueTransformer->transform($valueContext);
            }
        }

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

        $properties = [];
        $constructorCandidates = [];
        $setterCandidates = [];
        $this->recursionDepth++;

        $reflectionClass = new \ReflectionClass($class);
        $newObject = $reflectionClass->newInstanceWithoutConstructor();
        $propertyMetadatas = $this->classMetadataProvider->getPropertyMetadata($reflectionClass);

        foreach ($propertyMetadatas as $propertyMetadata) {
            $reflectionProperty = new \ReflectionProperty($propertyMetadata->class, $propertyMetadata->name);
            $reflectionProperty->setAccessible(true);

            $value = $reflectionProperty->isInitialized($object) ? $reflectionProperty->getValue($object) : null;
            $valueContext = new ValueContext($value, $class, $object, $propertyMetadata, $this);

            if ($this->handler->shouldSkipProperty($valueContext)) {
                continue;
            }

            $this->metadataResolver->resolve($valueContext);

            if (!$valueContext->isModified() && !$valueContext->isSkipped()) {
                $initialValue = $reflectionProperty->isInitialized($newObject) ?
                    $reflectionProperty->getValue($newObject) : null;

                $value = $valueContext->getValue();
                if ($value === $initialValue) {
                    continue;
                }

                $this->visitUnknownType($valueContext);
            }

            if ($valueContext->isSkipped()) {
                continue;
            }

            $propName = $this->propertyNamer->createName($valueContext);
            if ($propName === '' || $propName === '0') {
                throw new InvalidPropertyNameException('Property name must be a non empty string.');
            }

            $writeRoute = $this->fixtureWriteRouteResolver->resolve($object, $reflectionClass, $reflectionProperty, $propName);
            $normalizedValue = $valueContext->getValue();

            if ($writeRoute['type'] === 'property') {
                $properties[$propName] = $normalizedValue;

                continue;
            }

            if ($writeRoute['type'] === 'constructor') {
                $constructorCandidates[] = [
                    'position' => $writeRoute['position'],
                    'property' => $propName,
                    'value' => $normalizedValue,
                ];

                continue;
            }

            if ($writeRoute['type'] === 'setter') {
                $setterCandidates[] = [
                    'method' => $writeRoute['method'],
                    'property' => $propName,
                    'value' => $normalizedValue,
                ];

                continue;
            }

            if (!$this->generationContext->shouldSkipNonWritableProperties()) {
                $properties[$propName] = $normalizedValue;
            }
        }

        $this->recursionDepth--;

        $saveValues = [];

        $constructorArguments = $this->fixtureWriteRouteResolver->buildConstructorArguments(
            $reflectionClass,
            $constructorCandidates
        );
        if ($constructorArguments !== null) {
            $saveValues['__construct'] = $constructorArguments;
        } else {
            foreach ($constructorCandidates as $candidate) {
                $setterMethod = $this->fixtureWriteRouteResolver->findSetterMethod(
                    $reflectionClass,
                    $candidate['property']
                );
                if ($setterMethod !== null) {
                    $setterCandidates[] = [
                        'method' => $setterMethod,
                        'property' => $candidate['property'],
                        'value' => $candidate['value'],
                    ];

                    continue;
                }

                if (!$this->generationContext->shouldSkipNonWritableProperties()) {
                    $properties[$candidate['property']] = $candidate['value'];
                }
            }
        }

        foreach ($properties as $property => $value) {
            $saveValues[$property] = $value;
        }

        if ($setterCandidates !== []) {
            $saveValues['__calls'] = array_map(
                static fn (array $candidate): array => [$candidate['method'] => [$candidate['value']]],
                $setterCandidates
            );
        }

        if ($saveValues === []) {
            return false;
        }

        $this->results[$class][$reference] = $saveValues;

        return true;
    }
}

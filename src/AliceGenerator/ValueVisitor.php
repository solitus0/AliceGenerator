<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Exception\UnavailableObjectException;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureWriteRouteResolver;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProviderInterface;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolverInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamer;
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

    /** @var list<string> */
    private array $discardedReferences = [];

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

        $this->recursionDepth = 0;
        $this->results = [];
        $this->discardedReferences = [];
        $this->objectCacheByClass = new ObjectCacheCollection($this->handler);
        $this->generationContext->getConstraintsCollection()->setMetadataHandler($this->handler);
        $this->transformRulesCollection = $this->generationContext->getTransformRulesCollection();
    }

    public function getResults(): array
    {
        if ($this->discardedReferences === []) {
            return $this->results;
        }

        $results = $this->results;
        $dependents = [];
        foreach ($results as $class => $fixtures) {
            foreach ($fixtures as $reference => $definition) {
                array_walk_recursive($definition, static function (mixed $value) use (&$dependents, $class, $reference): void {
                    if (is_string($value) && str_starts_with($value, '@')) {
                        $dependents[$value][$reference] = $class;
                    }
                });
            }
        }

        // Back-references can be written before the target's fixture is accepted.
        $discarded = $this->discardedReferences;
        while (($reference = array_pop($discarded)) !== null) {
            foreach ($dependents['@' . $reference] ?? [] as $dependent => $class) {
                if (isset($results[$class][$dependent])) {
                    unset($results[$class][$dependent]);
                    $discarded[] = $dependent;
                }
            }
        }

        return array_filter($results);
    }

    public function visitSimpleValue(mixed $value): ValueContext
    {
        $valueContext = new ValueContext($value, valueVisitor: $this);
        $this->visitUnknownType($valueContext);

        return $valueContext;
    }

    public function visitUnknownType(ValueContext $valueContext): void
    {
        $this->visitValue($valueContext, preserveEmptyArrays: false);
    }

    private function visitValue(ValueContext $valueContext, bool $preserveEmptyArrays): void
    {
        $propertyMetadata = $valueContext->getMetadata();
        if ($propertyMetadata) {
            $valueTransformer = $this->transformRulesCollection->getPropertyTransformer($propertyMetadata);
            if ($valueTransformer instanceof ValueTransformerInterface) {
                $valueTransformer->transform($valueContext);
            }
        }

        if (is_array($valueContext->getValue())) {
            if ($preserveEmptyArrays) {
                $this->visitArrayValue($valueContext, preserveEmptyArrays: true);
            } else {
                $this->visitArray($valueContext);
            }
        } elseif (is_object($valueContext->getValue())) {
            $this->visitObject($valueContext);
        }
    }

    public function visitArray(ValueContext $valueContext): void
    {
        $this->visitArrayValue($valueContext, preserveEmptyArrays: false);
    }

    private function visitArrayValue(ValueContext $valueContext, bool $preserveEmptyArrays): void
    {
        $array = $valueContext->getValue();
        $keepEmpty = $preserveEmptyArrays && $array === [];
        $allowsStringable = $valueContext->allowsStringable()
            || $this->acceptsStringable($valueContext, arrayItem: true);

        foreach ($array as $key => &$item) {
            $itemValueContext = new ValueContext($item, valueVisitor: $this, allowsStringable: $allowsStringable);
            if ($preserveEmptyArrays && is_array($item)) {
                $this->visitArrayValue($itemValueContext, preserveEmptyArrays: true);
            } else {
                $this->visitUnknownType($itemValueContext);
            }

            if ($itemValueContext->isSkipped()) {
                unset($array[$key]);
            } else {
                $array[$key] = $itemValueContext->getValue();
            }
        }

        if ($array === [] && !$keepEmpty) {
            $valueContext->setSkipped(true);
        } else {
            $valueContext->setValue($array);
        }
    }

    public function visitObject(ValueContext $valueContext): void
    {
        $object = $valueContext->getValue();

        $objectHandled = $this->objectHandlerRegistry->runHandlers($valueContext);
        $hasMetadata = !$objectHandled && $this->handler->canHandle($object);

        if (
            !$objectHandled
            && $object instanceof \Stringable
            && (!$hasMetadata || $this->handler::class === NonSpecificMetadataHandler::class)
            && ($valueContext->allowsStringable() || $this->acceptsStringable($valueContext))
        ) {
            $valueContext->setLiteralString((string) $object);

            return;
        }

        if ($hasMetadata) {
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
                        $this->discardedReferences[] = $reference;
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

    private function acceptsStringable(ValueContext $valueContext, bool $arrayItem = false): bool
    {
        $owner = $valueContext->getContextObject();
        $class = $valueContext->getContextObjectClass();
        $metadata = $valueContext->getMetadata();
        if ($owner === null || $class === null || $metadata === null || $this->propertyNamer::class !== PropertyNamer::class) {
            return false;
        }

        return $this->fixtureWriteRouteResolver->acceptsStringable(
            $owner,
            new \ReflectionClass($class),
            new \ReflectionProperty($metadata->class, $metadata->name),
            $arrayItem,
        );
    }

    private function handlePersistedObject(object $object, string $reference): bool
    {
        $class = $this->handler->getClass($object);

        try {
            $this->handler->preHandle($object);
        } catch (UnavailableObjectException) {
            return false;
        }

        $prunedGraphValue = false;
        $this->recursionDepth++;

        $reflectionClass = new \ReflectionClass($class);
        $writePlan = $this->fixtureWriteRouteResolver->createPlan($object, $reflectionClass, $this->generationContext);
        $newObject = $reflectionClass->newInstanceWithoutConstructor();
        $propertyMetadatas = $this->classMetadataProvider->getPropertyMetadata($reflectionClass);

        foreach ($propertyMetadatas as $propertyMetadata) {
            $reflectionProperty = new \ReflectionProperty($propertyMetadata->class, $propertyMetadata->name);
            $reflectionProperty->setAccessible(true);

            $isInitialized = $reflectionProperty->isInitialized($object);
            $value = $isInitialized ? $reflectionProperty->getValue($object) : null;
            $valueContext = new ValueContext($value, $class, $object, $propertyMetadata, $this);

            if ($this->handler->shouldSkipProperty($valueContext)) {
                continue;
            }

            $this->metadataResolver->resolve($valueContext);

            $constructorOnly = false;
            if (!$valueContext->isModified() && !$valueContext->isSkipped()) {
                $initialValue = $reflectionProperty->isInitialized($newObject) ?
                    $reflectionProperty->getValue($newObject) : null;

                $value = $valueContext->getValue();
                if ($value === $initialValue) {
                    if (!$isInitialized || !$writePlan->canSupplyDefaultValue($reflectionProperty->getName())) {
                        continue;
                    }

                    $constructorOnly = true;
                    $this->visitValue($valueContext, preserveEmptyArrays: true);
                } else {
                    $this->visitUnknownType($valueContext);
                }

                if ($valueContext->isSkipped() && (is_object($value) || is_array($value))) {
                    $prunedGraphValue = true;
                }
            }

            if ($valueContext->isSkipped()) {
                continue;
            }

            $propName = $this->propertyNamer->createName($valueContext);
            if ($propName === '' || $propName === '0') {
                throw new InvalidPropertyNameException('Property name must be a non empty string.');
            }

            $writePlan->addProperty($reflectionProperty, $propName, $valueContext, $constructorOnly);
        }

        $this->recursionDepth--;

        $saveValues = $writePlan->buildDefinition($class, $prunedGraphValue);
        if ($saveValues === []) {
            return false;
        }

        $this->results[$class][$reference] = $saveValues;

        return true;
    }
}

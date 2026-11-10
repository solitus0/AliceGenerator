<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Exception\UnavailableObjectException;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGraph;
use Solitus0\AliceGenerator\Generator\FixtureWriteRouteResolver;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProviderInterface;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolverInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamer;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\PropertyTransformer\ValueTransformerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;

class ValueVisitor
{
    private FixtureGraph $graph;

    private bool $collectingFixtures = false;

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
        return $this->graph->context;
    }

    public function setup(FixtureGenerationContext $generationContext): void
    {
        $this->graph = new FixtureGraph($generationContext, $this->handler, $this->referenceNamer);
        $generationContext->getConstraintsCollection()->setMetadataHandler($this->handler);
    }

    /** @internal */
    public function collectFixtures(mixed $value, FixtureGenerationContext $generationContext): array
    {
        if ($this->collectingFixtures) {
            $visitor = clone $this;
            $visitor->collectingFixtures = false;

            return $visitor->collectFixtures($value, $generationContext);
        }

        $this->collectingFixtures = true;

        try {
            $this->setup($generationContext);
            $this->visitSimpleValue($value);

            return $this->getResults();
        } finally {
            $this->collectingFixtures = false;
        }
    }

    public function getResults(): array
    {
        return isset($this->graph) ? $this->graph->getDefinitions() : [];
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
            $rules = $this->graph->context->getTransformRulesCollection();
            $valueTransformer = $rules->getPropertyTransformer($propertyMetadata);
            if ($valueTransformer instanceof ValueTransformerInterface) {
                $valueTransformer->transform($valueContext);
            }
        }

        $value = $valueContext->getUnencodedValue();
        if (is_array($value)) {
            if ($preserveEmptyArrays) {
                $this->visitArrayValue($valueContext, preserveEmptyArrays: true);
            } else {
                $this->visitArray($valueContext);
            }
        } elseif (is_object($value) && !$value instanceof AliceExpression) {
            $this->visitObject($valueContext);
        }
    }

    public function visitArray(ValueContext $valueContext): void
    {
        $this->visitArrayValue($valueContext, preserveEmptyArrays: false);
    }

    private function visitArrayValue(ValueContext $valueContext, bool $preserveEmptyArrays): void
    {
        $array = $valueContext->getUnencodedValue();
        $keepEmpty = $preserveEmptyArrays && $array === [];
        $allowsStringable = $valueContext->allowsStringable()
            || $this->acceptsStringable($valueContext, arrayItem: true);

        $items = [];
        foreach ($array as $key => $item) {
            $itemValueContext = new ValueContext($item, valueVisitor: $this, allowsStringable: $allowsStringable);
            if ($preserveEmptyArrays && is_array($item)) {
                $this->visitArrayValue($itemValueContext, preserveEmptyArrays: true);
            } else {
                $this->visitUnknownType($itemValueContext);
            }

            if (!$itemValueContext->isSkipped()) {
                $items[$key] = $itemValueContext->getFixtureValue();
            }
        }

        if ($items === [] && !$keepEmpty) {
            $valueContext->setSkipped(true);
        } else {
            $valueContext->setSourceValue($items);
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
            $reference = $this->graph->referenceFor($object, $this->buildFixtureDefinition(...));
            if ($reference === null) {
                $valueContext->setSkipped(true);
            } else {
                $valueContext->setSourceValue(AliceExpression::reference($reference));
            }

            return;
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

    /** @return array<string, mixed> */
    private function buildFixtureDefinition(object $object, string $class): array
    {
        try {
            $this->handler->preHandle($object);
        } catch (UnavailableObjectException) {
            return [];
        }

        $prunedGraphValue = false;

        $reflectionClass = new \ReflectionClass($class);
        $writePlan = $this->fixtureWriteRouteResolver->createPlan($object, $reflectionClass, $this->graph->context);
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

                $value = $valueContext->getUnencodedValue();
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

        return $writePlan->buildDefinition($class, $prunedGraphValue);
    }
}

<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Doctrine\Common\Collections\Collection;
use Solitus0\AliceGenerator\Exception\UnavailableObjectException;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\CollectionSelection;
use Solitus0\AliceGenerator\Generator\DeferredStringable;
use Solitus0\AliceGenerator\Generator\FinalizedFixtureWrites;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGraph;
use Solitus0\AliceGenerator\Generator\FixtureWriteRouteResolver;
use Solitus0\AliceGenerator\Generator\PreparedProperty;
use Solitus0\AliceGenerator\Generator\StringableWriteMode;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProviderInterface;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolver;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolverInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataExportPolicyInterface;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\MetadataHandler\ObjectExportMode;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\PropertyTransformer\ValueTransformerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;

class ValueVisitor
{
    private FixtureGraph $graph;

    private array $completedResults = [];

    public function __construct(
        private readonly ClassMetadataProviderInterface $classMetadataProvider,
        private readonly MetadataHandlerInterface $handler,
        private readonly ?MetadataResolverInterface $metadataResolver,
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
        $this->completedResults = [];
        $this->graph = new FixtureGraph($generationContext, $this->handler, $this->referenceNamer);
    }

    /** @internal */
    public function collectFixtures(mixed $value, FixtureGenerationContext $generationContext): array
    {
        return $this->completedResults = $this->collectRunFixtures($value, $generationContext);
    }

    /** @internal */
    public function collectRunFixtures(mixed $value, FixtureGenerationContext $generationContext): array
    {
        $traversal = $this->createTraversal();

        try {
            $traversal->setup($generationContext);
            $traversal->visitSimpleValue($value);
            $traversal->completedResults = $traversal->getResults();

            return $traversal->completedResults;
        } finally {
            // Extension callbacks can retain the run's visitor for access to its results.
            unset($traversal->graph);
        }
    }

    private function createTraversal(): self
    {
        if ($this::class !== self::class) {
            // Preserve custom visitor hooks and their constructor configuration.
            return clone $this;
        }

        return new self(
            $this->classMetadataProvider,
            $this->handler,
            $this->metadataResolver,
            $this->objectHandlerRegistry,
            $this->propertyNamer,
            $this->referenceNamer,
            $this->fixtureWriteRouteResolver,
        );
    }

    public function getResults(): array
    {
        return isset($this->graph) ? $this->graph->getDefinitions() : $this->completedResults;
    }

    public function visitSimpleValue(mixed $value): ValueContext
    {
        $valueContext = new ValueContext($value, valueVisitor: $this);
        $this->visitUnknownType($valueContext);

        return $valueContext;
    }

    public function visitUnknownType(ValueContext $valueContext): void
    {
        $this->transformProperty($valueContext);
        $this->visitValue($valueContext, preserveEmptyArrays: false);
    }

    private function transformProperty(ValueContext $valueContext): void
    {
        $propertyMetadata = $valueContext->getMetadata();
        if ($propertyMetadata) {
            $rules = $this->graph->context->getTransformRulesCollection();
            $valueTransformer = $rules->getPropertyTransformer($propertyMetadata);
            if ($valueTransformer instanceof ValueTransformerInterface) {
                $valueTransformer->transform($valueContext);
            }
        }
    }

    private function visitValue(ValueContext $valueContext, bool $preserveEmptyArrays): void
    {
        if ($valueContext->isSkipped()) {
            return;
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

    /**
     * @internal
     * @param iterable<mixed> $collection
     */
    public function visitCollection(ValueContext $valueContext, iterable $collection): void
    {
        $items = CollectionSelection::select(
            $collection,
            $valueContext->getContextObjectClass(),
            $this->getGenerationContext(),
        );
        if ($items === null) {
            $valueContext->setSkipped(true);

            return;
        }

        $valueContext->setSourceValue($items);
        $visitSelected = $valueContext->getSelectedCollectionVisitor();
        if ($visitSelected !== null) {
            $visitSelected($items);

            return;
        }

        $this->visitArray($valueContext);
    }

    private function visitUnwritableCollection(ValueContext $valueContext, ?string $adderItemType): bool
    {
        if ($adderItemType === null) {
            $this->discoverInverseCollection($valueContext);

            return false;
        }

        $useAdder = false;
        $valueContext->withSelectedCollectionVisitor(
            function (array $items) use ($valueContext, $adderItemType, &$useAdder): void {
                foreach ($items as $item) {
                    if (!is_object($item) || !is_a($item, $adderItemType)) {
                        $useAdder = false;
                        $this->discoverInverseCollection($valueContext);

                        return;
                    }
                }

                $useAdder = true;
                $this->visitArray($valueContext);
            },
            fn () => $this->visitValue($valueContext, preserveEmptyArrays: false),
        );

        return $useAdder;
    }

    private function visitArrayValue(ValueContext $valueContext, bool $preserveEmptyArrays): void
    {
        $array = $valueContext->getUnencodedValue();
        $keepEmpty = $preserveEmptyArrays && $array === [];
        $stringableMode = $valueContext->getStringableWriteMode(arrayItem: true);

        $items = [];
        foreach ($array as $key => $item) {
            $itemValueContext = new ValueContext($item, valueVisitor: $this);
            $itemValueContext->setStringableWriteMode($stringableMode);
            if ($preserveEmptyArrays && is_array($item)) {
                $this->visitArrayValue($itemValueContext, preserveEmptyArrays: true);
            } else {
                $this->visitUnknownType($itemValueContext);
            }

            if (!$itemValueContext->isSkipped()) {
                $items[$key] = $itemValueContext->getUnencodedValue();
            }
        }

        if ($items === [] && !$keepEmpty) {
            $valueContext->setSkipped(true);
        } else {
            $valueContext->setSourceValue($items);
        }
    }

    private function discoverInverseCollection(ValueContext $valueContext): void
    {
        $owner = $valueContext->getContextObject();
        $collection = $valueContext->getUnencodedValue();
        if (!$this->handler instanceof MetadataExportPolicyInterface || $owner === null
            || (!$collection instanceof Collection && !is_array($collection))
        ) {
            return;
        }

        $owningField = $this->handler->findOwningFieldForInverseCollection($owner, $valueContext->getPropName());
        if ($owningField !== null) {
            $this->graph->discoverInverseCollection($owner, $owningField, function () use ($valueContext): mixed {
                $this->visitValue($valueContext, preserveEmptyArrays: false);

                return $valueContext->isSkipped() ? null : $valueContext->getUnencodedValue();
            });
        }
    }

    public function visitObject(ValueContext $valueContext): void
    {
        $object = $valueContext->getValue();

        $exportMode = $this->objectHandlerRegistry->runHandlers($valueContext) ? null : $this->getObjectExportMode($object);
        $exportAsFixture = $exportMode?->allowsFixture() ?? false;

        if ($object instanceof \Stringable && $exportMode?->allowsText()) {
            $mode = $valueContext->getStringableWriteMode();
            if ($mode === StringableWriteMode::Text) {
                $valueContext->setLiteralString((string) $object);

                return;
            }

            if ($mode->dependsOnConstructor()) {
                $valueContext->setSourceValue(new DeferredStringable(
                    $object,
                    $mode,
                    fn (): ?AliceExpression => $exportAsFixture
                        ? $this->visitFixtureReference($object)
                        : throw $this->unknownObjectType($object),
                ));

                return;
            }
        }

        if ($exportAsFixture) {
            $reference = $this->visitFixtureReference($object);
            if ($reference === null) {
                $valueContext->setSkipped(true);
            } else {
                $valueContext->setSourceValue($reference);
            }

            return;
        }

        if (!$valueContext->isSkipped() && !$valueContext->isModified()) {
            throw $this->unknownObjectType($valueContext->getValue());
        }
    }

    private function getObjectExportMode(object $object): ObjectExportMode
    {
        return $this->handler instanceof MetadataExportPolicyInterface
            ? $this->handler->getObjectExportMode($object)
            : ObjectExportMode::fromMetadataSupport($this->handler->canHandle($object));
    }

    private function visitFixtureReference(object $object): ?AliceExpression
    {
        $reference = $this->graph->referenceFor($object, $this->buildFixtureDefinition(...));

        return $reference === null ? null : AliceExpression::reference($reference);
    }

    private function unknownObjectType(mixed $value): UnknownObjectTypeException
    {
        return new UnknownObjectTypeException(sprintf(
            'Object of unknown type "%s" encountered during generation. Unknown types can\'t be serialized ' .
            'directly. You can create an ObjectHandler for this type, or supply metadata on the property for ' .
            'how this should be handled.',
            get_debug_type($value),
        ));
    }

    private function buildFixtureDefinition(object $object, string $class): ?FinalizedFixtureWrites
    {
        try {
            $this->handler->preHandle($object);
        } catch (UnavailableObjectException) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($class);
        $writePlan = $this->fixtureWriteRouteResolver->createPlan($object, $reflectionClass, $this->graph->context);
        $newObject = $reflectionClass->newInstanceWithoutConstructor();
        $hasRequiredConstructorArguments = $writePlan->hasRequiredConstructorArguments();
        $visitProperty = $this->visitValue(...);
        $visitUnwritableCollection = $this->visitUnwritableCollection(...);

        foreach ($this->classMetadataProvider->getPropertyMetadata($reflectionClass) as $metadata) {
            $property = $this->prepareProperty(
                $object,
                $class,
                $newObject,
                $metadata,
                $hasRequiredConstructorArguments,
            );
            if ($property !== null) {
                $writePlan->addProperty($property, $visitProperty, $visitUnwritableCollection);
            }
        }

        return $writePlan->buildDefinition($class);
    }

    private function prepareProperty(
        object $object,
        string $class,
        object $newObject,
        PropertyMetadata $metadata,
        bool $hasRequiredConstructorArguments,
    ): ?PreparedProperty {
        $reflection = new \ReflectionProperty($metadata->class, $metadata->name);
        $reflection->setAccessible(true);
        $isInitialized = $reflection->isInitialized($object);
        $value = $isInitialized ? $reflection->getValue($object) : null;
        $context = new ValueContext($value, $class, $object, $metadata, $this);
        if ($this->handler->shouldSkipProperty($context)) {
            return null;
        }

        if ($this->metadataResolver === null) {
            MetadataResolver::applyOverrides($context);
        } else {
            $this->metadataResolver->resolve($context);
        }
        if ($context->isSkipped()) {
            return null;
        }

        $needsTraversal = !$context->isModified();
        $constructorOnly = false;
        $source = $context->getUnencodedValue();
        if ($needsTraversal) {
            $initialValue = $reflection->isInitialized($newObject) ? $reflection->getValue($newObject) : null;
            $constructorOnly = $source === $initialValue;
            if ($constructorOnly && (!$isInitialized || !$hasRequiredConstructorArguments)) {
                return null;
            }

            $this->transformProperty($context);
            // The write plan can still identify a skipped constructor default as a missing required value.
            if ($context->isSkipped() && !$constructorOnly) {
                return null;
            }
        }

        $name = $this->propertyNamer->createName($context);
        $graphSource = $constructorOnly ? $source : $context->getUnencodedValue();

        return new PreparedProperty(
            $reflection,
            $name,
            $context,
            $constructorOnly,
            $needsTraversal,
            is_object($graphSource) || is_array($graphSource),
        );
    }
}

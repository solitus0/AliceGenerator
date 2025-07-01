<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Metadata\MetadataFactory;
use Solitus0\AliceGenerator\Metadata\Driver\AttributeDriver;
use Solitus0\AliceGenerator\Metadata\Provider\ClassMetadataProvider;
use Solitus0\AliceGenerator\Metadata\Resolver\MetadataResolver;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\ObjectHandler as ObjectHandler;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistry;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamer;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ClassNamer;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\ValueVisitor;

class FixtureGeneratorBuilder
{
    private MetadataHandlerInterface $handler;

    private readonly ObjectHandlerRegistryInterface $objectHandlerRegistry;

    private readonly ClassMetadataProvider $classMetadataProvider;

    private readonly MetadataResolver $metadataResolver;

    private PropertyNamerInterface $propertyNamer;

    private ReferenceNamerInterface $referenceNamer;

    private GeneratorInterface $generator;

    public function __construct()
    {
        $this->handler = new NonSpecificMetadataHandler();
        $this->generator = new YamlGenerator(3, 4);

        $objectHandlerRegistry = new ObjectHandlerRegistry();
        $objectHandlerRegistry->registerHandlers([
            new ObjectHandler\CollectionHandler(),
            new ObjectHandler\DateTimeHandler(),
            new ObjectHandler\DateTimeImmutableHandler(),
            new ObjectHandler\RamseyUuidHandler(),
            new ObjectHandler\EnumHandler(),
        ]);

        $this->objectHandlerRegistry = $objectHandlerRegistry;

        $this->classMetadataProvider = new ClassMetadataProvider(new MetadataFactory(new AttributeDriver()));
        $this->metadataResolver = new MetadataResolver();
        $this->referenceNamer = new ClassNamer();
        $this->propertyNamer = new PropertyNamer();
    }

    public static function create(): self
    {
        return new self();
    }

    public function setMetadataHandler(MetadataHandlerInterface $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function setGenerator(GeneratorInterface $generator): self
    {
        $this->generator = $generator;

        return $this;
    }

    public function configureObjectHandlerRegistry(\Closure $configure): self
    {
        $configure($this->objectHandlerRegistry);
        return $this;
    }

    public function getPropertyNamer(): PropertyNamerInterface
    {
        return $this->propertyNamer;
    }

    public function setPropertyNamer(PropertyNamerInterface $propertyNamer): self
    {
        $this->propertyNamer = $propertyNamer;

        return $this;
    }

    public function getReferenceNamer(): ReferenceNamerInterface
    {
        return $this->referenceNamer;
    }

    public function setReferenceNamer(ReferenceNamerInterface $referenceNamer): self
    {
        $this->referenceNamer = $referenceNamer;

        return $this;
    }

    public function build(): FixtureGenerator
    {
        return new FixtureGenerator(
            new ValueVisitor(
                $this->classMetadataProvider,
                $this->handler,
                $this->metadataResolver,
                $this->objectHandlerRegistry,
                $this->propertyNamer,
                $this->referenceNamer,
            ),
            $this->generator
        );
    }
}

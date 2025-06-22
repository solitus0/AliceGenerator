# Getting Started

## Installation

Install via Composer:

```bash
composer require solitus0/alice-generator
```

## Quick Start

Below is a minimal example demonstrating how to generate fixtures from your entities:

```php
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\MetadataHandler\DoctrineMetadataHandler;

$generator = FixtureGeneratorBuilder::create()
    ->setMetadataHandler(new DoctrineMetadataHandler($entityManager))
    ->build();

$context = FixtureGenerationContext::create()
    ->setMaximumRecursion(max: 2);

$yaml = $generator->generate($entity, $context);
```
<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Solitus0\AliceGenerator\Generator\FixtureGenerator;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\Generator\GeneratorInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;

class FixtureGeneratorBuilderTest extends TestCase
{
    public function testBuildFixtureGenerator(): void
    {
        // @phpstan-ignore-next-line PHPUnit assertion: always true in this context
        $this->assertInstanceOf(FixtureGenerator::class, FixtureGeneratorBuilder::create()->build());
    }

    public function testConfiguringObjectHandlerRegistry(): void
    {
        FixtureGeneratorBuilder::create()
            ->configureObjectHandlerRegistry(function ($registry): void {
                $this->assertInstanceOf(ObjectHandlerRegistryInterface::class, $registry);
            })
        ;
    }

    public function testSetWriterIsChainable(): void
    {
        $fixtureGeneratorBuilder = FixtureGeneratorBuilder::create();
        $writer = $this->createMock(GeneratorInterface::class);
        $this->assertSame($fixtureGeneratorBuilder, $fixtureGeneratorBuilder->setGenerator($writer));
    }
}

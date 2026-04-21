<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\Generator\GeneratorInterface;
use Symfony\Component\Yaml\Yaml;

class FixtureRoutingTest extends TestCase
{
    public function testWritablePropertyIsSavedDirectly(): void
    {
        $data = $this->generateFixtureData(new WritablePropertyFixture('Alice'));

        $this->assertSame(
            [
                WritablePropertyFixture::class => [
                    'WritablePropertyFixture-1' => [
                        'name' => 'Alice',
                    ],
                ],
            ],
            $data
        );
    }

    public function testConstructorOnlyPropertyUsesConstruct(): void
    {
        $data = $this->generateFixtureData(new ConstructorOnlyFixture('token-1'));

        $this->assertSame(
            ['token-1'],
            $data[ConstructorOnlyFixture::class]['ConstructorOnlyFixture-1']['__construct']
        );
    }

    public function testSetterBackedPropertyStaysAsPlainProperty(): void
    {
        $data = $this->generateFixtureData(new SetterOnlyFixture('draft'));

        $this->assertSame(
            [
                SetterOnlyFixture::class => [
                    'SetterOnlyFixture-1' => [
                        'status' => 'draft',
                    ],
                ],
            ],
            $data
        );
    }

    public function testMixedRoutingUsesConstructAndPlainProperties(): void
    {
        $fixture = new MixedRoutingFixture('id-1', 'Alice', 'published');
        $data = $this->generateFixtureData($fixture);

        $this->assertSame(
            [
                '__construct' => ['id-1'],
                'name' => 'Alice',
                'status' => 'published',
            ],
            $data[MixedRoutingFixture::class]['MixedRoutingFixture-1']
        );

        $yaml = $this->generateYaml($fixture);
        $parsed = Yaml::parse($yaml);

        $this->assertSame(
            $data[MixedRoutingFixture::class]['MixedRoutingFixture-1'],
            $parsed[MixedRoutingFixture::class]['MixedRoutingFixture-1']
        );
    }

    public function testConstructorBackedPrivateStatePrefersConstructorOverSetterBackedPropertyWrites(): void
    {
        $data = $this->generateFixtureData(new RouteLikeFixture('pages', 'id-1', 'lt', '/', 'maxima'));

        $this->assertSame(
            [
                '__construct' => ['pages', 'id-1', 'lt', '/', 'maxima'],
            ],
            $data[RouteLikeFixture::class]['RouteLikeFixture-1']
        );
    }

    public function testDollarSignsAreEscapedInPlainStrings(): void
    {
        $yaml = $this->generateYaml(new WritablePropertyFixture('$2y$13$Aazu5qay'));

        $this->assertStringContainsString('name: \$2y\$13\$Aazu5qay', $yaml);
    }

    public function testConstructorDefaultsFillMissingOptionalArguments(): void
    {
        $data = $this->generateFixtureData(new ConstructorDefaultsFixture('first', last: 'last'));

        $this->assertSame(
            ['first', 'fallback', 'last'],
            $data[ConstructorDefaultsFixture::class]['ConstructorDefaultsFixture-1']['__construct']
        );
    }

    public function testInvalidConstructorRoutingIsSkippedByDefault(): void
    {
        $data = $this->generateFixtureData(new InvalidConstructorRoutingFixture(null, 'second'));

        $this->assertSame([], $data);
    }

    public function testInvalidConstructorRoutingFallsBackToRawPropertyWhenConfigured(): void
    {
        $context = FixtureGenerationContext::create()->setSkipNonWritableProperties(false);
        $data = $this->generateFixtureData(new InvalidConstructorRoutingFixture(null, 'second'), $context);

        $this->assertSame(
            [
                InvalidConstructorRoutingFixture::class => [
                    'InvalidConstructorRoutingFixture-1' => [
                        'second' => 'second',
                    ],
                ],
            ],
            $data
        );
    }

    private function generateFixtureData(object $object, ?FixtureGenerationContext $context = null): array
    {
        $capturingGenerator = new class() implements GeneratorInterface {
            public array $data = [];

            public function generate(array $data): string
            {
                $this->data = $data;

                return '';
            }
        };

        $generator = FixtureGeneratorBuilder::create()
            ->setGenerator($capturingGenerator)
            ->build()
        ;

        $generator->generate($object, $context ?? FixtureGenerationContext::create());

        return $capturingGenerator->data;
    }

    private function generateYaml(object $object, ?FixtureGenerationContext $context = null): string
    {
        $generator = FixtureGeneratorBuilder::create()->build();

        return $generator->generate($object, $context ?? FixtureGenerationContext::create());
    }
}

<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Solitus0\AliceGenerator\Exception\InvalidArgumentException;
use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\Generator\PhpGenerator;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\CallbackNamer;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorDefaultsFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorOnlyFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorSetterFallbackFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\InvalidConstructorRoutingFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\MixedRoutingFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\Priority;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RequiredRelationFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RouteLikeFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\SetterOnlyFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\StaticSetterFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\UnwritableStateFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ValueFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\Visibility;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\WritablePropertyFixture;
use Solitus0\AliceGenerator\Tests\Support\FixtureTestCase;
use Solitus0\AliceGenerator\ValueContext;
use Symfony\Component\Yaml\Yaml;

final class FixtureRoundTripTest extends FixtureTestCase
{
    #[DataProvider('literalStrings')]
    public function testRestoresLiteralStrings(string $value): void
    {
        $objects = $this->loadFixtures($this->generate(new WritablePropertyFixture($value)));
        $fixture = $objects['WritablePropertyFixture-1'];

        self::assertInstanceOf(WritablePropertyFixture::class, $fixture);
        self::assertSame($value, $fixture->name);
    }

    public static function literalStrings(): iterable
    {
        yield 'plain text' => ['Alice'];
        yield 'literal dollar signs' => ['$2y$13$Aazu5qay'];
        yield 'literal reference marker' => ['@helpdesk'];
        yield 'literal backslash before reference marker' => ['\@helpdesk'];
        yield 'literal backslash before variable marker' => ['\$value'];
        yield 'email address' => ['support@example.org'];
        yield 'literal HTML' => ['<p title="Example">Alice &amp; Bob</p>'];
        yield 'multiline HTML' => ["<p>First line\r\nSecond line\nThird line</p>"];
    }

    #[DataProvider('dates')]
    public function testRestoresDateValues(\DateTimeInterface $date): void
    {
        $objects = $this->loadFixtures($this->generate(new ValueFixture($date)));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertInstanceOf($date::class, $fixture->value);
        self::assertEquals($date, $fixture->value);
        self::assertSame($date->getTimezone()->getName(), $fixture->value->getTimezone()->getName());
    }

    public static function dates(): iterable
    {
        $timezone = new \DateTimeZone(date_default_timezone_get() === 'Europe/Vilnius' ? 'UTC' : 'Europe/Vilnius');
        yield 'mutable midnight outside the default timezone' => [new \DateTime('2026-04-21 00:00:00', $timezone)];
        yield 'mutable with timezone' => [new \DateTime('2026-04-21 10:30:00', new \DateTimeZone('Europe/Vilnius'))];
        yield 'immutable midnight outside the default timezone' => [new \DateTimeImmutable('2026-04-21 00:00:00', $timezone)];
        yield 'mutable fractional seconds' => [new \DateTime('2026-04-21 10:30:00.125000', $timezone)];
        yield 'immutable fractional seconds' => [new \DateTimeImmutable('2026-04-21 10:30:00.125000', $timezone)];
    }

    public function testRestoresUnitAndIntegerBackedEnums(): void
    {
        $source = new ValueFixture([Visibility::Internal, Priority::High]);
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame([Visibility::Internal, Priority::High], $fixture->value);
    }

    public function testSharedReferencesStillResolveAfterSkippingAnEmptyObject(): void
    {
        $empty = new ValueFixture();
        $shared = new ValueFixture('shared');
        $source = new ValueFixture([$empty, $shared, $empty, $shared]);
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertIsArray($fixture->value);
        self::assertCount(2, $fixture->value);
        [$first, $second] = array_values($fixture->value);
        self::assertInstanceOf(ValueFixture::class, $first);
        self::assertSame('shared', $first->value);
        self::assertSame($first, $second);
        self::assertCount(2, $objects);
    }

    public function testSkipsAFixtureWhoseRequiredRelationIsOutsideTheRecursionLimit(): void
    {
        $source = new RequiredRelationFixture(new ValueFixture('related'), 'parent');
        $full = $this->loadFixtures($this->generate($source));
        $parent = $full['RequiredRelationFixture-1'];
        self::assertInstanceOf(RequiredRelationFixture::class, $parent);
        self::assertSame('related', $parent->relation->value);

        $context = FixtureGenerationContext::create()->setMaximumRecursion(0);
        $objects = $this->loadFixtures($this->generate([$source, new WritablePropertyFixture('kept')], $context));

        self::assertSame(['WritablePropertyFixture-1'], array_keys($objects));
    }

    public function testPrunesDependentFixturesWhenTheirReferencedParentIsSkipped(): void
    {
        $parent = new UnwritableStateFixture();
        $parent->replaceState(new ValueFixture(new ValueFixture($parent)));

        $objects = $this->loadFixtures($this->generate([$parent, new WritablePropertyFixture('kept')]));

        self::assertSame(['WritablePropertyFixture-1'], array_keys($objects));
    }

    public function testExportsObjectsFromARootDoctrineCollection(): void
    {
        $source = new ArrayCollection([new ValueFixture('first'), new ValueFixture('second')]);
        $objects = $this->loadFixtures($this->generate($source));
        self::assertCount(2, $objects);
        [$first, $second] = array_values($objects);
        self::assertInstanceOf(ValueFixture::class, $first);
        self::assertInstanceOf(ValueFixture::class, $second);
        self::assertSame('first', $first->value);
        self::assertSame('second', $second->value);
    }

    public function testExportsScalarCollectionItemsAndAppliesTheOwnerFilter(): void
    {
        $source = new ValueFixture(new ArrayCollection(['skip', 'keep', 3, false, null]));
        $context = FixtureGenerationContext::create()->addCollectionItemSkipCondition(
            static fn (string $owner, mixed $item): bool => $owner === ValueFixture::class && $item === 'skip'
        );
        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame(['keep', 3, false, null], $fixture->value);
    }

    public function testUsesTheConstructorWhenArgumentsAreAvailableEvenIfBypassIsAllowed(): void
    {
        $context = FixtureGenerationContext::create()->allowConstructorBypassFor(ConstructorOnlyFixture::class);
        $objects = $this->loadFixtures($this->generate(new ConstructorOnlyFixture('token-1'), $context));
        $fixture = $objects['ConstructorOnlyFixture-1'];

        self::assertInstanceOf(ConstructorOnlyFixture::class, $fixture);
        self::assertSame(['token-1'], $fixture->debugState());
    }

    public function testRestoresPrivateConstructorStateWhenSettersAlsoExist(): void
    {
        $objects = $this->loadFixtures($this->generate(new RouteLikeFixture('pages', 'id-1', 'lt', '/', 'maxima')));
        $fixture = $objects['RouteLikeFixture-1'];

        self::assertInstanceOf(RouteLikeFixture::class, $fixture);
        self::assertSame(['pages', 'id-1', 'lt', '/', 'maxima', null], $fixture->debugState());
    }

    public function testFillsAnOmittedOptionalConstructorArgument(): void
    {
        $source = new ConstructorDefaultsFixture('first', last: 'last');
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ConstructorDefaultsFixture-1'];

        self::assertInstanceOf(ConstructorDefaultsFixture::class, $fixture);
        self::assertSame(['first', 'fallback', 'last'], $fixture->debugState());
    }

    public function testRestoresRequiredWritableConstructorArgumentsAndSetterState(): void
    {
        $objects = $this->loadFixtures($this->generate(new MixedRoutingFixture('id-1', 'Alice', 'published')));
        $fixture = $objects['MixedRoutingFixture-1'];

        self::assertInstanceOf(MixedRoutingFixture::class, $fixture);
        self::assertSame('Alice', $fixture->name);
        self::assertSame(['id-1', 'published'], $fixture->debugState());
    }

    public function testHydratesSetterBackedState(): void
    {
        $objects = $this->loadFixtures($this->generate(new SetterOnlyFixture('published')));
        $fixture = $objects['SetterOnlyFixture-1'];

        self::assertInstanceOf(SetterOnlyFixture::class, $fixture);
        self::assertSame(['published'], $fixture->debugState());
    }

    public function testSkipsStateThatCanOnlyBeWrittenThroughAStaticSetter(): void
    {
        $source = new StaticSetterFixture();
        $source->name = 'kept';
        $source->changeStatus('changed');

        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['StaticSetterFixture-1'];

        self::assertInstanceOf(StaticSetterFixture::class, $fixture);
        self::assertSame('kept', $fixture->name);
        self::assertSame('initial', $fixture->getStatus());
        self::assertSame('initial', $fixture->getInternalStatus());
    }

    public function testExportsPrivateStateWithoutAWriteRouteOnlyWhenRequested(): void
    {
        $source = new UnwritableStateFixture();
        $source->name = 'kept';
        $source->replaceState('changed');
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['UnwritableStateFixture-1'];

        self::assertInstanceOf(UnwritableStateFixture::class, $fixture);
        self::assertSame('kept', $fixture->name);
        self::assertNull($fixture->getState());

        $context = FixtureGenerationContext::create()->setSkipNonWritableProperties(false);
        $yaml = $this->generate($source, $context);
        self::assertSame(
            ['name' => 'kept', 'state' => 'changed'],
            Yaml::parse($yaml)[UnwritableStateFixture::class]['UnwritableStateFixture-1']
        );
    }

    public function testExportsSetterCallsWhenConstructorArgumentsAreUnavailable(): void
    {
        $yaml = $this->generate(new ConstructorSetterFallbackFixture('draft', 'published'));
        self::assertSame(
            ['__calls' => [['setStatus' => ['published']]]],
            Yaml::parse($yaml)[ConstructorSetterFallbackFixture::class]['ConstructorSetterFallbackFixture-1']
        );
    }

    public function testLoadsSetterFallbackForAClassThatAllowsConstructorBypass(): void
    {
        $source = new ConstructorSetterFallbackFixture('draft', 'published');
        $context = FixtureGenerationContext::create()->allowConstructorBypassFor(ConstructorSetterFallbackFixture::class);
        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ConstructorSetterFallbackFixture-1'];

        self::assertInstanceOf(ConstructorSetterFallbackFixture::class, $fixture);
        self::assertSame('published', $fixture->getStatus());
    }

    public function testSkipsAnUnwritableValueWhenConstructorArgumentsAreUnavailable(): void
    {
        $objects = $this->loadFixtures($this->generate(new InvalidConstructorRoutingFixture(null, 'second')));

        self::assertSame([], $objects);
    }

    public function testExportsRawPropertiesWhenRequested(): void
    {
        $context = FixtureGenerationContext::create()->setSkipNonWritableProperties(false);
        $yaml = $this->generate(new InvalidConstructorRoutingFixture(null, 'second'), $context);

        self::assertSame(
            ['second' => 'second'],
            Yaml::parse($yaml)[InvalidConstructorRoutingFixture::class]['InvalidConstructorRoutingFixture-1']
        );
    }

    public function testExportsPhpFixturesWithCustomReferences(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setGenerator(new PhpGenerator())
            ->setReferenceNamer(new CallbackNamer(static fn (object $object, int $index): string => 'user-' . $index))
            ->build()
        ;
        $php = $generator->generate(new WritablePropertyFixture('Alice'), FixtureGenerationContext::create());
        $objects = $this->loadFixtures($php, 'php');
        $fixture = $objects['user-1'];

        self::assertInstanceOf(WritablePropertyFixture::class, $fixture);
        self::assertSame('Alice', $fixture->name);
    }

    public function testUsesARegisteredObjectHandlerDuringGeneration(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->configureObjectHandlerRegistry(static function (ObjectHandlerRegistryInterface $registry): void {
                $registry->registerHandlers([new class() implements ObjectHandlerInterface {
                    public function handle(ValueContext $valueContext): bool
                    {
                        if (!$valueContext->getValue() instanceof \Stringable) {
                            return false;
                        }

                        $valueContext->setValue((string) $valueContext->getValue());

                        return true;
                    }
                }]);
            })
            ->build()
        ;
        $name = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'Alice';
            }
        };
        $yaml = $generator->generate(new ValueFixture($name), FixtureGenerationContext::create());
        $objects = $this->loadFixtures($yaml);
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame('Alice', $fixture->value);
    }

    public function testReusesGeneratorAfterRejectingAnInvalidPropertyName(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setPropertyNamer(new class() implements PropertyNamerInterface {
                public function createName(ValueContext $valueContext): string
                {
                    return $valueContext->getValue() === 'invalid' ? '' : $valueContext->getPropName();
                }
            })
            ->build()
        ;

        $context = FixtureGenerationContext::create()->setMaximumRecursion(0);

        try {
            $generator->generate(new WritablePropertyFixture('invalid'), $context);
            self::fail('Generation accepted an empty fixture property name.');
        } catch (InvalidPropertyNameException) {
            $objects = $this->loadFixtures($generator->generate(new WritablePropertyFixture('Alice'), $context));
        }

        $fixture = $objects['WritablePropertyFixture-1'];
        self::assertInstanceOf(WritablePropertyFixture::class, $fixture);
        self::assertSame('Alice', $fixture->name);
    }

    public function testRejectsANegativeCollectionLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addCollectionItemLimitPerOwner(WritablePropertyFixture::class, -1);
    }

    public function testRejectsANegativeCollectionItemClassLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addLimitForOwnerItemCollection(
            WritablePropertyFixture::class,
            ConstructorOnlyFixture::class,
            -1,
        );
    }

    public function testRejectsNonObjectConstraints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addPersistedObjectConstraint(['not an object']);
    }

    public function testRejectsInvalidCollectionOwnerClasses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addOwnersWithSkippedCollections([new ValueFixture()]);
    }

    /** @param object|list<object> $source */
    private function generate(object|array $source, ?FixtureGenerationContext $context = null): string
    {
        return FixtureGeneratorBuilder::create()->build()->generate($source, $context ?? FixtureGenerationContext::create());
    }
}

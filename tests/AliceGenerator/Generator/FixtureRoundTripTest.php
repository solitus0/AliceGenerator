<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\Exception\InvalidArgumentException;
use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGenerator;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\Generator\PhpGenerator;
use Solitus0\AliceGenerator\Generator\YamlGenerator;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\PropertyNamer\PropertyNamerInterface;
use Solitus0\AliceGenerator\PropertyTransformer\CallbackValueTransformer;
use Solitus0\AliceGenerator\PropertyTransformer\ClassPropertyMatcher;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRule;
use Solitus0\AliceGenerator\PropertyTransformer\SimpleValueTransformer;
use Solitus0\AliceGenerator\PropertyTransformer\ValueTransformerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\CallbackNamer;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\CollectionAdderFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorDefaultsFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorObjectWithTextSetterFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorOnlyFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorSetterFallbackFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ConstructorTextWithObjectSetterFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\ExpressionLikeEnum;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\MixedRoutingFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\Other\WritablePropertyFixture as OtherWritablePropertyFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\Priority;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\PropertyDiscoveryChildFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\PropertyDiscoveryParentFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RenamedConstructorFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RequiredDefaultArrayFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RequiredNullableConstructorFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RequiredRelationFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\RouteLikeFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\SetterOnlyFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\StaticSetterFixture;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\StringableText;
use Solitus0\AliceGenerator\Tests\Generator\Fixtures\StringableWriteRoutesFixture;
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
        yield 'Alice expression' => ['<(1 + 2)>'];
        yield 'Alice list' => ['[first, second]'];
        yield 'Alice dynamic array' => ['2x item'];
        yield 'Alice optional value' => ['100%? item'];
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

    #[DataProvider('fixtureFormats')]
    public function testSeparatesTransformerValuesFromExplicitAliceExpressions(string $format): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setGenerator($format === 'php' ? new PhpGenerator() : new YamlGenerator(3, 4))
            ->build()
        ;
        $context = FixtureGenerationContext::create()
            ->addPropertyTransformRule(new PropertyTransformRule(
                new ClassPropertyMatcher(WritablePropertyFixture::class, 'name'),
                new SimpleValueTransformer('@missing_fixture'),
            ))
            ->addPropertyTransformRule(new PropertyTransformRule(
                new ClassPropertyMatcher(ValueFixture::class, 'value'),
                new CallbackValueTransformer(static fn (ValueContext $value): array => [
                    $value->getValue(),
                    '<(1 + 2)>',
                    new AliceExpression('<(1 + 2)>'),
                    ExpressionLikeEnum::Quoted,
                ]),
            ))
        ;

        $fixtures = $generator->generate([
            new WritablePropertyFixture('original'),
            new ValueFixture('@original'),
        ], $context);
        $objects = $this->loadFixtures($fixtures, $format);
        $literal = $objects['WritablePropertyFixture-1'];
        $mixed = $objects['ValueFixture-1'];

        self::assertInstanceOf(WritablePropertyFixture::class, $literal);
        self::assertInstanceOf(ValueFixture::class, $mixed);
        self::assertSame('@missing_fixture', $literal->name);
        self::assertSame(['@original', '<(1 + 2)>', 3, ExpressionLikeEnum::Quoted], $mixed->value);
    }

    public static function fixtureFormats(): iterable
    {
        yield 'YAML' => ['yaml'];
        yield 'PHP' => ['php'];
    }

    #[DataProvider('fixtureFormats')]
    public function testPreservesCustomTransformerSyntaxAtEveryNestingDepth(string $format): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setGenerator($format === 'php' ? new PhpGenerator() : new YamlGenerator(3, 4))
            ->build()
        ;
        $transformer = new class() implements ValueTransformerInterface {
            public function transform(ValueContext $valueContext): void
            {
                $values = ['<(1 + 2)>', ['@WritablePropertyFixture-1', new AliceExpression('<(2 + 2)>')]];
                if ($valueContext->getValue() === 'syntax') {
                    $valueContext->setValue($values);
                } else {
                    $valueContext->setSourceValue($values);
                }
            }
        };
        $context = FixtureGenerationContext::create()->addPropertyTransformRule(new PropertyTransformRule(
            new ClassPropertyMatcher(ValueFixture::class, 'value'),
            $transformer,
        ));

        $objects = $this->loadFixtures($generator->generate([
            new ValueFixture('syntax'),
            new ValueFixture('literal'),
            new WritablePropertyFixture('target'),
        ], $context), $format);
        $syntax = $objects['ValueFixture-1'];
        $literal = $objects['ValueFixture-2'];

        self::assertInstanceOf(ValueFixture::class, $syntax);
        self::assertInstanceOf(ValueFixture::class, $literal);
        self::assertSame([3, [$objects['WritablePropertyFixture-1'], 4]], $syntax->value);
        self::assertSame(['<(1 + 2)>', ['@WritablePropertyFixture-1', 4]], $literal->value);
    }

    public function testDiscoversInheritedAndShadowedPropertiesInHierarchyOrder(): void
    {
        $namer = new class() implements PropertyNamerInterface {
            /** @var list<array{string, string}> */
            public array $properties = [];

            public function createName(ValueContext $valueContext): string
            {
                $metadata = $valueContext->getMetadata();
                assert($metadata !== null);
                $this->properties[] = [$metadata->class, $metadata->name];

                return $metadata->name;
            }
        };
        $generator = FixtureGeneratorBuilder::create()->setPropertyNamer($namer)->build();
        $context = FixtureGenerationContext::create()->addPropertyTransformRule(new PropertyTransformRule(
            new ClassPropertyMatcher(PropertyDiscoveryChildFixture::class, 'inheritedPublic'),
            new SimpleValueTransformer('matched on child'),
        ));
        $source = new PropertyDiscoveryChildFixture();
        $source->setInheritedPrivate('inherited private value');
        $source->setParentShared('hidden parent value');
        $source->setShared('child value');
        $source->inheritedPublic = 'inherited public value';
        $source->childOnly = 'child only value';

        $objects = $this->loadFixtures($generator->generate($source, $context));
        $fixture = $objects['PropertyDiscoveryChildFixture-1'];

        self::assertSame([
            [PropertyDiscoveryParentFixture::class, 'inheritedPrivate'],
            [PropertyDiscoveryChildFixture::class, 'shared'],
            [PropertyDiscoveryChildFixture::class, 'inheritedPublic'],
            [PropertyDiscoveryChildFixture::class, 'childOnly'],
        ], $namer->properties);
        self::assertInstanceOf(PropertyDiscoveryChildFixture::class, $fixture);
        self::assertSame('inherited private value', $fixture->getInheritedPrivate());
        self::assertSame('child value', $fixture->getShared());
        self::assertSame('parent default', $fixture->getParentShared());
        self::assertSame('matched on child', $fixture->inheritedPublic);
        self::assertSame('child only value', $fixture->childOnly);
    }

    public function testAppliesMetadataOverridesFromTheConfiguredHandler(): void
    {
        $handler = new class() extends NonSpecificMetadataHandler {
            public function shouldSkipProperty(ValueContext $valueContext): bool
            {
                $metadata = $valueContext->getMetadata();
                if ($metadata?->class === ValueFixture::class) {
                    $metadata->staticData = '<(1 + 2)>';
                } elseif ($metadata?->class === WritablePropertyFixture::class) {
                    $metadata->ignore = true;
                }

                return false;
            }
        };
        $generator = FixtureGeneratorBuilder::create()->setMetadataHandler($handler)->build();
        $fixtures = $generator->generate([
            new ValueFixture('replace me'),
            new WritablePropertyFixture('ignore me'),
        ], FixtureGenerationContext::create());
        $objects = $this->loadFixtures($fixtures);

        self::assertSame(['ValueFixture-1'], array_keys($objects));
        self::assertInstanceOf(ValueFixture::class, $objects['ValueFixture-1']);
        self::assertSame(3, $objects['ValueFixture-1']->value);
    }

    public function testKeepsMetadataOverridesLocalToOnePropertyVisit(): void
    {
        $handler = new class() extends NonSpecificMetadataHandler {
            public function shouldSkipProperty(ValueContext $valueContext): bool
            {
                $metadata = $valueContext->getMetadata();
                if ($metadata !== null && $valueContext->getValue() === 'mask me') {
                    $metadata->staticData = 'masked';
                } elseif ($metadata !== null && $valueContext->getValue() === 'ignore me') {
                    $metadata->ignore = true;
                }

                return false;
            }
        };
        $builder = FixtureGeneratorBuilder::create()->setMetadataHandler($handler);
        $generator = $builder->build();
        $context = FixtureGenerationContext::create();

        $objects = $this->loadFixtures($generator->generate([
            new ValueFixture('mask me'),
            new ValueFixture('keep after mask'),
            new ValueFixture('ignore me'),
            new ValueFixture('keep after ignore'),
        ], $context));

        self::assertSame(['ValueFixture-1', 'ValueFixture-2', 'ValueFixture-4'], array_keys($objects));
        self::assertEquals([
            new ValueFixture('masked'),
            new ValueFixture('keep after mask'),
            new ValueFixture('keep after ignore'),
        ], array_values($objects));

        $source = new ValueFixture('keep in next run');
        $secondRun = $this->loadFixtures($generator->generate($source, $context));
        self::assertEquals($source, $secondRun['ValueFixture-1']);

        $defaultGenerator = $builder->setMetadataHandler(new NonSpecificMetadataHandler())->build();
        $defaultRun = $this->loadFixtures($defaultGenerator->generate($source, $context));
        self::assertEquals($source, $defaultRun['ValueFixture-1']);
    }

    public function testExportsStringableValuesAsLiteralText(): void
    {
        $source = new ValueFixture(new StringableText('@helpdesk'));
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame('@helpdesk', $fixture->value);
        self::assertCount(1, $objects);
    }

    public function testExportsStringableItemsInAPlainArray(): void
    {
        $source = new ValueFixture([new StringableText('first'), [new StringableText('\$second')]]);
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame(['first', ['\$second']], $fixture->value);
        self::assertCount(1, $objects);
    }

    public function testUsesTextOnlyForWriteRoutesThatAcceptIt(): void
    {
        $requiredObject = new StringableText('required object');
        $source = new StringableWriteRoutesFixture(new StringableText('constructor text'), $requiredObject);
        $source->typedProperty = $requiredObject;
        $source->setSetterValue(new StringableText('setter text'));
        $source->addLabel($requiredObject);
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['StringableWriteRoutesFixture-1'];

        self::assertInstanceOf(StringableWriteRoutesFixture::class, $fixture);
        self::assertSame('constructor text', $fixture->getConstructorValue());
        self::assertSame('setter text', $fixture->getSetterValue());
        self::assertInstanceOf(StringableText::class, $fixture->requiredObject);
        self::assertSame('required object', $fixture->requiredObject->text);
        self::assertSame($fixture->requiredObject, $fixture->typedProperty);
        self::assertSame([$fixture->requiredObject], $fixture->getLabels());
    }

    public function testExportsAStringableRootAsAnObject(): void
    {
        $objects = $this->loadFixtures($this->generate(new StringableText('root state')));
        $fixture = $objects['StringableText-1'];

        self::assertInstanceOf(StringableText::class, $fixture);
        self::assertSame('root state', $fixture->text);
    }

    public function testUsesConstructorTextEvenWhenTheFallbackSetterRequiresAnObject(): void
    {
        $source = new ConstructorTextWithObjectSetterFixture('original', new ValueFixture('relation'));
        $source->setText(new StringableText('@literal'));

        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['ConstructorTextWithObjectSetterFixture-1'];

        self::assertInstanceOf(ConstructorTextWithObjectSetterFixture::class, $fixture);
        self::assertSame('@literal', $fixture->getText());
        self::assertSame($objects['ValueFixture-1'], $fixture->relation);
        self::assertCount(2, $objects);
    }

    public function testUsesTheObjectForSetterFallbackAfterARequiredRelationIsPruned(): void
    {
        $source = new ConstructorTextWithObjectSetterFixture('original', new ValueFixture());
        $source->setText(new StringableText('fallback object'));
        $context = FixtureGenerationContext::create()->allowConstructorBypassFor($source::class);

        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ConstructorTextWithObjectSetterFixture-1'];

        self::assertInstanceOf(ConstructorTextWithObjectSetterFixture::class, $fixture);
        self::assertSame($objects['StringableText-1'], $fixture->getText());
        self::assertCount(2, $objects);
    }

    public function testUsesTextForSetterFallbackEvenWhenTheConstructorRequiresAnObject(): void
    {
        $source = new ConstructorObjectWithTextSetterFixture(new StringableText('@fallback'), new ValueFixture());
        $context = FixtureGenerationContext::create()
            ->setMaximumRecursion(0)
            ->allowConstructorBypassFor($source::class)
        ;

        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ConstructorObjectWithTextSetterFixture-1'];

        self::assertInstanceOf(ConstructorObjectWithTextSetterFixture::class, $fixture);
        self::assertSame('@fallback', $fixture->getText());
        self::assertCount(1, $objects);
    }

    public function testReconsidersConstructorAvailabilityWhenTheStringableReferenceIsPruned(): void
    {
        $source = new ConstructorObjectWithTextSetterFixture(new StringableText('fallback'), new ValueFixture('relation'));
        $context = FixtureGenerationContext::create()
            ->addPersistedObjectConstraint(new StringableText('only allowed object'))
            ->allowConstructorBypassFor($source::class)
        ;

        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ConstructorObjectWithTextSetterFixture-1'];

        self::assertInstanceOf(ConstructorObjectWithTextSetterFixture::class, $fixture);
        self::assertSame('fallback', $fixture->getText());
        self::assertArrayNotHasKey('StringableText-1', $objects);
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

    public function testReservesOneNameForASharedFixture(): void
    {
        $calls = 0;
        $generator = FixtureGeneratorBuilder::create()
            ->setReferenceNamer(new CallbackNamer(static function () use (&$calls): string {
                return 'fixture-' . ++$calls;
            }))
            ->build()
        ;
        $shared = new ValueFixture('shared');
        $source = new ValueFixture([$shared, $shared]);

        $objects = $this->loadFixtures($generator->generate($source, FixtureGenerationContext::create()));
        $root = $objects['fixture-1'];

        self::assertInstanceOf(ValueFixture::class, $root);
        self::assertSame([$objects['fixture-2'], $objects['fixture-2']], $root->value);
        self::assertSame(2, $calls);
    }

    public function testReloadsDistinctObjectsWithTheSameShortClassName(): void
    {
        $other = new OtherWritablePropertyFixture();
        $other->name = 'other';
        $source = new ValueFixture([new WritablePropertyFixture('first'), $other]);

        $objects = $this->loadFixtures($this->generate($source));
        $root = $objects['ValueFixture-1'];
        $first = $objects['WritablePropertyFixture-1'];
        $second = $objects['WritablePropertyFixture-1-2'];

        self::assertInstanceOf(ValueFixture::class, $root);
        self::assertInstanceOf(WritablePropertyFixture::class, $first);
        self::assertInstanceOf(OtherWritablePropertyFixture::class, $second);
        self::assertSame([$first, $second], $root->value);
    }

    public function testDisambiguatesCustomNamesWithoutReusingDiscardedNames(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setReferenceNamer(new CallbackNamer(
                static fn (object $object, int $index): string => $index === 2 ? 'fixture-2' : 'fixture',
            ))
            ->build()
        ;
        $source = [
            new ValueFixture(),
            new ValueFixture('existing suffix'),
            new ValueFixture('one'),
            new ValueFixture('two'),
        ];

        $objects = $this->loadFixtures($generator->generate($source, FixtureGenerationContext::create()));

        self::assertSame(['fixture-2', 'fixture-3', 'fixture-4'], array_keys($objects));
    }

    public function testNestedGenerationKeepsItsOwnGraphAndRecursionLimit(): void
    {
        $child = new ValueFixture(['outer child', new ValueFixture('outside the outer limit')]);
        $source = new ValueFixture($child);
        $generator = null;
        $nestedFixture = '';
        $generator = FixtureGeneratorBuilder::create()
            ->setReferenceNamer(new CallbackNamer(static function (object $object, int $index) use (
                $child,
                &$generator,
                &$nestedFixture,
            ): string {
                if ($object === $child) {
                    self::assertInstanceOf(FixtureGenerator::class, $generator);
                    $nestedFixture = $generator->generate(
                        new WritablePropertyFixture('inner'),
                        FixtureGenerationContext::create(),
                    );
                }

                return ($object instanceof ValueFixture ? 'outer-' : 'inner-') . $index;
            }))
            ->build()
        ;

        $outer = $this->loadFixtures($generator->generate(
            $source,
            FixtureGenerationContext::create()->setMaximumRecursion(1),
        ));
        $inner = $this->loadFixtures($nestedFixture);

        self::assertSame(['outer-1', 'outer-2'], array_keys($outer));
        self::assertSame(['inner-1'], array_keys($inner));
        self::assertInstanceOf(ValueFixture::class, $outer['outer-1']);
        self::assertInstanceOf(ValueFixture::class, $outer['outer-2']);
        self::assertInstanceOf(WritablePropertyFixture::class, $inner['inner-1']);
        self::assertSame($outer['outer-2'], $outer['outer-1']->value);
        self::assertSame(['outer child'], $outer['outer-2']->value);
        self::assertSame('inner', $inner['inner-1']->name);
    }

    public function testBindsObjectConstraintsToEachRunsMetadataHandler(): void
    {
        $handler = new class() extends NonSpecificMetadataHandler {
            public function getClass(object $object): string
            {
                return $object instanceof PropertyDiscoveryChildFixture
                    ? PropertyDiscoveryParentFixture::class
                    : parent::getClass($object);
            }
        };
        $allowed = new PropertyDiscoveryChildFixture();
        $allowed->inheritedPublic = 'allowed';
        $excluded = new PropertyDiscoveryChildFixture();
        $excluded->inheritedPublic = 'excluded';
        $source = [$allowed, $excluded];
        $context = FixtureGenerationContext::create()->addPersistedObjectConstraint($allowed);
        $innerGenerator = FixtureGeneratorBuilder::create()->setMetadataHandler($handler)->build();
        $innerFixture = '';

        $outerGenerator = FixtureGeneratorBuilder::create()
            ->setReferenceNamer(new CallbackNamer(static function (object $object, int $index) use (
                $source,
                $context,
                $innerGenerator,
                &$innerFixture,
            ): string {
                if ($index === 1) {
                    $innerFixture = $innerGenerator->generate($source, $context);
                }

                return 'outer-' . $index;
            }))
            ->build()
        ;
        $outer = $this->loadFixtures($outerGenerator->generate($source, $context));
        $inner = $this->loadFixtures($innerFixture);

        self::assertSame(['outer-1'], array_keys($outer));
        self::assertSame(['PropertyDiscoveryChildFixture-1'], array_keys($inner));
        $outerObject = $outer['outer-1'];
        $innerObject = $inner['PropertyDiscoveryChildFixture-1'];
        self::assertInstanceOf(PropertyDiscoveryChildFixture::class, $outerObject);
        self::assertSame(PropertyDiscoveryParentFixture::class, $innerObject::class);
        self::assertSame('allowed', $outerObject->inheritedPublic);
        self::assertSame('allowed', $innerObject->inheritedPublic);
        self::assertFalse($context->getConstraintsCollection()->isAllowed($excluded));
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
        $parent = new RequiredRelationFixture(new ValueFixture(), 'parent');
        $parent->dependent = new ValueFixture(new ValueFixture($parent));

        $objects = $this->loadFixtures($this->generate([$parent, new WritablePropertyFixture('kept')]));

        self::assertSame(['WritablePropertyFixture-1'], array_keys($objects));
    }

    #[DataProvider('discardedReferenceExpressions')]
    public function testPrunesAnExplicitReferenceAndKeepsLiteralTextWithTheSameValue(AliceExpression $reference): void
    {
        $context = FixtureGenerationContext::create()->addPropertyTransformRule(new PropertyTransformRule(
            new ClassPropertyMatcher(ValueFixture::class, 'value'),
            new SimpleValueTransformer($reference),
        ));

        $objects = $this->loadFixtures($this->generate([
            new UnwritableStateFixture(),
            new ValueFixture('replace this'),
            new WritablePropertyFixture($reference->syntax),
        ], $context));

        self::assertSame(['WritablePropertyFixture-1'], array_keys($objects));
        self::assertInstanceOf(WritablePropertyFixture::class, $objects['WritablePropertyFixture-1']);
        self::assertSame($reference->syntax, $objects['WritablePropertyFixture-1']->name);
    }

    public static function discardedReferenceExpressions(): iterable
    {
        yield 'direct reference' => [AliceExpression::reference('UnwritableStateFixture-1')];
        yield 'property reference' => [new AliceExpression('@UnwritableStateFixture-1->name')];
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

    public function testCollectionLimitsControlCallbacksAndCountItemsBeforeTraversal(): void
    {
        $pruned = new WritablePropertyFixture('pruned by recursion');
        $source = new ValueFixture(new ArrayCollection([
            $pruned,
            new WritablePropertyFixture('over the item class limit'),
            'skip',
            'keep',
            'over the owner limit',
        ]));
        $callbackItems = [];
        $context = FixtureGenerationContext::create()
            ->setMaximumRecursion(0)
            ->addCollectionItemLimitPerOwner(ValueFixture::class, 2)
            ->addLimitForOwnerItemCollection(ValueFixture::class, WritablePropertyFixture::class, 1)
            ->addCollectionItemSkipCondition(static function (string $owner, mixed $item) use (&$callbackItems): bool {
                $callbackItems[] = [$owner, $item];

                return $item === 'skip';
            })
        ;

        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ValueFixture-1'];

        self::assertSame([
            [ValueFixture::class, $pruned],
            [ValueFixture::class, 'skip'],
            [ValueFixture::class, 'keep'],
        ], $callbackItems);
        self::assertCount(1, $objects);
        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame([1 => 'keep'], $fixture->value);
    }

    public function testOwnerLimitDoesNotApplyToPlainArraysOrRootCollections(): void
    {
        $context = FixtureGenerationContext::create()
            ->addCollectionItemLimitPerOwner(ValueFixture::class, 0)
            ->addCollectionItemSkipCondition(static function (): bool {
                self::fail('The callback should not run for any of these items.');
            })
        ;
        $objects = $this->loadFixtures($this->generate([
            new ValueFixture(new ArrayCollection(['excluded'])),
            new ValueFixture(['plain item']),
            new ArrayCollection([new ValueFixture('root item')]),
        ], $context));

        self::assertSame(['ValueFixture-2', 'ValueFixture-3'], array_keys($objects));
        self::assertInstanceOf(ValueFixture::class, $objects['ValueFixture-2']);
        self::assertInstanceOf(ValueFixture::class, $objects['ValueFixture-3']);
        self::assertSame(['plain item'], $objects['ValueFixture-2']->value);
        self::assertSame('root item', $objects['ValueFixture-3']->value);
    }

    public function testCustomCollectionHandlerTakesPrecedenceOverBuiltInSelection(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->configureObjectHandlerRegistry(static function (ObjectHandlerRegistryInterface $registry): void {
                $registry->registerHandlers([new class() implements ObjectHandlerInterface {
                    public function handle(ValueContext $valueContext): bool
                    {
                        if (!$valueContext->getValue() instanceof ArrayCollection) {
                            return false;
                        }

                        $valueContext->setSourceValue('handled by extension');

                        return true;
                    }
                }]);
            })
            ->build()
        ;
        $context = FixtureGenerationContext::create()->addOwnersWithSkippedCollections(ValueFixture::class);
        $source = new ValueFixture(new ArrayCollection(['excluded by built-in handler']));
        $objects = $this->loadFixtures($generator->generate($source, $context));
        $fixture = $objects['ValueFixture-1'];

        self::assertInstanceOf(ValueFixture::class, $fixture);
        self::assertSame('handled by extension', $fixture->value);
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
        $context = FixtureGenerationContext::create()->addPropertyTransformRule(new PropertyTransformRule(
            new ClassPropertyMatcher(ConstructorDefaultsFixture::class, 'middle'),
            new SimpleValueTransformer('unexpected replacement'),
        ));
        $objects = $this->loadFixtures($this->generate($source, $context));
        $fixture = $objects['ConstructorDefaultsFixture-1'];

        self::assertInstanceOf(ConstructorDefaultsFixture::class, $fixture);
        self::assertSame(['first', '@fallback', 'last'], $fixture->debugState());
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

    public function testUsesAnInitializedDefaultForARenamedConstructorArgument(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setPropertyNamer(new class() implements PropertyNamerInterface {
                public function createName(ValueContext $valueContext): string
                {
                    return 'needed';
                }
            })
            ->build()
        ;

        $objects = $this->loadFixtures($generator->generate([
            new RenamedConstructorFixture('seed'),
            new RenamedConstructorFixture('changed'),
        ], FixtureGenerationContext::create()));
        $default = $objects['RenamedConstructorFixture-1'];
        $changed = $objects['RenamedConstructorFixture-2'];

        self::assertInstanceOf(RenamedConstructorFixture::class, $default);
        self::assertInstanceOf(RenamedConstructorFixture::class, $changed);
        self::assertSame('seed', $default->getLegacy());
        self::assertSame('changed', $changed->getLegacy());
    }

    public function testVisitsAnUnwritableRelationOnlyWhenReachedThroughAnEmittedPath(): void
    {
        $child = new ValueFixture('child');
        $owner = new UnwritableStateFixture();
        $owner->name = 'owner';
        $owner->replaceState($child);

        $alone = $this->loadFixtures($this->generate($owner));
        self::assertSame(['UnwritableStateFixture-1'], array_keys($alone));

        $reachable = $this->loadFixtures($this->generate([$owner, new ValueFixture($child)]));
        self::assertSame(['UnwritableStateFixture-1', 'ValueFixture-1', 'ValueFixture-2'], array_keys($reachable));
        $root = $reachable['ValueFixture-1'];
        self::assertInstanceOf(ValueFixture::class, $root);
        self::assertSame($reachable['ValueFixture-2'], $root->value);
    }

    public function testAppendsSelectedCollectionItemsThroughExactNameAdderCalls(): void
    {
        $source = new CollectionAdderFixture();
        $source->name = 'Batch';
        $source->getProgress()->clear();
        $source->addProgress(new WritablePropertyFixture('Queued'));
        $source->addProgress(new WritablePropertyFixture('Running'));
        $source->addProgress(new WritablePropertyFixture('Filtered'));
        $context = FixtureGenerationContext::create()->addCollectionItemLimitPerOwner(CollectionAdderFixture::class, 2);

        $yaml = $this->generate($source, $context);
        $definition = Yaml::parse($yaml)[CollectionAdderFixture::class]['CollectionAdderFixture-1'];
        self::assertArrayNotHasKey('progress', $definition);
        self::assertSame([
            ['addProgress' => ['@WritablePropertyFixture-1']],
            ['addProgress' => ['@WritablePropertyFixture-2']],
        ], $definition['__calls']);

        $objects = $this->loadFixtures($yaml);
        self::assertCount(3, $objects);
        $batch = $objects['CollectionAdderFixture-1'];
        self::assertInstanceOf(CollectionAdderFixture::class, $batch);
        [$default, $queued, $running] = $batch->getProgress()->getValues();
        self::assertInstanceOf(WritablePropertyFixture::class, $default);
        self::assertSame('Constructor default', $default->name);
        self::assertSame($objects['WritablePropertyFixture-1'], $queued);
        self::assertSame($objects['WritablePropertyFixture-2'], $running);
    }

    public function testCustomCollectionHandlerCanSkipAnExactNameAdder(): void
    {
        $handler = new class() implements ObjectHandlerInterface {
            public int $visits = 0;

            public function handle(ValueContext $valueContext): bool
            {
                if (!$valueContext->getValue() instanceof ArrayCollection) {
                    return false;
                }

                $this->visits++;
                $valueContext->setSkipped(true);

                return true;
            }
        };
        $generator = FixtureGeneratorBuilder::create()
            ->configureObjectHandlerRegistry(static function (ObjectHandlerRegistryInterface $registry) use ($handler): void {
                $registry->registerHandlers([$handler]);
            })
            ->build()
        ;
        $source = new CollectionAdderFixture();
        $source->name = 'Batch';
        $source->getProgress()->clear();
        $source->addProgress(new WritablePropertyFixture('Should be skipped'));
        $context = FixtureGenerationContext::create()->addCollectionItemSkipCondition(static function (): bool {
            self::fail('The custom handler must run before built-in collection selection.');
        });

        $objects = $this->loadFixtures($generator->generate($source, $context));

        self::assertSame(1, $handler->visits);
        self::assertSame(['CollectionAdderFixture-1'], array_keys($objects));
        $batch = $objects['CollectionAdderFixture-1'];
        self::assertInstanceOf(CollectionAdderFixture::class, $batch);
        self::assertCount(1, $batch->getProgress());
    }

    public function testSkipsAnAdderRouteWithIncompatibleSelectedItems(): void
    {
        $source = new CollectionAdderFixture();
        $source->name = 'Batch';
        $source->getProgress()->add(new ValueFixture('Incompatible'));

        $objects = $this->loadFixtures($this->generate($source));
        self::assertSame(['CollectionAdderFixture-1'], array_keys($objects));
        $batch = $objects['CollectionAdderFixture-1'];
        self::assertInstanceOf(CollectionAdderFixture::class, $batch);
        self::assertCount(1, $batch->getProgress());
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

    public function testRestoresAnInitializedNullRequiredByTheConstructor(): void
    {
        $source = new RequiredNullableConstructorFixture(null, 'second');
        $objects = $this->loadFixtures($this->generate($source));
        $fixture = $objects['RequiredNullableConstructorFixture-1'];

        self::assertInstanceOf(RequiredNullableConstructorFixture::class, $fixture);
        self::assertSame([null, 'second'], $fixture->debugState());
    }

    public function testUsesADefaultValuedArrayForTheConstructorOnly(): void
    {
        $source = new RequiredDefaultArrayFixture(['@literal', []]);
        $yaml = $this->generate($source);
        $objects = $this->loadFixtures($yaml);
        $fixture = $objects['RequiredDefaultArrayFixture-1'];

        self::assertInstanceOf(RequiredDefaultArrayFixture::class, $fixture);
        self::assertSame($source->items, $fixture->items);
        self::assertArrayNotHasKey(
            'items',
            Yaml::parse($yaml)[RequiredDefaultArrayFixture::class]['RequiredDefaultArrayFixture-1']
        );
    }

    public function testSkipsAnUnwritableValueWhenConstructorArgumentsAreUnavailable(): void
    {
        $source = new RequiredNullableConstructorFixture(null, 'second');
        $source->unsetFirst();
        $objects = $this->loadFixtures($this->generate($source));

        self::assertSame([], $objects);
    }

    public function testExportsRawPropertiesWhenRequested(): void
    {
        $context = FixtureGenerationContext::create()->setSkipNonWritableProperties(false);
        $source = new RequiredNullableConstructorFixture(null, 'second');
        $source->unsetFirst();
        $yaml = $this->generate($source, $context);

        self::assertSame(
            ['second' => 'second'],
            Yaml::parse($yaml)[RequiredNullableConstructorFixture::class]['RequiredNullableConstructorFixture-1']
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

                        $valueContext->setValue(sprintf(
                            '<(strtoupper(%s))>',
                            var_export((string) $valueContext->getValue(), true),
                        ));

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
        self::assertSame('ALICE', $fixture->value);
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

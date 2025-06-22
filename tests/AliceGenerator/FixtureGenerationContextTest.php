<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Solitus0\AliceGenerator\Exception\InvalidArgumentException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;

class FixtureGenerationContextTest extends TestCase
{
    public function testSkippedCollectionOwnerClassesCanBeAddedAndChecked(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->addSkippedCollectionOwnerClasses(['Foo', 'Bar'])
        ;

        $this->assertSame(['Foo', 'Bar'], $fixtureGenerationContext->getSkippedCollectionOwnerClasses());
        $this->assertTrue($fixtureGenerationContext->shouldSkipCollectionsForOwnerClass('Foo'));
        $this->assertFalse($fixtureGenerationContext->shouldSkipCollectionsForOwnerClass('Baz'));
    }

    public function testAddSkippedCollectionItemCallbackAndFiltering(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->addSkippedCollectionItemCallback(
                fn (string $ownerClass, $item): bool => $ownerClass === 'A' && $item === 'skip'
            )
            ->addSkippedCollectionItemCallback(
                fn (string $ownerClass, $item): bool => $ownerClass === 'B' && is_int($item) && $item < 0
            )
        ;

        // owner A, item 'skip' should be skipped
        $this->assertTrue($fixtureGenerationContext->shouldSkipCollectionItem('A', 'skip'));
        // owner A, other items not skipped by first callback
        $this->assertFalse($fixtureGenerationContext->shouldSkipCollectionItem('A', 'keep'));
        // owner B, negative int should be skipped
        $this->assertTrue($fixtureGenerationContext->shouldSkipCollectionItem('B', -1));
        // owner B, positive int not skipped
        $this->assertFalse($fixtureGenerationContext->shouldSkipCollectionItem('B', 2));
        // owner C, no callbacks apply
        $this->assertFalse($fixtureGenerationContext->shouldSkipCollectionItem('C', 'any'));
    }

    public function testCollectionSizeLimitMethodsAndValidation(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->setCollectionSizeLimit('Foo', 3)
        ;

        $this->assertTrue($fixtureGenerationContext->shouldLimitCollectionSizeForOwnerClass('Foo'));
        $this->assertSame(3, $fixtureGenerationContext->getCollectionSizeLimitForOwnerClass('Foo'));
        $this->assertFalse($fixtureGenerationContext->shouldLimitCollectionSizeForOwnerClass('Bar'));

        $this->assertSame(['Foo' => 3], $fixtureGenerationContext->getCollectionSizeLimits());

        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->setCollectionSizeLimit('Foo', -1);
    }

    public function testCollectionItemSizeLimitMethodsAndValidation(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->setCollectionItemSizeLimit('O', 'I', 2)
            ->setCollectionItemSizeLimit('O', 'J', 1)
        ;

        $this->assertTrue($fixtureGenerationContext->shouldLimitCollectionItemSizeFor('O', 'I'));
        $this->assertSame(2, $fixtureGenerationContext->getCollectionItemSizeLimitFor('O', 'I'));
        $this->assertTrue($fixtureGenerationContext->shouldLimitCollectionItemSizeFor('O', 'J'));
        $this->assertSame(1, $fixtureGenerationContext->getCollectionItemSizeLimitFor('O', 'J'));
        $this->assertFalse($fixtureGenerationContext->shouldLimitCollectionItemSizeFor('O', 'K'));

        $this->assertSame([
            'O' => ['I' => 2, 'J' => 1],
        ], $fixtureGenerationContext->getCollectionItemSizeLimits());

        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->setCollectionItemSizeLimit('Foo', 'Bar', -1);
    }
}

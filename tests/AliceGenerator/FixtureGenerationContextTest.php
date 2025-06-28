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
            ->addOwnersWithSkippedCollections(['Foo', 'Bar'])
        ;

        $this->assertSame(['Foo', 'Bar'], $fixtureGenerationContext->getSkipCollectionsOwnedBy());
        $this->assertTrue($fixtureGenerationContext->shouldSkipCollectionsOwnedBy('Foo'));
        $this->assertFalse($fixtureGenerationContext->shouldSkipCollectionsOwnedBy('Baz'));
    }

    public function testaddCollectionItemSkipConditionAndFiltering(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->addCollectionItemSkipCondition(
                fn (string $ownerClass, $item): bool => $ownerClass === 'A' && $item === 'skip'
            )
            ->addCollectionItemSkipCondition(
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
            ->addCollectionItemLimitPerOwner('Foo', 3)
        ;

        $this->assertTrue($fixtureGenerationContext->hasCollectionItemLimitForOwner('Foo'));
        $this->assertSame(3, $fixtureGenerationContext->getItemLimitForOwnerCollections('Foo'));
        $this->assertFalse($fixtureGenerationContext->hasCollectionItemLimitForOwner('Bar'));

        $this->assertSame(['Foo' => 3], $fixtureGenerationContext->getCollectionItemLimitPerOwner());

        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addCollectionItemLimitPerOwner('Foo', -1);
    }

    public function testCollectionItemSizeLimitMethodsAndValidation(): void
    {
        $fixtureGenerationContext = FixtureGenerationContext::create()
            ->addLimitForOwnerItemCollection('O', 'I', 2)
            ->addLimitForOwnerItemCollection('O', 'J', 1)
        ;

        $this->assertTrue($fixtureGenerationContext->shouldLimitCollectionItemSize('O', 'I'));
        $this->assertSame(2, $fixtureGenerationContext->getCollectionItemSizeLimit('O', 'I'));
        $this->assertTrue($fixtureGenerationContext->shouldLimitCollectionItemSize('O', 'J'));
        $this->assertSame(1, $fixtureGenerationContext->getCollectionItemSizeLimit('O', 'J'));
        $this->assertFalse($fixtureGenerationContext->shouldLimitCollectionItemSize('O', 'K'));

        $this->assertSame([
            'O' => ['I' => 2, 'J' => 1],
        ], $fixtureGenerationContext->getCollectionItemLimitPerOwnerItem());

        $this->expectException(InvalidArgumentException::class);
        FixtureGenerationContext::create()->addLimitForOwnerItemCollection('Foo', 'Bar', -1);
    }
}

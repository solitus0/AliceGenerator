<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\Exception\InvalidArgumentException;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\ReferenceNamer\ClassNamer;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\Storage\ObjectConstraintsCollection;

class FixtureGenerationContext
{
    private int $maximumRecursion = 5;

    private readonly ObjectConstraintsCollection $constraintsCollection;

    private ReferenceNamerInterface $referenceNamer;

    private bool $excludeDefaultValues = true;

    private bool $sortResults = true;

    /**
     * @var string[] Classes whose Doctrine Collection properties will be skipped
     */
    private array $skippedCollectionOwnerClasses = [];

    /**
     * @var callable[] Callbacks to skip individual collection items: function(string $ownerClass, mixed $item): bool
     */
    private array $skippedCollectionItemCallbacks = [];

    /**
     * @var int[] Maximum number of collection items to include per owner class
     */
    private array $collectionSizeLimits = [];

    /**
     * @var int[][] Maximum number of collection items per owner and item class
     *            [ ownerClass => [ itemClass => limit, ... ], ... ]
     */
    private array $collectionItemSizeLimits = [];

    public function __construct()
    {
        $this->referenceNamer = new ClassNamer();
        $this->constraintsCollection = new ObjectConstraintsCollection();
        $this->constraintsCollection->setMetadataHandler(new NonSpecificMetadataHandler());
    }

    public static function create(): self
    {
        return new self();
    }

    public function getMaximumRecursion(): int
    {
        return $this->maximumRecursion;
    }

    public function setMaximumRecursion(int $max): static
    {
        $this->maximumRecursion = $max;

        return $this;
    }

    public function addPersistedObjectConstraint(array|object $objects): static
    {
        $objects = is_array($objects) ? $objects : [$objects];

        foreach ($objects as $object) {
            if (!is_object($object)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Non-object passed to addPersistedObjectConstraint() - "%s" given',
                        gettype($object)
                    )
                );
            }

            $this->getConstraintsCollection()->addConstraint($object);
        }

        return $this;
    }

    public function getConstraintsCollection(): ObjectConstraintsCollection
    {
        return $this->constraintsCollection;
    }

    public function getReferenceNamer(): ReferenceNamerInterface
    {
        return $this->referenceNamer;
    }

    public function setReferenceNamer(ReferenceNamerInterface $referenceNamer): static
    {
        $this->referenceNamer = $referenceNamer;

        return $this;
    }

    public function isExcludeDefaultValuesEnabled(): bool
    {
        return $this->excludeDefaultValues;
    }

    public function setExcludeDefaultValues(bool $excludeDefaultValues): static
    {
        $this->excludeDefaultValues = $excludeDefaultValues;

        return $this;
    }

    public function isSortResultsEnabled(): bool
    {
        return $this->sortResults;
    }

    public function setSortResults(bool $sortResults): static
    {
        $this->sortResults = $sortResults;

        return $this;
    }

    /**
     * Add owner class(es) for which Doctrine Collection properties should be skipped.
     */
    public function addSkippedCollectionOwnerClasses(string|array $classes): static
    {
        $classes = is_array($classes) ? $classes : [$classes];
        foreach ($classes as $class) {
            if (!is_string($class)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Non-string passed to addSkippedCollectionOwnerClasses() - "%s" given',
                        gettype($class)
                    )
                );
            }

            $this->skippedCollectionOwnerClasses[] = $class;
        }

        return $this;
    }

    /**
     * Returns classes configured to skip Doctrine Collection properties.
     *
     * @return string[]
     */
    public function getSkippedCollectionOwnerClasses(): array
    {
        return $this->skippedCollectionOwnerClasses;
    }

    /**
     * Whether to skip Doctrine Collection properties on the given owner class.
     */
    public function shouldSkipCollectionsForOwnerClass(string $ownerClass): bool
    {
        return in_array($ownerClass, $this->skippedCollectionOwnerClasses, true);
    }

    /**
     * Register a callback that returns true if a collection item on an owner class should be skipped.
     *
     * Signature: function(string $ownerClass, mixed $item): bool
     *
     * @return $this
     */
    public function addSkippedCollectionItemCallback(callable $callback): static
    {
        $this->skippedCollectionItemCallbacks[] = $callback;

        return $this;
    }

    /**
     * Whether a given collection item on an owner class should be skipped.
     */
    public function shouldSkipCollectionItem(string $ownerClass, mixed $item): bool
    {
        foreach ($this->skippedCollectionItemCallbacks as $skippedCollectionItemCallback) {
            if ($skippedCollectionItemCallback($ownerClass, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set a limit for the number of items included in Doctrine Collection properties on a specific owner class.
     *
     * @return $this
     */
    public function setCollectionSizeLimit(string $ownerClass, int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Limit must be a non-negative integer, %d given',
                    $limit
                )
            );
        }

        $this->collectionSizeLimits[$ownerClass] = $limit;

        return $this;
    }

    /**
     * Get all configured collection size limits per owner class.
     *
     * @return int[] keyed by owner class name
     */
    public function getCollectionSizeLimits(): array
    {
        return $this->collectionSizeLimits;
    }

    /**
     * Whether a size limit has been configured for this owner class.
     */
    public function shouldLimitCollectionSizeForOwnerClass(string $ownerClass): bool
    {
        return isset($this->collectionSizeLimits[$ownerClass]);
    }

    /**
     * Get the configured size limit for a given owner class, or null if none.
     *
     */
    public function getCollectionSizeLimitForOwnerClass(string $ownerClass): ?int
    {
        return $this->collectionSizeLimits[$ownerClass] ?? null;
    }

    /**
     * Set a limit for the number of a specific item class to include in Doctrine Collection properties
     * on a given owner class.
     *
     * @return $this
     */
    public function setCollectionItemSizeLimit(string $ownerClass, string $itemClass, int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Limit must be a non-negative integer, %d given',
                    $limit
                )
            );
        }

        $this->collectionItemSizeLimits[$ownerClass][$itemClass] = $limit;

        return $this;
    }

    /**
     * Get all configured collection item size limits, nested per owner class.
     *
     * @return int[][] [ ownerClass => [ itemClass => limit, ... ], ... ]
     */
    public function getCollectionItemSizeLimits(): array
    {
        return $this->collectionItemSizeLimits;
    }

    /**
     * Whether a size limit has been configured for the given owner & item class.
     */
    public function shouldLimitCollectionItemSizeFor(string $ownerClass, string $itemClass): bool
    {
        return isset($this->collectionItemSizeLimits[$ownerClass][$itemClass]);
    }

    /**
     * Get the configured size limit for the given owner & item class, or null if none.
     *
     */
    public function getCollectionItemSizeLimitFor(string $ownerClass, string $itemClass): ?int
    {
        return $this->collectionItemSizeLimits[$ownerClass][$itemClass] ?? null;
    }
}

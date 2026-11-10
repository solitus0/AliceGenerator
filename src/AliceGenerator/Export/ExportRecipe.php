<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGenerationPolicy;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\MetadataHandler\DoctrineMetadataHandler;

/**
 * @experimental
 * @phpstan-import-type SelectedFixture from FixtureGenerationPolicy
 */
final class ExportRecipe
{
    public const MAX_YAML_BYTES = 8_388_608;

    /** @var \WeakMap<object, string> */
    private readonly \WeakMap $aliases;

    /** @var array<string, SelectedFixture> */
    private array $records = [];

    /**
     * @param array<string, array{object: object, values: array<string, mixed>}> $records
     * @param \Closure(string): array<string, object> $load In-memory Alice loader
     */
    public function __construct(
        private readonly EntityManagerInterface $manager,
        array $records,
        private readonly \Closure $load,
        private readonly int $maxObjects = 100,
        private readonly int $maxYamlBytes = 1_048_576,
    ) {
        if ($records === [] || $maxObjects < 1 || $maxYamlBytes < 1 || $maxYamlBytes > self::MAX_YAML_BYTES) {
            throw new ExportFailure('invalid_selection');
        }
        if (count($records) > $maxObjects) {
            throw new ExportFailure('selection_limit_exceeded');
        }

        $this->aliases = new \WeakMap();
        foreach ($records as $alias => ['object' => $object]) {
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/', $alias) !== 1 || isset($this->aliases[$object])) {
                throw new ExportFailure('invalid_selection');
            }
            $this->aliases[$object] = $alias;
        }
        foreach ($records as $alias => ['object' => $object, 'values' => $values]) {
            $metadata = $manager->getClassMetadata(ClassUtils::getClass($object));
            $class = $metadata->getName();
            $links = [];
            foreach ($values as $field => $value) {
                if (str_starts_with($field, '__') || !property_exists($class, $field)) {
                    throw new ExportFailure('invalid_selection');
                }
                if ($metadata->hasField($field)) {
                    $this->assertLiteral($value);
                } elseif ($metadata->isSingleValuedAssociation($field)) {
                    if ($value !== null) {
                        $target = $this->targetAlias($value, $metadata->getAssociationTargetClass($field));
                        if (!$metadata->isAssociationInverseSide($field)) {
                            $links[$field] = $target;
                        }
                    }
                } elseif ($metadata->isCollectionValuedAssociation($field) && is_array($value)) {
                    foreach ($value as $item) {
                        $this->targetAlias($item, $metadata->getAssociationTargetClass($field));
                    }
                } else {
                    throw new ExportFailure('invalid_selection');
                }
            }
            $this->records[$alias] = compact('object', 'class', 'values', 'links');
        }
    }

    /**
     * @internal
     * @return array{objects: int, classes: array<class-string, int>, properties: array<class-string, list<string>>}
     */
    public function summary(): array
    {
        $classes = array_count_values(array_column($this->records, 'class'));
        ksort($classes);
        $properties = array_fill_keys(array_keys($classes), []);
        foreach ($this->records as ['class' => $class, 'values' => $values]) {
            foreach (array_keys($values) as $property) {
                $properties[$class][$property] = true;
            }
        }
        $properties = array_map(static function (array $selected): array {
            $names = array_keys($selected);
            sort($names, SORT_STRING);

            return $names;
        }, $properties);

        return ['objects' => count($this->records), 'classes' => $classes, 'properties' => $properties];
    }

    /** @internal */
    public function generate(): string
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setMetadataHandler(new DoctrineMetadataHandler($this->manager, skipIdentifiers: false, skipEmptyStrings: false))
            ->build()
        ;
        $context = FixtureGenerationContext::create()
            ->setMaximumRecursion($this->maxObjects)
            ->setSkipNonWritableProperties(true)
            ->setGenerationPolicy(new FixtureGenerationPolicy($this->records, $this->aliases))
        ;
        $yaml = $generator->generate(array_column($this->records, 'object'), $context);
        if (strlen($yaml) > $this->maxYamlBytes) {
            throw new ExportFailure('artifact_limit_exceeded');
        }

        return $yaml;
    }

    /** @internal */
    public function validate(string $yaml): void
    {
        $loaded = ($this->load)($yaml);
        if (count($loaded) !== count($this->records)) {
            throw new ExportFailure('fixture_set_mismatch');
        }
        $seen = new \WeakMap();
        foreach ($this->records as $alias => ['class' => $class, 'links' => $links]) {
            $object = $loaded[$alias] ?? null;
            if (!$object instanceof $class || isset($this->aliases[$object]) || isset($seen[$object])) {
                throw new ExportFailure('fixture_set_mismatch');
            }
            $seen[$object] = true;
            $metadata = $this->manager->getClassMetadata($class);
            foreach ($links as $field => $target) {
                if ($metadata->getFieldValue($object, $field) !== ($loaded[$target] ?? null)) {
                    throw new ExportFailure('required_link_mismatch');
                }
            }
        }
    }

    private function assertLiteral(mixed $value, int $depth = 0): void
    {
        if (is_array($value)) {
            if ($depth >= 32) {
                throw new ExportFailure('unsupported_field_value');
            }
            foreach ($value as $item) {
                $this->assertLiteral($item, $depth + 1);
            }

            return;
        }

        if ($value !== null && !is_scalar($value) && !$value instanceof \DateTimeInterface && !$value instanceof \UnitEnum) {
            throw new ExportFailure('unsupported_field_value');
        }
    }

    private function targetAlias(mixed $value, string $class): string
    {
        if (!$value instanceof $class) {
            throw new ExportFailure('forbidden_reference');
        }

        return $this->aliases[$value] ?? throw new ExportFailure('forbidden_reference');
    }
}

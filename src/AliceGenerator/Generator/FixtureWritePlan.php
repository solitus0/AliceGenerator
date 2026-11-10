<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Doctrine\Common\Collections\Collection;
use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\ValueContext;

/**
 * @internal
 * @phpstan-type PropertyWrite array{source: string, value: mixed}
 * @phpstan-type ConstructorCandidate array{position: int, property: string, source: string, value: mixed}
 */
final class FixtureWritePlan
{
    /** @var array<string, PropertyWrite> */
    private array $properties = [];

    /** @var list<ConstructorCandidate> */
    private array $constructorCandidates = [];

    /** @var array<string, PropertyWrite> */
    private array $defaultConstructorValues = [];

    /** @var list<array{method: string, source: string|null, value: mixed}> */
    private array $setterCandidates = [];

    private bool $prunedGraphValue = false;

    private bool $hasDeferredStringables = false;

    public function __construct(
        private readonly object $object,
        private readonly \ReflectionClass $reflectionClass,
        private readonly FixtureGenerationContext $generationContext,
        private readonly FixtureWriteRouteResolver $resolver,
    ) {
    }

    public function hasRequiredConstructorArguments(): bool
    {
        return $this->reflectionClass->getConstructor()?->getNumberOfRequiredParameters() > 0;
    }

    private function canSupplyDefaultValue(string $propertyName): bool
    {
        foreach ($this->reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() === $propertyName) {
                return !$parameter->isOptional();
            }
        }

        return false;
    }

    /**
     * @param \Closure(ValueContext, bool): void $visit
     * @param \Closure(ValueContext, string|null): bool $visitUnwritableCollection
     */
    public function addProperty(
        PreparedProperty $property,
        \Closure $visit,
        \Closure $visitUnwritableCollection,
    ): void {
        $reflectionProperty = $property->reflection;
        $propertyName = $property->name;
        $valueContext = $property->context;
        $constructorOnly = $property->constructorOnly;

        if ($constructorOnly && !$this->canSupplyDefaultValue($propertyName)) {
            return;
        }

        if ($propertyName === '' || $propertyName === '0') {
            throw new InvalidPropertyNameException('Property name must be a non empty string.');
        }

        $route = $this->resolver->resolve($this->object, $this->reflectionClass, $reflectionProperty, $propertyName);
        if ($route['type'] === 'skip' && $this->generationContext->shouldSkipNonWritableProperties()) {
            if (!$constructorOnly && $property->needsTraversal) {
                $this->planUnwritableCollection($propertyName, $valueContext, $visitUnwritableCollection);
            }

            return;
        }

        if ($property->needsTraversal) {
            $valueContext->setStringableWriteMode(fn (bool $arrayItem): StringableWriteMode => $this->stringableMode(
                $reflectionProperty,
                $propertyName,
                $route,
                $constructorOnly,
                $arrayItem,
            ));
            $visit($valueContext, $property->preserveEmptyArrays);
            if ($valueContext->isSkipped()) {
                $this->prunedGraphValue = $this->prunedGraphValue || $property->sourceIsGraphValue;

                return;
            }
        }

        $write = ['source' => $reflectionProperty->getName(), 'value' => $valueContext->getUnencodedValue()];

        if ($constructorOnly) {
            if ($route['type'] === 'property' || $route['type'] === 'constructor') {
                $this->defaultConstructorValues[$propertyName] = $write;
            }

            return;
        }

        switch ($route['type']) {
            case 'property':
            case 'skip':
                $this->properties[$propertyName] = $write;

                break;
            case 'constructor':
                $this->constructorCandidates[] = [
                    'position' => $route['position'],
                    'property' => $propertyName,
                ] + $write;

                break;
            case 'setter':
                $this->setterCandidates[] = ['method' => $route['method']] + $write;

                break;
        }
    }

    /** @param array{type: 'property'|'constructor'|'setter'|'skip', position?: int, method?: string} $route */
    private function stringableMode(
        \ReflectionProperty $property,
        string $name,
        array $route,
        bool $constructorOnly,
        bool $arrayItem,
    ): StringableWriteMode {
        $class = $this->reflectionClass;
        if ($constructorOnly && ($route['type'] === 'property' || $route['type'] === 'constructor')) {
            $text = $this->resolver->requiredConstructorAcceptsStringable($class, $name, $arrayItem);

            return $text ? StringableWriteMode::Text : StringableWriteMode::Reference;
        }

        $primaryText = $this->resolver->acceptsStringableAtRoute($class, $property, $name, $route, $arrayItem);
        $fallbackText = $primaryText;
        if ($route['type'] === 'constructor') {
            $setter = $this->resolver->findSetterMethod($class, $name);
            if ($setter !== null) {
                $fallbackText = $this->resolver->setterAcceptsStringable($class, $setter, $arrayItem);
            }
        } elseif ($route['type'] === 'property' && $primaryText) {
            // A required argument can reuse a writable property's value.
            $primaryText = $this->resolver->requiredConstructorAcceptsStringable($class, $name, $arrayItem) !== false;
        }

        $mode = StringableWriteMode::forRoutes($primaryText, $fallbackText);
        $this->hasDeferredStringables = $this->hasDeferredStringables || $mode->dependsOnConstructor();

        return $mode;
    }

    /** @param \Closure(ValueContext, string|null): bool $visit */
    private function planUnwritableCollection(string $propertyName, ValueContext $valueContext, \Closure $visit): void
    {
        $collection = $valueContext->getUnencodedValue();
        $adder = $collection instanceof Collection
            ? $this->resolver->findCollectionAdder($this->reflectionClass, $propertyName)
            : null;
        if (!$visit($valueContext, $adder['itemType'] ?? null) || $adder === null || $valueContext->isSkipped()) {
            return;
        }

        $arguments = $valueContext->getUnencodedValue();
        if (is_array($arguments)) {
            foreach ($arguments as $argument) {
                $this->setterCandidates[] = ['method' => $adder['method'], 'source' => null, 'value' => $argument];
            }
        }
    }

    public function buildDefinition(string $class): ?FinalizedFixtureWrites
    {
        $definition = [];
        $owningReferences = [];
        $properties = $this->properties;
        $setterCandidates = $this->setterCandidates;
        $constructorCandidates = $this->constructorCandidates;
        $constructorArguments = $this->buildConstructorArguments($constructorCandidates, $properties);
        if ($this->hasDeferredStringables) {
            // Check whether the reference writes survive before converting any constructor text.
            foreach ([false, true] as $resolveText) {
                if ($constructorArguments === null) {
                    break;
                }

                $constructorCandidates = $this->resolveWrites($constructorCandidates, true, $resolveText);
                $properties = $this->resolveWrites($properties, true, $resolveText);
                $constructorArguments = $this->buildConstructorArguments($constructorCandidates, $properties);
            }
        }

        $policy = $this->generationContext->getGenerationPolicy();
        if ($constructorArguments === null && $this->hasRequiredConstructorArguments()) {
            $policy->missingRequiredConstructor();
        }

        if ($constructorArguments !== null) {
            $definition['__construct'] = $constructorArguments;
            if (!array_key_exists('__construct', $properties)) {
                $this->recordConstructorReferences(
                    $owningReferences,
                    $constructorArguments,
                    $constructorCandidates,
                    $properties + $this->defaultConstructorValues,
                );
            }
        } else {
            if ($this->hasDeferredStringables) {
                $properties = $this->resolveWrites($this->properties, useConstructor: false);
            }
            foreach ($this->constructorCandidates as $candidate) {
                $property = $candidate['property'];
                $setterMethod = $this->resolver->findSetterMethod($this->reflectionClass, $property);
                if ($setterMethod === null && $this->generationContext->shouldSkipNonWritableProperties()) {
                    continue;
                }

                $write = ['source' => $candidate['source'], 'value' => $candidate['value']];
                if ($this->hasDeferredStringables) {
                    $write = $this->resolveWrite($write, useConstructor: false, resolveText: true);
                    if ($write === null) {
                        continue;
                    }
                }

                if ($setterMethod !== null) {
                    $setterCandidates[] = ['method' => $setterMethod] + $write;
                } else {
                    $properties[$property] = $write;
                }
            }

            if ($this->hasRequiredConstructorArguments()) {
                if ($setterCandidates !== [] && $this->generationContext->allowsConstructorBypassFor($class)) {
                    $definition['__construct'] = false;
                } elseif ($this->prunedGraphValue) {
                    return null;
                }
            }
        }

        foreach ($properties as $property => $write) {
            $definition[$property] = $write['value'];
            if ($property !== '__calls' || $setterCandidates === []) {
                $this->recordReference($owningReferences, $write['value'], $write['source'], $property);
            }
        }

        if ($setterCandidates !== []) {
            $definition['__calls'] = [];
            foreach ($setterCandidates as ['method' => $method, 'source' => $source, 'value' => $value]) {
                $definition['__calls'][] = [$method => [$value]];
                if ($source === null) {
                    continue;
                }

                // Preserve the destination match for conventional setters as well as the source field.
                $suffix = str_starts_with($method, 'set') ? substr($method, 3) : null;
                $destination = $suffix !== null && ucfirst($suffix) === $suffix ? $suffix : null;
                $this->recordReference(
                    $owningReferences,
                    $value,
                    $source,
                    $destination,
                    $destination === null ? null : lcfirst($destination),
                );
            }
        }

        return $policy->finalizeWrites($definition, $owningReferences);
    }

    /**
     * @param array<int, ConstructorCandidate> $candidates
     * @param array<string, PropertyWrite> $properties
     * @return array<int, mixed>|null
     */
    private function buildConstructorArguments(array $candidates, array $properties): ?array
    {
        return $this->resolver->buildConstructorArguments(
            $this->reflectionClass,
            array_map(fn (array $candidate): array => [
                'position' => $candidate['position'],
                'property' => $candidate['property'],
                'value' => $this->constructorProbeValue($candidate['value']),
            ], $candidates),
            array_map(fn (array $write): mixed => $this->constructorProbeValue($write['value']), $properties)
                + array_map(static fn (array $write): mixed => $write['value'], $this->defaultConstructorValues),
        );
    }

    private function constructorProbeValue(mixed $value): mixed
    {
        if (!$this->hasDeferredStringables) {
            return $value;
        }

        return match (true) {
            $value instanceof DeferredStringable => $value->source,
            is_array($value) => array_map($this->constructorProbeValue(...), $value),
            default => $value,
        };
    }

    /**
     * @template TKey of array-key
     * @template TWrite of PropertyWrite|ConstructorCandidate
     * @param array<TKey, TWrite> $writes
     * @return array<TKey, TWrite>
     */
    private function resolveWrites(array $writes, bool $useConstructor, bool $resolveText = true): array
    {
        foreach ($writes as $key => $write) {
            $resolved = $this->resolveWrite($write, $useConstructor, $resolveText);
            if ($resolved === null) {
                unset($writes[$key]);
            } else {
                $writes[$key] = $resolved;
            }
        }

        return $writes;
    }

    /**
     * @template TWrite of PropertyWrite|ConstructorCandidate
     * @param TWrite $write
     * @return TWrite|null
     */
    private function resolveWrite(array $write, bool $useConstructor, bool $resolveText): ?array
    {
        $skipped = false;
        $write['value'] = $this->resolveValue($write['value'], $useConstructor, $resolveText, $skipped);
        if ($skipped) {
            $this->prunedGraphValue = true;

            return null;
        }

        return $write;
    }

    private function resolveValue(mixed $value, bool $useConstructor, bool $resolveText, bool &$skipped): mixed
    {
        if ($value instanceof DeferredStringable) {
            $result = $value->resolve($useConstructor, $resolveText);
            $skipped = $result === null;

            return $result;
        }

        if (!is_array($value) || $value === []) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $itemSkipped = false;
            $value[$key] = $this->resolveValue($item, $useConstructor, $resolveText, $itemSkipped);
            if ($itemSkipped) {
                unset($value[$key]);
            }
        }
        $skipped = $value === [];

        return $value;
    }

    /**
     * @param array<string, array<array-key, true>> $references
     * @param array<int, mixed> $arguments
     * @param array<int, ConstructorCandidate> $constructorCandidates
     * @param array<string, PropertyWrite> $writableProperties
     */
    private function recordConstructorReferences(
        array &$references,
        array $arguments,
        array $constructorCandidates,
        array $writableProperties,
    ): void {
        $candidates = array_column($constructorCandidates, null, 'position');
        foreach ($this->reflectionClass->getConstructor()?->getParameters() ?? [] as $position => $parameter) {
            $name = $parameter->getName();
            $value = $arguments[$position] ?? null;
            $write = $candidates[$position] ?? ($parameter->isOptional() ? null : ($writableProperties[$name] ?? null));
            $source = $write !== null && $write['value'] === $value ? $write['source'] : null;
            $this->recordReference($references, $value, $name, $source);
        }
    }

    /** @param array<string, array<array-key, true>> $references */
    private function recordReference(array &$references, mixed $value, ?string ...$fields): void
    {
        if (!$value instanceof AliceExpression || !str_starts_with($value->syntax, '@')) {
            return;
        }

        $target = substr($value->syntax, 1);
        foreach ($fields as $field) {
            if ($field !== null) {
                $references[$field][$target] = true;
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\Exception\InvalidPropertyNameException;
use Solitus0\AliceGenerator\ValueContext;

/** @internal */
final class FixtureWritePlan
{
    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var list<array{position: int, property: string, value: mixed}> */
    private array $constructorCandidates = [];

    /** @var array<string, mixed> */
    private array $defaultConstructorValues = [];

    /** @var list<array{method: string, value: mixed}> */
    private array $setterCandidates = [];

    private bool $prunedGraphValue = false;

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

    /** @param (\Closure(ValueContext, bool): void)|null $visit */
    public function addProperty(
        \ReflectionProperty $reflectionProperty,
        string $propertyName,
        ValueContext $valueContext,
        bool $constructorOnly,
        ?\Closure $visit,
    ): void {
        if ($constructorOnly && !$this->canSupplyDefaultValue($propertyName)) {
            return;
        }

        if ($propertyName === '' || $propertyName === '0') {
            throw new InvalidPropertyNameException('Property name must be a non empty string.');
        }

        $route = $this->resolver->resolve($this->object, $this->reflectionClass, $reflectionProperty, $propertyName);
        if ($route['type'] === 'skip' && $this->generationContext->shouldSkipNonWritableProperties()) {
            return;
        }

        if ($visit !== null) {
            $source = $valueContext->getUnencodedValue();
            $valueContext->setStringableWriteCheck(fn (bool $arrayItem): bool => $this->resolver->acceptsStringableAtRoute(
                $this->reflectionClass,
                $reflectionProperty,
                $propertyName,
                $route,
                $arrayItem,
            ));
            $visit($valueContext, $constructorOnly);
            if ($valueContext->isSkipped()) {
                $this->prunedGraphValue = $this->prunedGraphValue || is_object($source) || is_array($source);

                return;
            }
        }

        $value = $valueContext->getUnencodedValue();

        if ($constructorOnly) {
            if ($route['type'] === 'property' || $route['type'] === 'constructor') {
                $this->defaultConstructorValues[$propertyName] = $value;
            }

            return;
        }

        switch ($route['type']) {
            case 'property':
                $this->properties[$propertyName] = $value;

                break;
            case 'constructor':
                $this->constructorCandidates[] = [
                    'position' => $route['position'],
                    'property' => $propertyName,
                    'value' => $value,
                ];

                break;
            case 'setter':
                $this->setterCandidates[] = ['method' => $route['method'], 'value' => $value];

                break;
            case 'skip':
                $this->properties[$propertyName] = $value;

                break;
        }
    }

    /** @return array<string, mixed> */
    public function buildDefinition(string $class): array
    {
        $definition = [];
        $properties = $this->properties;
        $setterCandidates = $this->setterCandidates;

        $constructorArguments = $this->resolver->buildConstructorArguments(
            $this->reflectionClass,
            $this->constructorCandidates,
            $properties + $this->defaultConstructorValues,
        );
        if ($constructorArguments !== null) {
            $definition['__construct'] = $constructorArguments;
        } else {
            foreach ($this->constructorCandidates as $candidate) {
                $setterMethod = $this->resolver->findSetterMethod($this->reflectionClass, $candidate['property']);
                if ($setterMethod !== null) {
                    $setterCandidates[] = ['method' => $setterMethod, 'value' => $candidate['value']];
                } elseif (!$this->generationContext->shouldSkipNonWritableProperties()) {
                    $properties[$candidate['property']] = $candidate['value'];
                }
            }

            if ($this->hasRequiredConstructorArguments()) {
                if ($setterCandidates !== [] && $this->generationContext->allowsConstructorBypassFor($class)) {
                    $definition['__construct'] = false;
                } elseif ($this->prunedGraphValue) {
                    return [];
                }
            }
        }

        foreach ($properties as $property => $value) {
            $definition[$property] = $value;
        }

        if ($setterCandidates !== []) {
            $definition['__calls'] = array_map(
                static fn (array $candidate): array => [$candidate['method'] => [$candidate['value']]],
                $setterCandidates
            );
        }

        return $definition;
    }
}

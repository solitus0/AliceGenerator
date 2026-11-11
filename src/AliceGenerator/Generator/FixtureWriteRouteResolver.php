<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;

/**
 * @phpstan-type ConstructorValue array{source: string|null, value: mixed}
 * @phpstan-type PlannedConstructorValue array{position: int, property: string, source: string, value: mixed}
 * @phpstan-type ConstructorArgument array{parameter: string|null, source: string|null, value: mixed}
 */
class FixtureWriteRouteResolver
{
    private PropertyAccessorInterface $propertyAccessor;

    private ?ReflectionExtractor $writeInfoExtractor = null;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /** @internal */
    public function createPlan(
        object $object,
        \ReflectionClass $reflectionClass,
        FixtureGenerationContext $generationContext,
    ): FixtureWritePlan {
        return new FixtureWritePlan($object, $reflectionClass, $generationContext, $this);
    }

    /**
     * @return array{type: 'property'|'constructor'|'setter'|'skip', position?: int, method?: string}
     */
    public function resolve(
        object $object,
        \ReflectionClass $reflectionClass,
        \ReflectionProperty $reflectionProperty,
        string $propertyName
    ): array {
        $destination = $this->getDestinationProperty($reflectionClass, $reflectionProperty, $propertyName);
        if ($destination?->isPublic() && !$destination->isReadOnly()) {
            return ['type' => 'property'];
        }

        $constructorPosition = $this->findConstructorParameterPosition($reflectionClass, $propertyName);
        if ($constructorPosition !== null) {
            return ['type' => 'constructor', 'position' => $constructorPosition];
        }

        if ($this->propertyAccessor->isWritable($object, $propertyName)) {
            $setter = $this->findPropertySetter($reflectionClass, $propertyName);

            return ['type' => $setter?->isStatic() ? 'skip' : 'property'];
        }

        $setterMethod = $this->findSetterMethod($reflectionClass, $propertyName);
        if ($setterMethod !== null) {
            return ['type' => 'setter', 'method' => $setterMethod];
        }

        // A custom namer may intentionally rename a public property for a custom output generator.
        if ($destination === null && $reflectionProperty->isPublic() && !$reflectionProperty->isReadOnly()) {
            return ['type' => 'property'];
        }

        return ['type' => 'skip'];
    }

    public function acceptsStringable(
        object $object,
        \ReflectionClass $class,
        \ReflectionProperty $property,
        bool $arrayItem,
    ): bool {
        $name = $property->getName();
        $route = $this->resolve($object, $class, $property, $name);

        if (!$this->acceptsStringableAtRoute($class, $property, $name, $route, $arrayItem)) {
            return false;
        }

        if ($route['type'] === 'constructor') {
            $setter = $this->findSetterMethod($class, $name);

            return $setter === null || $this->setterAcceptsStringable($class, $setter, $arrayItem);
        }

        return $route['type'] !== 'property'
            || $this->requiredConstructorAcceptsStringable($class, $name, $arrayItem) !== false;
    }

    /**
     * @internal
     * @param array{type: 'property'|'constructor'|'setter'|'skip', position?: int, method?: string} $route
     */
    public function acceptsStringableAtRoute(
        \ReflectionClass $class,
        \ReflectionProperty $property,
        string $name,
        array $route,
        bool $arrayItem,
    ): bool {
        if ($route['type'] === 'skip') {
            return false;
        }

        $valueType = $arrayItem ? 'array' : 'string';
        if ($route['type'] === 'setter') {
            return $this->setterAcceptsStringable($class, $route['method'], $arrayItem);
        }

        if ($route['type'] === 'constructor') {
            $position = $route['position'] ?? $this->findConstructorParameterPosition($class, $name);
            $parameter = $position === null ? null : $class->getConstructor()?->getParameters()[$position];

            return $this->acceptsType($parameter?->getType(), $valueType);
        }

        return $this->propertyAcceptsType(
            $class,
            $this->getDestinationProperty($class, $property, $name),
            $name,
            $valueType,
            $arrayItem,
        );
    }

    /** @internal */
    public function setterAcceptsStringable(\ReflectionClass $class, string $setter, bool $arrayItem): bool
    {
        $type = $class->getMethod($setter)->getParameters()[0]->getType();

        return $this->acceptsType($type, $arrayItem ? 'array' : 'string');
    }

    /** @internal */
    public function requiredConstructorAcceptsStringable(\ReflectionClass $class, string $name, bool $arrayItem): ?bool
    {
        $position = $this->findConstructorParameterPosition($class, $name);
        $parameter = $position === null ? null : $class->getConstructor()?->getParameters()[$position];

        return $parameter === null || $parameter->isOptional()
            ? null
            : $this->acceptsType($parameter->getType(), $arrayItem ? 'array' : 'string');
    }

    private function getDestinationProperty(
        \ReflectionClass $class,
        \ReflectionProperty $source,
        string $name,
    ): ?\ReflectionProperty {
        if ($name === $source->getName()) {
            return $source;
        }

        return $class->hasProperty($name) ? $class->getProperty($name) : null;
    }

    private function propertyAcceptsType(
        \ReflectionClass $class,
        ?\ReflectionProperty $property,
        string $name,
        string $valueType,
        bool $arrayItem,
    ): bool {
        if (!class_exists(PropertyWriteInfo::class)) {
            $setter = $this->findPropertySetter($class, $name);

            return !$arrayItem && ($setter === null
                ? $property?->isPublic() && $this->acceptsType($property->getType(), $valueType)
                : !$setter->isStatic() && $this->acceptsType($setter->getParameters()[0]->getType(), $valueType));
        }

        $extractor = $this->writeInfoExtractor ??= new ReflectionExtractor(['set'], null, null, false);
        $write = $extractor->getWriteInfo($class->getName(), $name, [
            'enable_getter_setter_extraction' => true,
            'enable_constructor_extraction' => false,
            'enable_adder_remover_extraction' => $arrayItem,
        ]);
        if ($write?->getType() === PropertyWriteInfo::TYPE_ADDER_AND_REMOVER) {
            $write = $write->getAdderInfo();
            $valueType = 'string';
        }

        return match ($write?->getType()) {
            PropertyWriteInfo::TYPE_PROPERTY => $write->getVisibility() === PropertyWriteInfo::VISIBILITY_PUBLIC
                && $property?->isPublic()
                && !$property->isStatic()
                && $this->acceptsType($property->getType(), $valueType),
            PropertyWriteInfo::TYPE_METHOD => !$write->isStatic()
                && $class->hasMethod($write->getName())
                && $this->acceptsType($class->getMethod($write->getName())->getParameters()[0]->getType(), $valueType),
            default => false,
        };
    }

    private function acceptsType(?\ReflectionType $type, string $valueType): bool
    {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->acceptsType($member, $valueType)) {
                    return true;
                }
            }

            return false;
        }

        return $type === null || ($type instanceof \ReflectionNamedType && (
            $type->getName() === $valueType
            || $type->getName() === 'mixed'
            || ($valueType === 'array' && $type->getName() === 'iterable')
        ));
    }

    public function findSetterMethod(\ReflectionClass $reflectionClass, string $propertyName): ?string
    {
        $methodName = 'set' . ucfirst($propertyName);
        if (!$reflectionClass->hasMethod($methodName)) {
            return null;
        }

        $reflectionMethod = $reflectionClass->getMethod($methodName);
        if (!$reflectionMethod->isPublic() || $reflectionMethod->isStatic()) {
            return null;
        }

        if (count($reflectionMethod->getParameters()) !== 1) {
            return null;
        }

        return $methodName;
    }

    /**
     * @internal
     * @return array{method: string, itemType: string}|null
     */
    public function findCollectionAdder(\ReflectionClass $class, string $property): ?array
    {
        $suffix = ucfirst($property);
        $adder = 'add' . $suffix;
        $itemType = $this->collectionMutatorItemType($class, $adder);
        if ($itemType === null || $itemType !== $this->collectionMutatorItemType($class, 'remove' . $suffix)) {
            return null;
        }

        return ['method' => $adder, 'itemType' => $itemType];
    }

    private function collectionMutatorItemType(\ReflectionClass $class, string $name): ?string
    {
        if (!$class->hasMethod($name)) {
            return null;
        }

        $method = $class->getMethod($name);
        if (!$method->isPublic() || $method->isStatic() || $method->getNumberOfParameters() !== 1) {
            return null;
        }

        $parameter = $method->getParameters()[0];
        $type = $parameter->getType();
        if ($parameter->isPassedByReference() || $parameter->isVariadic()
            || !$type instanceof \ReflectionNamedType || $type->isBuiltin()
        ) {
            return null;
        }

        return match ($type->getName()) {
            'self' => $method->getDeclaringClass()->getName(),
            'parent' => ($method->getDeclaringClass()->getParentClass() ?: null)?->getName(),
            default => $type->getName(),
        };
    }

    private function findPropertySetter(\ReflectionClass $reflectionClass, string $propertyName): ?\ReflectionMethod
    {
        $camelized = str_replace(' ', '', ucwords(str_replace('_', ' ', $propertyName)));
        foreach ([$camelized, ucfirst($propertyName)] as $name) {
            $methodName = 'set' . $name;
            if (!$reflectionClass->hasMethod($methodName)) {
                continue;
            }

            $method = $reflectionClass->getMethod($methodName);
            if ($method->isPublic() && $method->getNumberOfRequiredParameters() <= 1 && $method->getNumberOfParameters() > 0) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{position: int, property: string, value: mixed}> $constructorCandidates
     * @param array<string, mixed> $writableProperties
     *
     * @return array<int, mixed>|null
     */
    public function buildConstructorArguments(
        \ReflectionClass $reflectionClass,
        array $constructorCandidates,
        array $writableProperties = [],
    ): ?array {
        $candidatesByPosition = [];
        foreach ($constructorCandidates as $candidate) {
            $candidatesByPosition[$candidate['position']] = ['source' => null, 'value' => $candidate['value']];
        }

        $arguments = $this->assembleConstructorArguments(
            $reflectionClass,
            $candidatesByPosition,
            array_map(static fn (mixed $value): array => ['source' => null, 'value' => $value], $writableProperties),
        );

        return $arguments === null ? null : array_column($arguments, 'value');
    }

    /**
     * @internal
     * @param array<int, PlannedConstructorValue> $constructorCandidates
     * @param array<string, ConstructorValue> $writableProperties
     * @return array<int, ConstructorArgument>|null
     */
    public function bindConstructorArguments(
        \ReflectionClass $reflectionClass,
        array $constructorCandidates,
        array $writableProperties,
    ): ?array {
        $candidatesByPosition = array_column($constructorCandidates, null, 'position');
        if ($this::class === self::class) {
            return $this->assembleConstructorArguments($reflectionClass, $candidatesByPosition, $writableProperties);
        }

        // Custom resolvers can override the public argument builder.
        $arguments = $this->buildConstructorArguments(
            $reflectionClass,
            array_map(static fn (array $candidate): array => [
                'position' => $candidate['position'],
                'property' => $candidate['property'],
                'value' => $candidate['value'],
            ], $constructorCandidates),
            array_map(static fn (array $write): mixed => $write['value'], $writableProperties),
        );
        if ($arguments === null) {
            return null;
        }

        $parameters = $reflectionClass->getConstructor()?->getParameters() ?? [];
        $bindings = [];
        foreach ($arguments as $position => $value) {
            $parameter = $parameters[$position] ?? null;
            $write = $parameter === null ? null : $this->constructorWrite($parameter, $candidatesByPosition, $writableProperties);
            $bindings[$position] = [
                'parameter' => $parameter?->getName(),
                'source' => $write !== null && $write['value'] === $value ? $write['source'] : null,
                'value' => $value,
            ];
        }

        return $bindings;
    }

    /**
     * @param array<int, ConstructorValue> $candidatesByPosition
     * @param array<string, ConstructorValue> $writableProperties
     * @return list<ConstructorArgument>|null
     */
    private function assembleConstructorArguments(
        \ReflectionClass $reflectionClass,
        array $candidatesByPosition,
        array $writableProperties,
    ): ?array {
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $highestPosition = max([$constructor->getNumberOfRequiredParameters() - 1, ...array_keys($candidatesByPosition)]);
        if ($highestPosition < 0) {
            return null;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $position => $parameter) {
            if ($position > $highestPosition) {
                break;
            }

            $write = $this->constructorWrite($parameter, $candidatesByPosition, $writableProperties);
            if ($write === null) {
                if (!$parameter->isDefaultValueAvailable()) {
                    return null;
                }
                $write = ['source' => null, 'value' => $parameter->getDefaultValue()];
            }

            $arguments[] = ['parameter' => $parameter->getName(), 'source' => $write['source'], 'value' => $write['value']];
        }

        return $arguments;
    }

    /**
     * @param array<int, ConstructorValue> $candidatesByPosition
     * @param array<string, ConstructorValue> $writableProperties
     * @return ConstructorValue|null
     */
    private function constructorWrite(\ReflectionParameter $parameter, array $candidatesByPosition, array $writableProperties): ?array
    {
        return $candidatesByPosition[$parameter->getPosition()]
            ?? ($parameter->isOptional() ? null : ($writableProperties[$parameter->getName()] ?? null));
    }

    private function findConstructorParameterPosition(\ReflectionClass $reflectionClass, string $propertyName): ?int
    {
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $position => $parameter) {
            if ($parameter->getName() === $propertyName) {
                return $position;
            }
        }

        return null;
    }
}

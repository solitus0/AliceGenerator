<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;

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

        return $this->acceptsStringableAtRoute(
            $class,
            $property,
            $name,
            $this->resolve($object, $class, $property, $name),
            $arrayItem,
        );
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
            return $this->acceptsType($class->getMethod($route['method'])->getParameters()[0]->getType(), $valueType);
        }

        $position = $route['position'] ?? $this->findConstructorParameterPosition($class, $name);
        $parameter = $position === null ? null : $class->getConstructor()?->getParameters()[$position];
        if ($route['type'] === 'constructor') {
            $setter = $this->findSetterMethod($class, $name);
            $setterType = $setter === null ? null : $class->getMethod($setter)->getParameters()[0]->getType();

            return $this->acceptsType($parameter?->getType(), $valueType)
                && $this->acceptsType($setterType, $valueType);
        }

        if ($parameter !== null && !$parameter->isOptional() && !$this->acceptsType($parameter->getType(), $valueType)) {
            return false;
        }

        return $this->propertyAcceptsType(
            $class,
            $this->getDestinationProperty($class, $property, $name),
            $name,
            $valueType,
            $arrayItem,
        );
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
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $candidatesByPosition = [];
        foreach ($constructorCandidates as $candidate) {
            $candidatesByPosition[$candidate['position']] = $candidate['value'];
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

            if (array_key_exists($position, $candidatesByPosition)) {
                $arguments[] = $candidatesByPosition[$position];

                continue;
            }

            if (!$parameter->isOptional() && array_key_exists($parameter->getName(), $writableProperties)) {
                $arguments[] = $writableProperties[$parameter->getName()];

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            return null;
        }

        return $arguments;
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

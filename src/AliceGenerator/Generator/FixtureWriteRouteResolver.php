<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FixtureWriteRouteResolver
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
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
        if ($reflectionProperty->isPublic() && !$reflectionProperty->isReadOnly()) {
            return ['type' => 'property'];
        }

        $constructorPosition = $this->findConstructorParameterPosition($reflectionClass, $propertyName);
        if ($constructorPosition !== null) {
            return ['type' => 'constructor', 'position' => $constructorPosition];
        }

        if ($this->propertyAccessor->isWritable($object, $propertyName)) {
            return ['type' => 'property'];
        }

        $setterMethod = $this->findSetterMethod($reflectionClass, $propertyName);
        if ($setterMethod !== null) {
            return ['type' => 'setter', 'method' => $setterMethod];
        }

        return ['type' => 'skip'];
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
     * @param array<int, array{position: int, property: string, value: mixed}> $constructorCandidates
     *
     * @return array<int, mixed>|null
     */
    public function buildConstructorArguments(\ReflectionClass $reflectionClass, array $constructorCandidates): ?array
    {
        if ($constructorCandidates === []) {
            return null;
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $candidatesByPosition = [];
        foreach ($constructorCandidates as $candidate) {
            $candidatesByPosition[$candidate['position']] = $candidate['value'];
        }

        $highestPosition = max(array_keys($candidatesByPosition));
        $arguments = [];

        foreach ($constructor->getParameters() as $position => $parameter) {
            if ($position > $highestPosition) {
                break;
            }

            if (array_key_exists($position, $candidatesByPosition)) {
                $arguments[] = $candidatesByPosition[$position];

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

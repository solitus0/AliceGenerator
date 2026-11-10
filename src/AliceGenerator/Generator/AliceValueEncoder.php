<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\AliceExpression;

/** @internal */
final class AliceValueEncoder
{
    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public static function encodeDefinition(array $definition): array
    {
        return array_map(self::encode(...), $definition);
    }

    public static function markSyntax(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => new AliceExpression($value),
            is_array($value) => array_map(self::markSyntax(...), $value),
            default => $value,
        };
    }

    public static function unwrapExpressions(mixed $value): mixed
    {
        return match (true) {
            $value instanceof AliceExpression => $value->syntax,
            is_array($value) => array_map(self::unwrapExpressions(...), $value),
            default => $value,
        };
    }

    public static function enumExpression(\UnitEnum $value): AliceExpression
    {
        return AliceExpression::php($value::class . '::' . $value->name);
    }

    public static function dateExpression(\DateTimeInterface $value): AliceExpression
    {
        $class = $value instanceof \DateTimeImmutable ? '\DateTimeImmutable' : '\DateTime';

        return AliceExpression::php(sprintf(
            'new %s(%s, new \DateTimeZone(%s))',
            $class,
            var_export($value->format('Y-m-d H:i:s.u'), true),
            var_export($value->getTimezone()->getName(), true),
        ));
    }

    private static function encode(mixed $value): mixed
    {
        return match (true) {
            $value instanceof AliceExpression => $value->syntax,
            $value instanceof \UnitEnum => self::enumExpression($value)->syntax,
            $value instanceof \DateTimeInterface => self::dateExpression($value)->syntax,
            is_array($value) => array_map(self::encode(...), $value),
            is_string($value) => self::encodeLiteral($value),
            default => $value,
        };
    }

    private static function encodeLiteral(string $value): string
    {
        // Alice also interprets bracketed lists, optional values and dynamic array counts.
        if (strpbrk($value, '<>[]%\\') !== false || preg_match('/[0-9]+x /', $value) === 1) {
            return "<base64_decode('" . base64_encode($value) . "')>";
        }

        return str_replace(['@', '$'], ['\@', '\$'], $value);
    }
}

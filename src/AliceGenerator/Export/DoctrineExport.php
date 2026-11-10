<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/** @experimental */
final class DoctrineExport
{
    /**
     * @param \Closure(class-string): array{EntityManagerInterface, \Closure(string): array<string, object>} $bootstrap
     * @param class-string $entity
     * @param list<string> $fields Doctrine fields to project from each root
     * @param array<string, mixed> $replace Literal values or closures receiving the one-based root number
     * @param array<string, array{entity: class-string, owningField: string, fields: list<string>, orderBy?: list<string>}> $children
     * @return \Closure(list<string>): ExportRecipe
     */
    public static function byId(
        \Closure $bootstrap,
        string $entity,
        string $alias,
        array $fields = [],
        array $replace = [],
        array $children = [],
        int $maxRoots = 10,
        int $maxChildren = 50,
        int $maxObjects = 100,
    ): \Closure {
        return static function (array $arguments) use (
            $bootstrap,
            $entity,
            $alias,
            $fields,
            $replace,
            $children,
            $maxRoots,
            $maxChildren,
            $maxObjects,
        ): ExportRecipe {
            if ($maxRoots < 1 || $maxChildren < 0 || $maxChildren === PHP_INT_MAX || $maxObjects < 1) {
                throw new ExportFailure('invalid_selection');
            }
            $ids = self::ids($arguments, $maxRoots);

            try {
                [$manager, $load] = $bootstrap($entity);
            } catch (\Throwable) {
                throw new ExportFailure('bootstrap_failed');
            }
            $connection = $manager->getConnection();
            if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
                $connection->executeStatement('PRAGMA query_only = ON');
            }

            $root = $manager->getClassMetadata($entity);
            $idField = self::idField($root);
            $fields = array_values(array_diff($fields, array_keys($replace)));
            $childMappings = [];
            foreach ($children as $suffix => $child) {
                $mapping = $manager->getClassMetadata($child['entity']);
                $owner = $child['owningField'];
                if (!$mapping->isSingleValuedAssociation($owner) || $mapping->isAssociationInverseSide($owner)
                    || !is_a($entity, $mapping->getAssociationTargetClass($owner), true)
                    || $mapping->getSingleAssociationReferencedJoinColumnName($owner) !== $root->getColumnName($idField)
                ) {
                    throw new ExportFailure('invalid_selection');
                }
                $childMappings[$suffix] = $mapping;
            }

            $records = [];
            $connection->beginTransaction();

            try {
                foreach ($ids as $position => $id) {
                    $number = $position + 1;
                    $name = $alias . '_' . $number;
                    $rows = self::project($manager, $root, $fields, $idField, $id, 1);
                    if ($rows === []) {
                        throw new ExportFailure('root_not_found');
                    }
                    $values = $rows[0];
                    foreach ($replace as $field => $value) {
                        $values[$field] = $value instanceof \Closure ? $value($number) : $value;
                    }
                    $object = $root->newInstance();
                    self::addRecord($records, $name, $object, $values, $maxObjects);

                    foreach ($children as $suffix => $child) {
                        $mapping = $childMappings[$suffix];
                        $owner = $child['owningField'];
                        $rows = self::project(
                            $manager,
                            $mapping,
                            $child['fields'],
                            $owner,
                            $id,
                            $maxChildren + 1,
                            $child['orderBy'] ?? [],
                        );
                        if (count($rows) > $maxChildren) {
                            throw new ExportFailure('relation_limit_exceeded');
                        }
                        foreach ($rows as $index => $values) {
                            $values[$owner] = $object;
                            self::addRecord(
                                $records,
                                $name . '_' . $suffix . '_' . ($index + 1),
                                $mapping->newInstance(),
                                $values,
                                $maxObjects,
                            );
                        }
                    }
                }
            } finally {
                $connection->rollBack();
            }

            return new ExportRecipe($manager, $records, $load, maxObjects: $maxObjects);
        };
    }

    /** @param list<string> $arguments @return list<int> */
    private static function ids(array $arguments, int $maxRoots): array
    {
        if ($arguments === [] || count($arguments) > $maxRoots) {
            throw new ExportFailure('invalid_recipe_arguments');
        }
        $ids = array_map(static fn (string $id): int|false => filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        ), $arguments);
        if (in_array(false, $ids, true) || count(array_unique($ids)) !== count($ids)) {
            throw new ExportFailure('invalid_recipe_arguments');
        }

        return $ids;
    }

    /** @param ClassMetadata<object> $mapping */
    private static function idField(ClassMetadata $mapping): string
    {
        $ids = $mapping->getIdentifierFieldNames();
        if (count($ids) !== 1 || !$mapping->hasField($ids[0])) {
            throw new ExportFailure('invalid_selection');
        }

        return $ids[0];
    }

    /**
     * @param ClassMetadata<object> $mapping
     * @param list<string> $fields
     * @param list<string> $orderBy
     * @return list<array<string, mixed>>
     */
    private static function project(
        EntityManagerInterface $manager,
        ClassMetadata $mapping,
        array $fields,
        string $match,
        int $id,
        int $limit,
        array $orderBy = [],
    ): array {
        $idField = self::idField($mapping);
        $query = $manager->createQueryBuilder()
            ->select('record.' . $idField . ' AS export_id')
            ->from($mapping->getName(), 'record')
            ->where('record.' . $match . ' = :source')
            ->setParameter('source', $id)
            ->setMaxResults($limit)
        ;
        foreach ([...$fields, ...$orderBy] as $field) {
            if (!$mapping->hasField($field) || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $field) !== 1) {
                throw new ExportFailure('invalid_selection');
            }
        }
        foreach ($fields as $index => $field) {
            $query->addSelect('record.' . $field . ' AS export_value_' . $index);
        }
        foreach (array_unique([...$orderBy, $idField]) as $field) {
            $query->addOrderBy('record.' . $field, 'ASC');
        }

        $rows = [];
        foreach ($query->getQuery()->getArrayResult() as $row) {
            $values = [];
            foreach ($fields as $index => $field) {
                $values[$field] = $row['export_value_' . $index];
            }
            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @param array<string, array{object: object, values: array<string, mixed>}> $records
     * @param array<string, mixed> $values
     */
    private static function addRecord(
        array &$records,
        string $alias,
        object $object,
        array $values,
        int $maxObjects,
    ): void {
        if (count($records) >= $maxObjects) {
            throw new ExportFailure('selection_limit_exceeded');
        }
        if (isset($records[$alias])) {
            throw new ExportFailure('invalid_selection');
        }
        $records[$alias] = compact('object', 'values');
    }
}

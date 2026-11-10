# Recipe-based fixture export

Use `alice-export` from a project that installs AliceGenerator with Composer.
A project-owned PHP recipe selects the records and properties for one scenario
and supplies the same Alice configuration the project uses to load fixtures.

```sh
vendor/bin/alice-export preview tools/fixtures/closed-case.php -- 17
vendor/bin/alice-export validate tools/fixtures/closed-case.php -- 17
vendor/bin/alice-export export --output tests/fixtures/closed-case.yaml tools/fixtures/closed-case.php -- 17
```

- `preview` prepares the recipe and reports the approved selection. A recipe may query its source.
- `validate` generates YAML and reloads it in memory. It verifies the selected aliases, classes,
  distinct object identities, and required owning links.
- `export` validates the same YAML it writes. Choose a new filename in an existing local directory
  that supports hard links. An existing file, directory, or symlink produces `output_exists`.

The first `--` passes the remaining tokens to the recipe unchanged. Commands run noninteractively.
`--help` and `--version` print text without loading the project. Operational commands print one
schema-versioned JSON object. The report contains the stage, selection counts and property names,
validation status, and a stable error code on failure. A successful export reports its byte count and
SHA-256. Exit codes are `0` for success, `1` for operational errors, and `2` for invalid arguments.
The worker requires `proc_open` and has a 60-second execution budget.

## Write a recipe

For a Symfony app with Doctrine and Nelmio Alice, declare the root entity,
the values to replace, and which children to select through an owning association.

```php
<?php

declare(strict_types=1);

use App\Entity\CaseEvent;
use App\Entity\SupportCase;
use App\Kernel;
use Solitus0\AliceGenerator\Export\DoctrineExport;
use Solitus0\AliceGenerator\Export\SymfonyExport;

return DoctrineExport::byId(
    SymfonyExport::bootstrap(Kernel::class, dirname(__DIR__, 2) . '/config/bootstrap.php'),
    SupportCase::class,
    alias: 'case',
    fields: ['status'],
    replace: ['title' => static fn (int $number): string => 'Example case ' . $number],
    children: [
        'event' => [
            'entity' => CaseEvent::class,
            'owningField' => 'case',
            'fields' => ['kind'],
        ],
    ],
);
```

Set `ALICE_EXPORT_DATABASE_URL` to an approved read-only connection. The Symfony
adapter runs your application bootstrap, then uses its dev kernel, Doctrine manager
and configured `nelmio_alice.data_loader` service. That loader must construct objects
in memory without persisting or purging them.

`byId()` accepts distinct positive integer IDs for an entity with one scalar
identifier. It projects only the listed mapped fields. A replacement may
be a literal or a closure that receives the root's one-based position. Child
associations must own a link to the root identifier. Children can declare
`orderBy` as a list of mapped fields in ascending order. The identifier breaks ties.
Aliases use positions such as `case_1` and `case_1_event_1`.

The recipe accepts optional named limits. `maxRoots` defaults to 10 requested IDs,
`maxChildren` to 50 records per child group per root, and `maxObjects` to 100
records across the whole export, including roots. Set `maxChildren: 0` to require
an empty child group. A missing ID or an exceeded limit fails the whole export.

Other Doctrine apps can pass `byId()` a bootstrap closure that returns the entity
manager and an in-memory loader. For custom selection, a recipe can instead return
a closure accepting the CLI arguments and constructing an `ExportRecipe` with a
single `records` map. Each alias maps to `['object' => $object, 'values' => $values]`.
The values are the only properties emitted. An owning to-one value pointing to
another selected object is automatically checked during generation and reload.
The loader takes the generated YAML string and returns objects keyed by alias.

The recipe API is experimental. Update the library and recipe together when
switching from the earlier parallel `objects`, `fields`, `values`, and
`requiredLinks` arguments. To roll back, restore both previous versions.

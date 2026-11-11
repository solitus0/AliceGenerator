# Recipe-based fixture export

The `alice-export` command runs a project-owned PHP recipe that selects records and fields for
a test scenario. It uses the project's Alice loader to check that the selected fixture set can be reloaded.

## Prerequisites

- Install AliceGenerator [v0.2.0](../CHANGELOG.md#v020-2026-11-11) or later in the consuming project.
- Run from the consuming project with its Composer dependencies installed. The CLI needs `proc_open`
  and a writable system temporary directory.
- The Symfony adapter needs a dev kernel, a Doctrine entity manager for the root entity, and an accessible
  `nelmio_alice.data_loader` service. The loader must construct objects in memory without persisting or purging them.
  Set `ALICE_EXPORT_DATABASE_URL` to an approved read-only connection for the source data. The adapter
  passes it to the application as `DATABASE_URL` before booting the kernel.

## Write a recipe

Save this example as `tools/fixtures/closed-case.php` and substitute your application's classes and bootstrap file.

```php
<?php

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

`byId()` accepts one or more distinct positive integer IDs. The root and child mappings need one scalar
identifier field each. The recipe exports the listed mapped fields and the selected children's owning link
to the root. Use `replace` to supply literal root values or closures that receive the root's one-based position.
A replaced field's original value is not selected. Children can specify `orderBy` as a list of mapped fields
to sort in ascending order, with the identifier as the final sort key. Aliases use positions such as
`case_1` and `case_1_event_1`.

| Recipe option | Default |
| --- | --- |
| `maxRoots` | 10 requested IDs |
| `maxChildren` | 50 records per child group per root. Use `0` to require an empty group. |
| `maxObjects` | 100 records across roots and children |

A missing root or exceeded limit fails the export. For a Doctrine app without Symfony, pass `byId()` a
bootstrap closure that takes the root class and returns `[$entityManager, $load]`.

## Preview, validate, and export

Choose a new filename in a writable local directory that supports hard links. Create the directory before exporting.

```sh
mkdir -p tests/fixtures
vendor/bin/alice-export preview tools/fixtures/closed-case.php -- 17
vendor/bin/alice-export validate tools/fixtures/closed-case.php -- 17
vendor/bin/alice-export export --output tests/fixtures/closed-case.yaml tools/fixtures/closed-case.php -- 17
```

- `preview` prepares the recipe and reports the selection. It can query the source.
- `validate` generates YAML and reloads it in memory to check aliases, types, distinct object identities, and owning links.
- `export` runs the same checks and writes the validated YAML. An existing file, directory, or symlink returns `output_exists`.

The first `--` passes the remaining arguments to the recipe. Commands run without interaction. Each operational
invocation writes one JSON report to stdout. Check `stage`, `validation`, and `error.code` to diagnose a failure.
The report lists selected counts and property names, and a successful export includes `artifact.bytes` and
`artifact.sha256`. Exit codes are `0` for success, `1` for operational errors, and `2` for invalid arguments.
The worker has a 60-second time limit. `--help` and `--version` print text without loading the project.
The latter reports the JSON schema version.

### Example report and fixture

For a closed case with one closure event, `validate` reports that both fixtures loaded and the event points to the case.

```json
{
  "schema_version": 1,
  "command": "validate",
  "status": "ok",
  "stage": "load",
  "selection": {
    "objects": 2,
    "classes": { "App\\Entity\\CaseEvent": 1, "App\\Entity\\SupportCase": 1 },
    "properties": {
      "App\\Entity\\CaseEvent": ["case", "kind"],
      "App\\Entity\\SupportCase": ["status", "title"]
    }
  },
  "validation": "passed",
  "artifact": null,
  "error": null
}
```

`export` writes the YAML for the same selection.

```yaml
App\Entity\CaseEvent:
    case_1_event_1:
        kind: closed
        case: '@case_1'
App\Entity\SupportCase:
    case_1:
        status: closed
        title: 'Example case 1'
```

## Custom selection

For selection beyond `byId()`, return a closure that takes the CLI argument list and constructs an
`ExportRecipe` with the entity manager, a `records` map, and an in-memory loader `$load`. Each record key is
its fixture alias and maps to `['object' => $object, 'values' => $values]`. Only the named properties in
`values` are exported. Field values may be literals, arrays of supported values, dates, or enums.
Association values must point to objects in the selection. For a selected non-null owning to-one link,
the generator checks that the emitted fixture writes the link and the loader restores it to the same target object.

The loader accepts a YAML string and returns objects keyed by fixture alias. `ExportRecipe` defaults to a
1 MiB YAML limit and accepts `maxYamlBytes` up to 8 MiB.

The recipe API is experimental. Review recipe compatibility when upgrading AliceGenerator.

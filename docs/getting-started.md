# Getting started

Install AliceGenerator as a development dependency with Composer 2.2 or later and PHP 8.1 or later.

```sh
composer require solitus0/alice-generator --dev
```

The examples in these guides describe the current branch. See the [changelog](../CHANGELOG.md) for changes since the latest tagged release.

## Quick start

Save this example as `example.php` in your project root and run `php example.php`. It exports two tasks that share one assignee.

```php
<?php

namespace App\Entity;

use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;

require __DIR__ . '/vendor/autoload.php';

final class Assignee
{
    public string $name;
}

final class Task
{
    public string $title;
    public Assignee $assignee;
}

$alex = new Assignee();
$alex->name = 'Alex';

$invoice = new Task();
$invoice->title = 'Send invoice';
$invoice->assignee = $alex;

$review = new Task();
$review->title = 'Review';
$review->assignee = $alex;

$generator = FixtureGeneratorBuilder::create()->build();
$yaml = $generator->generate([$invoice, $review], FixtureGenerationContext::create());
echo $yaml;
```

```yaml
App\Entity\Assignee:
    Assignee-1:
        name: Alex
App\Entity\Task:
    Task-1:
        title: 'Send invoice'
        assignee: '@Assignee-1'
    Task-2:
        title: Review
        assignee: '@Assignee-1'
```

## Reload the fixtures

Use your project's Alice loader with the same classes available. For this standalone example, install
`nelmio/alice` with `composer require --dev nelmio/alice`, then replace `echo $yaml;` with the following.

```php
$loader = new \Nelmio\Alice\Loader\NativeLoader();
$objects = $loader->loadData(\Symfony\Component\Yaml\Yaml::parse($yaml))->getObjects();

if ($objects['Task-1']->assignee !== $objects['Task-2']->assignee) {
    throw new \RuntimeException('Expected both tasks to share an assignee.');
}

echo "Both tasks share the same assignee object.\n";
```

## Next steps

For an existing Doctrine entity, [configure the Doctrine metadata handler](configuration.md#doctrine-integration) with
your application's entity manager. To control what the generator follows, see [Usage](usage.md).

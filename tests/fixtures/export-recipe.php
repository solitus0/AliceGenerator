<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Loader\NativeLoader;
use Solitus0\AliceGenerator\Export\DoctrineExport;
use Solitus0\AliceGenerator\Export\ExportRecipe;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\JobBatch;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\JobProgress;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\RenamedBatch;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\RenamedProgress;
use Solitus0\AliceGenerator\Tests\Export\Fixtures\RecipeRecord;
use Symfony\Component\Yaml\Yaml;

fwrite(STDOUT, 'private bootstrap output');
fwrite(STDERR, 'private bootstrap error');

return static function (array $arguments): ExportRecipe {
    if ($arguments === ['exit']) {
        exit(0);
    }
    $config = new Configuration();
    $config->setMetadataDriverImpl(new AttributeDriver([
        dirname(__DIR__) . '/AliceGenerator/Doctrine/Fixtures',
        dirname(__DIR__) . '/AliceGenerator/Export/Fixtures',
    ]));
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('AliceExportTestProxies');
    if (PHP_VERSION_ID >= 80400 && (new ReflectionClass(Configuration::class))->hasMethod('enableNativeLazyObjects')) {
        $config->enableNativeLazyObjects(true);
    }
    $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $load = static function (string $yaml) use ($arguments): array {
        $objects = (new NativeLoader())->loadData(Yaml::parse($yaml))->getObjects();
        if ($arguments === ['wrong-link']) {
            $objects['batch_1_progress_1']->batch = $objects['batch_2'];
        }

        return $objects;
    };

    if ($arguments === ['missing-constructor']) {
        $progress = new RenamedProgress(new RenamedBatch(), 'source label');

        return new ExportRecipe($manager, [
            'progress' => ['object' => $progress, 'values' => ['label' => 'Selected label']],
        ], $load);
    }
    if ($arguments === ['unwritable-link'] || $arguments === ['empty-values']) {
        $batch = new JobBatch('source name');
        $values = $arguments === ['unwritable-link'] ? ['batch' => $batch] : ['payload' => [[], 'named' => []]];

        return new ExportRecipe($manager, [
            'batch' => ['object' => $batch, 'values' => []],
            'record' => ['object' => new RecipeRecord($batch), 'values' => $values],
        ], $load);
    }

    (new SchemaTool($manager))->createSchema([
        $manager->getClassMetadata(JobBatch::class),
        $manager->getClassMetadata(JobProgress::class),
    ]);
    $batch = new JobBatch('private source name');
    $manager->persist($batch);
    $manager->persist(new JobBatch('another private source name'));
    $manager->persist(new JobProgress('@literal label', $batch));
    if (in_array($arguments, [['overflow'], ['expanded-child-limit']], true)) {
        for ($index = 0; $index < 50; $index++) {
            $manager->persist(new JobProgress('extra', $batch));
        }
    }
    $manager->flush();
    $manager->clear();

    $limits = match ($arguments) {
        ['root-limit'] => ['maxRoots' => 1],
        ['empty-children'] => ['maxChildren' => 0],
        ['expanded-child-limit'] => ['maxChildren' => 51],
        ['object-limit'] => ['maxObjects' => 2],
        default => [],
    };
    $prepare = DoctrineExport::byId(
        static fn (): array => [$manager, $load],
        JobBatch::class,
        ...$limits,
        alias: 'batch',
        replace: ['name' => 'Example batch'],
        children: ['progress' => ['entity' => JobProgress::class, 'owningField' => 'batch', 'fields' => ['label']]],
    );

    return $prepare(match ($arguments) {
        ['--id', '17'], ['wrong-link'], ['root-limit'], ['object-limit'] => ['1', '2'],
        ['missing'] => ['1', '999'],
        ['overflow'], ['empty-children'], ['expanded-child-limit'] => ['1'],
        default => $arguments,
    });
};

<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nelmio\Alice\DataLoaderInterface;
use Symfony\Component\Yaml\Yaml;

/** @experimental */
final class SymfonyExport
{
    /**
     * @param class-string $kernel Symfony application kernel
     * @return \Closure(class-string): array{EntityManagerInterface, \Closure(string): array<string, object>}
     */
    public static function bootstrap(string $kernel, string $bootstrapFile): \Closure
    {
        return static function (string $entity) use ($kernel, $bootstrapFile): array {
            $source = getenv('ALICE_EXPORT_DATABASE_URL');
            if ($source === false || $source === '') {
                throw new ExportFailure('bootstrap_failed');
            }

            require $bootstrapFile;
            $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $source;
            $application = new $kernel('dev', false);
            $application->boot();
            $container = $application->getContainer();
            $doctrine = $container->get('doctrine');
            $manager = $doctrine instanceof ManagerRegistry ? $doctrine->getManagerForClass($entity) : null;
            if (!$manager instanceof EntityManagerInterface) {
                throw new ExportFailure('bootstrap_failed');
            }

            return [$manager, static function (string $yaml) use ($container): array {
                $loader = $container->get('nelmio_alice.data_loader');
                if (!$loader instanceof DataLoaderInterface) {
                    throw new ExportFailure('alice_load_failed');
                }

                return $loader->loadData(Yaml::parse($yaml))->getObjects();
            }];
        };
    }
}

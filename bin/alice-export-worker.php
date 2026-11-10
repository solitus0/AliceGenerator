<?php

declare(strict_types=1);

use Solitus0\AliceGenerator\Export\ExportWorker;

require __DIR__ . '/../src/AliceGenerator/Export/ExportWorker.php';

exit(ExportWorker::run($argv[1], $argv[2], $argv[3], $argv[4], array_slice($argv, 5)));

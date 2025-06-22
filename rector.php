<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths(
        [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    );

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->sets(
        [
            SetList::TYPE_DECLARATION,
            SetList::DEAD_CODE,
            SetList::INSTANCEOF,
            SetList::CODE_QUALITY,
            SetList::CODING_STYLE,
            LevelSetList::UP_TO_PHP_81,
            SetList::EARLY_RETURN,
            SetList::PRIVATIZATION,
            SetList::STRICT_BOOLEANS,
        ]
    );
};

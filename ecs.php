<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        VisibilityRequiredFixer::class,
        ArraySyntaxFixer::class,
        NoUnusedImportsFixer::class,
        NoSuperfluousPhpdocTagsFixer::class,
        BinaryOperatorSpacesFixer::class,
        DeclareStrictTypesFixer::class,
    ])
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'method' => 'one',
            'property' => 'one',
            'trait_import' => 'one',
            'const' => 'one',
        ],
    ])
    ->withPreparedSets(psr12: true, cleanCode: true)
;

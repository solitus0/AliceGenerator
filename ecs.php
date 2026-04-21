<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\Arrays\DisallowLongArraySyntaxSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\WhiteSpace\DisallowTabIndentSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Methods\MethodDeclarationSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Namespaces\NamespaceDeclarationSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Namespaces\UseDeclarationSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Classes\ClassDeclarationSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Classes\ClassFileNameSniff;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules(
        [
            DeclareStrictTypesFixer::class,
            DisallowLongArraySyntaxSniff::class,
            DisallowTabIndentSniff::class,
            MethodDeclarationSniff::class,
            NamespaceDeclarationSniff::class,
            UseDeclarationSniff::class,
            ClassFileNameSniff::class,
            ClassDeclarationSniff::class,
            ClassAttributesSeparationFixer::class,
            BlankLineBeforeStatementFixer::class,
            SingleQuoteFixer::class,
            PhpdocOrderFixer::class,
            NoEmptyPhpdocFixer::class,
            NoUnneededFinalMethodFixer::class,
            NoUselessReturnFixer::class,
        ]
    )
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'method' => 'one',
            'property' => 'one',
            'trait_import' => 'one',
            'const' => 'none',
        ],
    ])
    ->withConfiguredRule(MultilineWhitespaceBeforeSemicolonsFixer::class, [
        'strategy' => 'new_line_for_chained_calls',
    ])
    ->withConfiguredRule(MethodArgumentSpaceFixer::class, [
        'on_multiline' => 'ensure_fully_multiline',
    ])
    ->withConfiguredRule(NoSuperfluousPhpdocTagsFixer::class, [
        'remove_inheritdoc' => true,
    ])
    ->withPreparedSets(
        psr12: true,
        comments: true,
        spaces: true,
        namespaces: true,
        phpunit: true,
        cleanCode: true,
    )
    ->withPhpCsFixerSets(
        php81Migration: true,
    )
    ->withSkip([
        NotOperatorWithSuccessorSpaceFixer::class,
    ])
    ->withRootFiles()
;

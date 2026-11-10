<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

/** @internal */
final class ExportWorker
{
    private const REASONS = [
        'bootstrap_failed',
        'invalid_recipe_arguments',
        'root_not_found',
        'invalid_selection',
        'selection_limit_exceeded',
        'relation_limit_exceeded',
        'forbidden_reference',
        'unsupported_field_value',
        'required_constructor_argument_missing',
        'required_link_mismatch',
        'artifact_limit_exceeded',
        'fixture_set_mismatch',
    ];

    /** @param list<string> $arguments */
    public static function run(string $autoload, string $result, string $command, string $path, array $arguments): int
    {
        $stage = 'bootstrap';
        $selection = null;
        $validation = 'not_run';
        $error = null;
        $bytes = null;

        try {
            require $autoload;
            $prepare = require $path;
            if (!$prepare instanceof \Closure) {
                throw new ExportFailure('invalid_selection');
            }

            $stage = 'selection';
            $recipe = $prepare($arguments);
            if (!$recipe instanceof ExportRecipe) {
                throw new ExportFailure('invalid_selection');
            }
            $selection = $recipe->summary();
            if ($command !== 'preview') {
                $stage = 'generation';
                $yaml = $recipe->generate();
                $stage = 'load';
                $validation = 'failed';
                $recipe->validate($yaml);
                $validation = 'passed';
                $bytes = $command === 'export' ? base64_encode($yaml) : null;
            }
        } catch (\Throwable $exception) {
            $error = $exception instanceof ExportFailure && in_array($exception->reason, self::REASONS, true)
                ? $exception->reason
                : match ($stage) {
                    'bootstrap' => 'bootstrap_failed',
                    'selection' => 'selection_failed',
                    'generation' => 'generation_failed',
                    default => 'alice_load_failed',
                };
        }

        $json = json_encode(compact('stage', 'selection', 'validation', 'error', 'bytes'), JSON_THROW_ON_ERROR);

        return file_put_contents($result, $json) === strlen($json) ? 0 : 1;
    }
}

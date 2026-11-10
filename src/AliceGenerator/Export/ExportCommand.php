<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

/** @internal Loaded before the consuming project's autoloader. */
final class ExportCommand
{
    private const COMMANDS = ['preview', 'validate', 'export'];
    private const MAX_RESULT_BYTES = 12 * 1024 * 1024;

    /** @param list<string> $arguments */
    public static function run(array $arguments, string $autoload, string $worker): int
    {
        if (in_array($arguments, [[], ['help'], ['--help'], ['-h']], true)
            || (count($arguments) === 2 && (
                ($arguments[0] === 'help' && in_array($arguments[1], self::COMMANDS, true))
                || (in_array($arguments[0], self::COMMANDS, true) && in_array($arguments[1], ['--help', '-h'], true))
            ))
        ) {
            fwrite(STDOUT, <<<'HELP'
                Usage:
                  alice-export preview RECIPE [-- ARGS...]
                  alice-export validate RECIPE [-- ARGS...]
                  alice-export export --output PATH RECIPE [-- ARGS...]
                  alice-export --version

                Commands run without interaction and emit one JSON report.
                HELP . PHP_EOL);

            return 0;
        }
        if (in_array($arguments, [['--version'], ['-V']], true)) {
            fwrite(STDOUT, 'alice-export report schema 1' . PHP_EOL);

            return 0;
        }

        $command = array_shift($arguments);
        $known = in_array($command, self::COMMANDS, true);
        $report = [
            'schema_version' => 1,
            'command' => $known ? $command : null,
            'status' => 'error',
            'stage' => 'input',
            'selection' => null,
            'validation' => 'not_run',
            'artifact' => null,
            'error' => null,
        ];

        $input = self::parse($arguments);
        if (!$known || $input === null || ($command === 'export') !== ($input['output'] !== null)) {
            return self::finish($report, 'invalid_cli', 2);
        }

        $recipe = self::localPath($input['recipe']) ? realpath($input['recipe']) : false;
        if ($recipe === false || !is_file($recipe) || !is_readable($recipe)) {
            return self::finish($report, 'recipe_unreadable');
        }

        $destination = null;
        if ($input['output'] !== null) {
            $report['stage'] = 'output';
            $destination = self::destination($input['output']);
            if ($destination === null) {
                return self::finish($report, 'output_publish_failed');
            }
            if (self::exists($destination)) {
                return self::finish($report, 'output_exists');
            }
        }

        $report['stage'] = 'worker';
        $result = self::runWorker($worker, $autoload, $command, $recipe, $input['arguments']);
        if (is_string($result)) {
            return self::finish($report, $result);
        }
        foreach (['stage', 'selection', 'validation'] as $key) {
            $report[$key] = $result[$key];
        }
        if ($result['error'] !== null) {
            return self::finish($report, $result['error'], $result['error'] === 'invalid_recipe_arguments' ? 2 : 1);
        }
        if ($report['selection'] === null || $report['validation'] !== ($command === 'preview' ? 'not_run' : 'passed')) {
            return self::finish($report, 'worker_failed');
        }

        if ($destination !== null) {
            $report['stage'] = 'output';
            $yaml = is_string($result['bytes']) ? base64_decode($result['bytes'], true) : false;
            if ($yaml === false) {
                return self::finish($report, 'worker_failed');
            }
            $error = self::publish($destination, $yaml);
            if ($error !== null) {
                return self::finish($report, $error);
            }
            $report['artifact'] = ['bytes' => strlen($yaml), 'sha256' => hash('sha256', $yaml)];
        }

        return self::finish($report, null, 0);
    }

    /**
     * @param list<string> $arguments
     * @return array{recipe: string, output: ?string, arguments: list<string>}|null
     */
    private static function parse(array $arguments): ?array
    {
        $recipe = null;
        $output = null;
        $values = [];
        $nonInteractive = false;
        for ($index = 0, $length = count($arguments); $index < $length; $index++) {
            $argument = $arguments[$index];
            if ($argument === '--') {
                $values = [...$values, ...array_slice($arguments, $index + 1)];

                break;
            }
            if ($argument === '--output' || str_starts_with($argument, '--output=')) {
                $path = $argument === '--output' ? ($arguments[++$index] ?? '') : substr($argument, 9);
                if ($output !== null || $path === '' || str_starts_with($path, '-')) {
                    return null;
                }
                $output = $path;
            } elseif (in_array($argument, ['-n', '--no-interaction'], true) && !$nonInteractive) {
                $nonInteractive = true;
            } elseif (str_starts_with($argument, '-')) {
                return null;
            } elseif ($recipe === null) {
                $recipe = $argument;
            } else {
                $values[] = $argument;
            }
        }

        return $recipe !== null ? ['recipe' => $recipe, 'output' => $output, 'arguments' => $values] : null;
    }

    /**
     * @param list<string> $arguments
     * @return array{stage: string, selection: ?array, validation: string, error: ?string, bytes: ?string}|string
     */
    private static function runWorker(string $worker, string $autoload, string $command, string $recipe, array $arguments): array|string
    {
        if (!function_exists('proc_open')) {
            return 'worker_unavailable';
        }
        $resultFile = @tempnam(sys_get_temp_dir(), 'alice-export-');
        if ($resultFile === false) {
            return 'worker_unavailable';
        }

        try {
            $sink = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $process = @proc_open(
                [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', $worker, $autoload, $resultFile, $command, $recipe, ...$arguments],
                [['file', $sink, 'r'], ['file', $sink, 'w'], ['file', $sink, 'w']],
                $pipes,
            );
            if ($process === false) {
                return 'worker_unavailable';
            }
            $deadline = hrtime(true) + 60_000_000_000;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (hrtime(true) >= $deadline) {
                    proc_terminate($process, 9);
                    proc_close($process);

                    return 'worker_timeout';
                }
                usleep(20_000);
            } while (true);
            proc_close($process);
            if ($status['exitcode'] !== 0) {
                return 'worker_failed';
            }

            $json = @file_get_contents($resultFile, false, null, 0, self::MAX_RESULT_BYTES + 1);
            if ($json === false || strlen($json) > self::MAX_RESULT_BYTES) {
                return 'worker_failed';
            }
            $result = json_decode($json, true);
            if (!is_array($result)
                || !in_array($result['stage'] ?? null, ['bootstrap', 'selection', 'generation', 'load'], true)
                || !in_array($result['validation'] ?? null, ['not_run', 'passed', 'failed'], true)
                || !array_key_exists('error', $result) || ($result['error'] !== null && !is_string($result['error']))
                || !array_key_exists('selection', $result) || ($result['selection'] !== null && !is_array($result['selection']))
                || !array_key_exists('bytes', $result) || ($result['bytes'] !== null && !is_string($result['bytes']))
            ) {
                return 'worker_failed';
            }

            return $result;
        } catch (\Throwable) {
            return 'worker_failed';
        } finally {
            @unlink($resultFile);
        }
    }

    private static function localPath(string $path): bool
    {
        return !str_contains($path, "\0") && !str_contains($path, '://');
    }

    private static function destination(string $path): ?string
    {
        if (!self::localPath($path) || str_ends_with($path, '/') || str_ends_with($path, DIRECTORY_SEPARATOR)) {
            return null;
        }
        $name = basename($path);
        $directory = realpath(dirname($path));
        if ($directory === false || !is_dir($directory) || in_array($name, ['', '.', '..'], true)) {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . $name;
    }

    private static function exists(string $path): bool
    {
        clearstatcache(true, $path);

        return file_exists($path) || is_link($path);
    }

    private static function publish(string $destination, string $yaml): ?string
    {
        $directory = dirname($destination);
        $staging = @tempnam($directory, '.alice-export-');
        if ($staging === false) {
            return 'output_publish_failed';
        }

        try {
            if (dirname($staging) === $directory && @file_put_contents($staging, $yaml) === strlen($yaml)
                && @link($staging, $destination)
            ) {
                return null;
            }

            return self::exists($destination) ? 'output_exists' : 'output_publish_failed';
        } finally {
            @unlink($staging);
        }
    }

    private static function finish(array $report, ?string $error, int $exit = 1): int
    {
        $report['status'] = $error === null ? 'ok' : 'error';
        $report['error'] = $error === null ? null : ['code' => $error];
        fwrite(STDOUT, json_encode($report, JSON_THROW_ON_ERROR) . PHP_EOL);

        return $exit;
    }
}

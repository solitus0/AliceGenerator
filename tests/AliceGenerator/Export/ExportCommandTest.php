<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Export;

use Nelmio\Alice\Loader\NativeLoader;
use PHPUnit\Framework\TestCase;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\JobBatch;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\JobProgress;
use Solitus0\AliceGenerator\Tests\Export\Fixtures\RecipeRecord;

final class ExportCommandTest extends TestCase
{
    public function testExportsValidatedBytesAndProtectsAnExistingFile(): void
    {
        $directory = sys_get_temp_dir() . '/alice export ' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $first = $directory . '/first.yaml';
        $second = $directory . '/second.yaml';

        try {
            [$code, $preview] = $this->runCli(['preview', $this->recipe(), '--', '--id', '17']);
            self::assertSame(0, $code);
            self::assertSame('not_run', $preview['validation']);
            self::assertSame(3, $preview['selection']['objects']);
            self::assertSame(['batch', 'label'], $preview['selection']['properties'][JobProgress::class]);

            [$code, $validation] = $this->runCli(['validate', $this->recipe(), '--', '--id', '17']);
            self::assertSame(0, $code);
            self::assertSame('passed', $validation['validation']);
            self::assertSame(['.', '..'], scandir($directory));

            [$code, $report] = $this->runCli(['export', '--output', $first, $this->recipe(), '--', '--id', '17']);
            self::assertSame(0, $code);
            self::assertSame('passed', $report['validation']);
            self::assertFileExists($first);
            $yaml = file_get_contents($first);
            self::assertIsString($yaml);
            self::assertSame(hash('sha256', $yaml), $report['artifact']['sha256']);
            self::assertStringNotContainsString('private source', $yaml);
            $objects = (new NativeLoader())->loadFile($first)->getObjects();
            self::assertInstanceOf(JobBatch::class, $objects['batch_1']);
            self::assertInstanceOf(JobProgress::class, $objects['batch_1_progress_1']);
            self::assertSame($objects['batch_1'], $objects['batch_1_progress_1']->batch);
            self::assertSame('Example batch', $objects['batch_1']->name);
            self::assertSame('@literal label', $objects['batch_1_progress_1']->label);
            self::assertCount(0, $objects['batch_1']->getProgress());

            [$code] = $this->runCli(['export', '--output=' . $second, $this->recipe(), '--', '--id', '17']);
            self::assertSame(0, $code);
            self::assertSame($yaml, file_get_contents($second));

            [$code, $report] = $this->runCli(['export', '--output', $first, $this->recipe(), '--', '--id', '17']);
            self::assertSame(1, $code);
            self::assertSame('output_exists', $report['error']['code']);
            self::assertSame($yaml, file_get_contents($first));
        } finally {
            foreach ([$first, $second] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }

    public function testReportsTypedFailuresAndChecksTheReloadedOwningLink(): void
    {
        foreach ([
            ['wrong-link', 1, 'required_link_mismatch'],
            ['missing', 1, 'root_not_found'],
            ['overflow', 1, 'relation_limit_exceeded'],
            ['root-limit', 2, 'invalid_recipe_arguments'],
            ['empty-children', 1, 'relation_limit_exceeded'],
            ['object-limit', 1, 'selection_limit_exceeded'],
            ['exit', 1, 'worker_failed'],
            ['private argument', 2, 'invalid_recipe_arguments'],
        ] as [$argument, $expectedExit, $expectedError]) {
            [$exit, $report] = $this->runCli(['validate', $this->recipe(), $argument]);
            self::assertSame($expectedExit, $exit);
            self::assertSame($expectedError, $report['error']['code']);
            self::assertNull($report['artifact']);
        }
    }

    public function testRecipeCanIncreaseChildLimit(): void
    {
        [$exit, $report] = $this->runCli(['validate', $this->recipe(), 'expanded-child-limit']);
        self::assertSame(0, $exit);
        self::assertSame('passed', $report['validation']);
        self::assertSame(52, $report['selection']['objects']);
    }

    public function testRejectsIncompleteSelectedWritesBeforeLoading(): void
    {
        foreach ([
            'missing-constructor' => 'required_constructor_argument_missing',
            'unwritable-link' => 'required_link_mismatch',
        ] as $argument => $error) {
            [$exit, $report] = $this->runCli(['validate', $this->recipe(), $argument]);

            self::assertSame(1, $exit);
            self::assertSame('generation', $report['stage']);
            self::assertSame('not_run', $report['validation']);
            self::assertSame($error, $report['error']['code']);
        }
    }

    public function testKeepsSelectedEmptyFixturesAndNestedArrays(): void
    {
        $directory = sys_get_temp_dir() . '/alice export ' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $output = $directory . '/empty.yaml';

        try {
            [$exit, $report] = $this->runCli(['export', '--output', $output, $this->recipe(), 'empty-values']);

            self::assertSame(0, $exit);
            self::assertSame('passed', $report['validation']);
            $objects = (new NativeLoader())->loadFile($output)->getObjects();
            self::assertInstanceOf(JobBatch::class, $objects['batch']);
            self::assertSame('', $objects['batch']->name);
            self::assertInstanceOf(RecipeRecord::class, $objects['record']);
            self::assertSame([[], 'named' => []], $objects['record']->payload);
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
            rmdir($directory);
        }
    }

    /** @param list<string> $arguments @return array{int, array<string, mixed>} */
    private function runCli(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 3) . '/bin/alice-export', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame('', $errors);
        self::assertIsString($output);
        self::assertStringNotContainsString('private', $output);
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame(1, $report['schema_version']);

        return [$exit, $report];
    }

    private function recipe(): string
    {
        return dirname(__DIR__, 2) . '/fixtures/export-recipe.php';
    }
}

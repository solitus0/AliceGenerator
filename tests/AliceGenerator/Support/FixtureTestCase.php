<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Support;

use Nelmio\Alice\Loader\NativeLoader;
use PHPUnit\Framework\TestCase;

abstract class FixtureTestCase extends TestCase
{
    /**
     * @return array<string, object>
     */
    protected function loadFixtures(string $content, string $extension = 'yaml'): array
    {
        $directory = sys_get_temp_dir() . '/alice-generator-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0o700)) {
            throw new \RuntimeException('Could not create the temporary fixture directory.');
        }

        $file = $directory . '/fixtures.' . $extension;

        try {
            if (file_put_contents($file, $content) === false) {
                throw new \RuntimeException('Could not write the generated fixture file.');
            }

            return (new NativeLoader())->loadFile($file)->getObjects();
        } finally {
            if (is_file($file)) {
                unlink($file);
            }

            rmdir($directory);
        }
    }
}

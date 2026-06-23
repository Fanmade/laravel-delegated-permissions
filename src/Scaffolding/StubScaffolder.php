<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Scaffolding;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies stub files into a target directory, stripping the ".stub" suffix and
 * replacing tokens (e.g. namespace placeholders) on the way. The host owns the
 * result, so existing files are skipped unless forced.
 */
final class StubScaffolder
{
    /**
     * @param  array<string, string>  $replacements
     * @return array{written: array<int, string>, skipped: array<int, string>}
     */
    public function copy(string $sourceDir, string $targetDir, array $replacements = [], bool $force = false): array
    {
        $written = [];
        $skipped = [];

        foreach ($this->stubFiles($sourceDir) as $file) {
            $relative = $this->relativeTarget($sourceDir, $file->getPathname());
            $target = rtrim($targetDir, '/').'/'.$relative;

            if (is_file($target) && ! $force) {
                $skipped[] = $target;

                continue;
            }

            $this->ensureDirectory(dirname($target));

            $contents = strtr((string) file_get_contents($file->getPathname()), $replacements);
            file_put_contents($target, $contents);

            $written[] = $target;
        }

        sort($written);
        sort($skipped);

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * @return iterable<int, SplFileInfo>
     */
    private function stubFiles(string $sourceDir): iterable
    {
        if (! is_dir($sourceDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'stub') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * The target path of a stub relative to the source root, with ".stub" removed.
     */
    private function relativeTarget(string $sourceDir, string $path): string
    {
        $relative = ltrim(substr($path, strlen(rtrim($sourceDir, '/'))), '/');

        return substr($relative, 0, -strlen('.stub'));
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}

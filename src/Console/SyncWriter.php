<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Support\Facades\File;

/**
 * @internal Filesystem apply helpers for SyncCommand: symlink-or-copy skills,
 * rewrite the `<package-boost-guidelines>` block in agent files, remove stale
 * skill destinations.
 */
final class SyncWriter
{
    public static function linkOrCopy(string $source, string $dest): void
    {
        if (file_exists($dest) || is_link($dest)) {
            is_link($dest) ? File::delete($dest) : File::deleteDirectory($dest);
        }

        File::ensureDirectoryExists(dirname($dest));

        $resolvedSource = realpath($source);
        $relativePath = SyncReporter::relativePath($resolvedSource !== false ? $resolvedSource : $source, dirname($dest));

        if (@symlink($relativePath, $dest)) {
            return;
        }

        File::ensureDirectoryExists($dest);
        File::copyDirectory($source, $dest);
    }

    public static function writeGuidelineBlock(string $filePath, string $block): void
    {
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        if (file_exists($filePath)) {
            $content = (string) file_get_contents($filePath);

            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace($pattern, SyncReporter::escapeReplacement($block), $content, 1)
                : rtrim($content) . "\n\n" . $block . "\n";
        } else {
            File::ensureDirectoryExists(dirname($filePath));
            $content = $block . "\n";
        }

        file_put_contents($filePath, $content);
    }

    public static function removeSkill(string $entry): void
    {
        is_link($entry) ? File::delete($entry) : File::deleteDirectory($entry);
    }
}

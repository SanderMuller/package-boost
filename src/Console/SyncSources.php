<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Symfony\Component\Finder\Finder;

/**
 * @internal Collects skills and guidelines from package-boost's shipped
 * resources and the user's `.ai/` directory. Shipped resources come first so
 * user entries override them in later iteration.
 */
final class SyncSources
{
    /**
     * @return array<string, string>  name => absolute source path
     */
    public static function skills(string $root): array
    {
        $skills = [];

        foreach (self::dirs($root, 'skills') as $dir) {
            $entries = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                $skills[basename($entry)] = $entry;
            }
        }

        return $skills;
    }

    public static function guidelines(string $root): string
    {
        $groups = [];

        foreach (self::dirs($root, 'guidelines') as $dir) {
            $group = self::readGuidelineDir($dir);

            if ($group !== '') {
                $groups[] = $group;
            }
        }

        return implode("\n\n---\n\n", $groups);
    }

    /**
     * @return array<int, string>
     */
    private static function dirs(string $root, string $kind): array
    {
        return array_values(array_filter(
            [
                dirname(__DIR__, 2) . '/resources/boost/' . $kind,
                $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . $kind,
            ],
            is_dir(...),
        ));
    }

    private static function readGuidelineDir(string $dir): string
    {
        $finder = Finder::create()
            ->files()
            ->in($dir)
            ->name('*.md')
            ->sortByName();

        $parts = [];

        foreach ($finder as $file) {
            $content = trim($file->getContents());

            if ($content !== '') {
                $parts[] = $content;
            }
        }

        return implode("\n\n", $parts);
    }
}

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

    /**
     * Safe read of `.mcp.json`: returns `[]` on missing file, invalid JSON,
     * or any non-object root (bare scalars, arrays of ints, etc.).
     *
     * @return array<mixed>
     */
    public static function mcpConfig(string $mcpPath): array
    {
        if (! file_exists($mcpPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($mcpPath), true);

        return is_array($decoded) ? $decoded : [];
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
     * Ordering is load-bearing: later dirs override earlier entries when a
     * skill name collides, and guideline groups concatenate in this order.
     * Shipped → vendor packages (alphabetical) → host `.ai/`, so host always
     * wins over a vendor contribution and vendors win over shipped defaults.
     *
     * @return array<int, string>
     */
    private static function dirs(string $root, string $kind): array
    {
        $dirs = array_merge(
            [dirname(__DIR__, 2) . '/resources/boost/' . $kind],
            self::vendorDirs($root, $kind),
            [$root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . $kind],
        );

        return array_values(array_filter($dirs, is_dir(...)));
    }

    /**
     * Discover `vendor/<vendor>/<name>/resources/boost/<kind>` contributions
     * from installed packages. Glob-based so test stubs work without a
     * composer install step.
     *
     * @return array<int, string>  absolute paths, sorted by package name
     */
    private static function vendorDirs(string $root, string $kind): array
    {
        if (! self::vendorDiscoveryEnabled()) {
            return [];
        }

        $excluded = self::vendorDiscoveryExclusions();
        $vendorRoot = $root . DIRECTORY_SEPARATOR . 'vendor';
        $matches = glob($vendorRoot . '/*/*/resources/boost/' . $kind, GLOB_ONLYDIR);

        if ($matches === false || $matches === []) {
            return [];
        }

        // Realpath of the shipped kind-dir — any vendor match that resolves to
        // the same directory (e.g. package-boost consumed as its own vendored
        // dep, or a symlinked dev checkout) would duplicate shipped content.
        // Structural guard, independent of the user-configurable exclude list.
        $shippedReal = realpath(dirname(__DIR__, 2) . '/resources/boost/' . $kind);

        $byPackage = [];

        foreach ($matches as $dir) {
            $relative = substr($dir, strlen($vendorRoot) + 1);
            $segments = explode(DIRECTORY_SEPARATOR, $relative);

            if (count($segments) < 2) {
                continue;
            }

            $package = $segments[0] . '/' . $segments[1];

            if (in_array($package, $excluded, true)) {
                continue;
            }

            if ($shippedReal !== false && realpath($dir) === $shippedReal) {
                continue;
            }

            $byPackage[$package] = $dir;
        }

        ksort($byPackage);

        return array_values($byPackage);
    }

    private static function vendorDiscoveryEnabled(): bool
    {
        if (! function_exists('config')) {
            return true;
        }

        return (bool) config('package-boost.discover_vendor_packages', true);
    }

    /**
     * @return array<int, string>
     */
    private static function vendorDiscoveryExclusions(): array
    {
        $default = ['sandermuller/package-boost'];

        if (! function_exists('config')) {
            return $default;
        }

        $configured = config('package-boost.excluded_vendor_packages', $default);

        return is_array($configured) ? array_values(array_filter($configured, is_string(...))) : $default;
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

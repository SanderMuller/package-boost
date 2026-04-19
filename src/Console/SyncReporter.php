<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Symfony\Component\Finder\Finder;

/**
 * @internal Shared helpers for SyncCommand action planning and output rendering.
 */
final class SyncReporter
{
    /**
     * @return array{0: 'new'|'updated'|'unchanged', 1: string}
     */
    public static function planSkillAction(string $source, string $dest): array
    {
        if (! is_link($dest) && ! file_exists($dest)) {
            return ['new', ''];
        }

        if (is_link($dest)) {
            $resolvedSource = realpath($source);
            $expected = self::relativePath(
                $resolvedSource !== false ? $resolvedSource : $source,
                dirname($dest),
            );
            $actual = readlink($dest);

            if ($actual === $expected) {
                return ['unchanged', ''];
            }

            return ['updated', "symlink → {$expected}"];
        }

        if (is_dir($dest)) {
            $sourceTree = self::hashTree($source);
            $destTree = self::hashTree($dest);

            if ($sourceTree === $destTree) {
                return ['unchanged', ''];
            }

            return ['updated', self::renderContentHint($sourceTree, $destTree)];
        }

        return ['unchanged', ''];
    }

    /**
     * Recursive map of `{relativePath => md5}` for files under $dir. Skips
     * dotfiles at any level so filesystem/tooling detritus (.DS_Store,
     * .gitattributes) doesn't register as content drift.
     *
     * @return array<string, string>
     */
    public static function hashTree(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $map = [];

        foreach (Finder::create()->files()->ignoreDotFiles(true)->in($dir) as $file) {
            $hash = hash_file('xxh128', $file->getPathname());
            $map[$file->getRelativePathname()] = $hash !== false ? $hash : '';
        }

        ksort($map);

        return $map;
    }

    /**
     * @param  array<string, string>  $source
     * @param  array<string, string>  $dest
     */
    public static function renderContentHint(array $source, array $dest): string
    {
        $differ = [];
        $added = [];
        $removed = [];

        foreach ($source as $rel => $hash) {
            if (! isset($dest[$rel])) {
                $added[] = $rel;
            } elseif ($dest[$rel] !== $hash) {
                $differ[] = $rel;
            }
        }

        foreach (array_keys($dest) as $rel) {
            if (! isset($source[$rel])) {
                $removed[] = $rel;
            }
        }

        $total = count($differ) + count($added) + count($removed);

        return 'content: ' . ($total <= 3
            ? self::renderNamedHint($differ, $added, $removed)
            : self::renderCountHint($differ, $added, $removed));
    }

    /**
     * @param  array<int, string>  $differ
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     */
    private static function renderNamedHint(array $differ, array $added, array $removed): string
    {
        $parts = [];

        foreach ($differ as $file) {
            $parts[] = "{$file} differs";
        }

        foreach ($added as $file) {
            $parts[] = "{$file} added";
        }

        foreach ($removed as $file) {
            $parts[] = "{$file} removed";
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, string>  $differ
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     */
    private static function renderCountHint(array $differ, array $added, array $removed): string
    {
        $parts = [];

        if ($differ !== []) {
            $parts[] = count($differ) . ' differ';
        }

        if ($added !== []) {
            $parts[] = count($added) . ' added';
        }

        if ($removed !== []) {
            $parts[] = count($removed) . ' removed';
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{0: 'new'|'updated'|'unchanged', 1: string, 2: ?int}  action, display hint, raw line delta
     */
    public static function planGuidelineAction(string $filePath, string $block): array
    {
        if (! file_exists($filePath)) {
            return ['new', '', null];
        }

        $content = (string) file_get_contents($filePath);
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        $newContent = preg_match($pattern, $content) === 1
            ? (string) preg_replace($pattern, self::escapeReplacement($block), $content, 1)
            : rtrim($content) . "\n\n" . $block . "\n";

        if ($newContent === $content) {
            return ['unchanged', '', null];
        }

        $delta = self::lineDelta($content, $newContent);

        return ['updated', self::formatLineDelta($delta), $delta];
    }

    /**
     * @param  array<mixed>  $existing
     *
     * @return array{0: 'new'|'updated'|'unchanged', 1: array<mixed>}
     */
    public static function planMcpAction(string $mcpPath, array $existing): array
    {
        $mcpServers = isset($existing['mcpServers']) && is_array($existing['mcpServers'])
            ? $existing['mcpServers']
            : [];
        $mcpServers['laravel-boost'] = [
            'command' => 'vendor/bin/testbench',
            'args' => ['boost:mcp'],
        ];

        $desired = $existing;
        $desired['mcpServers'] = $mcpServers;

        if (! file_exists($mcpPath)) {
            return ['new', $desired];
        }

        if ($existing === $desired) {
            return ['unchanged', $desired];
        }

        return ['updated', $desired];
    }

    public static function escapeReplacement(string $block): string
    {
        return strtr($block, ['\\' => '\\\\', '$' => '\\$']);
    }

    public static function glyph(string $action): string
    {
        return match ($action) {
            'new' => '+',
            'updated' => '~',
            'removed' => '-',
            'unchanged' => '=',
            default => '?',
        };
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function summaryLine(array $counts): string
    {
        $parts = [];

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $parts[] = "{$count} {$label}";
            }
        }

        return 'total: ' . ($parts === [] ? '0 changes' : implode(', ', $parts));
    }

    public static function relativePath(string $target, string $from): string
    {
        $target = str_replace('\\', '/', $target);
        $resolvedFrom = realpath($from);
        $from = str_replace('\\', '/', $resolvedFrom !== false ? $resolvedFrom : $from);

        $targetParts = explode('/', $target);
        $fromParts = explode('/', $from);

        $common = 0;

        while ($common < count($targetParts) && $common < count($fromParts) && $targetParts[$common] === $fromParts[$common]) {
            ++$common;
        }

        $ups = count($fromParts) - $common;

        return str_repeat('../', $ups) . implode('/', array_slice($targetParts, $common));
    }

    private static function lineDelta(string $before, string $after): int
    {
        $beforeCount = substr_count($before, "\n") + ($before === '' ? 0 : 1);
        $afterCount = substr_count($after, "\n") + ($after === '' ? 0 : 1);

        return $afterCount - $beforeCount;
    }

    private static function formatLineDelta(int $delta): string
    {
        if ($delta === 0) {
            return 'content updated';
        }

        $sign = $delta > 0 ? '+' : '';

        return "{$sign}{$delta} lines";
    }
}

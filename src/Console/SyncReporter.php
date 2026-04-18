<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

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

            return ['updated', " (symlink → {$expected})"];
        }

        return ['unchanged', ''];
    }

    /**
     * @return array{0: 'new'|'updated'|'unchanged', 1: string}
     */
    public static function planGuidelineAction(string $filePath, string $block): array
    {
        if (! file_exists($filePath)) {
            return ['new', ''];
        }

        $content = (string) file_get_contents($filePath);
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        $newContent = preg_match($pattern, $content) === 1
            ? (string) preg_replace($pattern, self::escapeReplacement($block), $content, 1)
            : rtrim($content) . "\n\n" . $block . "\n";

        if ($newContent === $content) {
            return ['unchanged', ''];
        }

        return ['updated', self::lineDiff($content, $newContent)];
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

    private static function lineDiff(string $before, string $after): string
    {
        $beforeCount = substr_count($before, "\n") + ($before === '' ? 0 : 1);
        $afterCount = substr_count($after, "\n") + ($after === '' ? 0 : 1);
        $delta = $afterCount - $beforeCount;

        if ($delta === 0) {
            return ' (content updated)';
        }

        $sign = $delta > 0 ? '+' : '';

        return " ({$sign}{$delta} lines)";
    }
}

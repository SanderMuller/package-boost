<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

/**
 * @internal Pure planning helpers extracted from `SyncCommand`. Each
 * method walks the host's filesystem to build a `SyncPlan` for one
 * category — skills, guidelines, or MCP — without writing anything.
 * The companion writer lives in `SyncWriter`; orchestration stays in
 * `SyncCommand`.
 */
final class SyncPlanner
{
    /**
     * @param  array<string, string>  $skills  name => absolute source path
     * @param  array<int, string>  $skillTargets  per-agent destination dirs
     */
    public static function planSkills(string $root, array $skills, array $skillTargets): SyncPlan
    {
        if ($skills === []) {
            return SyncPlan::skipped('no-sources');
        }

        // Cache `hashTree($source)` lazily — only populated when the copy
        // path of `planSkillAction` actually needs it (symlink dests skip
        // hashing entirely). Avoids N×M tree walks when sync falls back
        // to copy mode and the same source contributes to multiple agent
        // target dirs.
        $sourceHashes = [];

        $new = [];
        $updated = [];
        $unchanged = [];
        $removed = [];

        foreach ($skillTargets as $target) {
            $targetDir = $root . DIRECTORY_SEPARATOR . $target;

            foreach ($skills as $name => $source) {
                $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
                $hashes = self::resolveSourceHashes($sourceHashes, $name, $source, $dest);
                [$action, $hint] = SyncReporter::planSkillAction($source, $dest, $hashes);
                $entry = new SyncAction("{$target}/{$name}", hint: $hint !== '' ? $hint : null);

                match ($action) {
                    'new' => $new[] = $entry,
                    'updated' => $updated[] = $entry,
                    'unchanged' => $unchanged[] = $entry,
                };
            }

            foreach (self::existingSkillNames($targetDir) as $existing) {
                if (isset($skills[$existing])) {
                    continue;
                }

                $removed[] = new SyncAction("{$target}/{$existing}");
            }
        }

        return new SyncPlan(new: $new, updated: $updated, unchanged: $unchanged, removed: $removed);
    }

    /**
     * Hash the source tree the first time we need it for a skill, then
     * reuse across every remaining agent target. We only hash when the
     * dest is a directory (the copy fallback) — keeps the symlink-only
     * fast path free of any hashing work.
     *
     * @param  array<string, array<string, string>>  $cache
     * @return ?array<string, string>
     */
    private static function resolveSourceHashes(array &$cache, string $name, string $source, string $dest): ?array
    {
        if (! is_dir($dest) || is_link($dest)) {
            return null;
        }

        if (! isset($cache[$name])) {
            $cache[$name] = SyncReporter::hashTree($source);
        }

        return $cache[$name];
    }

    /**
     * @param  array<int, string>  $guidelineTargets
     * @return array{0: SyncPlan, 1: string}  plan + composed block for write phase
     */
    public static function planGuidelines(string $root, array $guidelineTargets): array
    {
        $guidelines = SyncSources::guidelines($root);

        if ($guidelines === '') {
            return [SyncPlan::skipped('no-sources'), ''];
        }

        $block = "<package-boost-guidelines>\n{$guidelines}\n</package-boost-guidelines>";

        $new = [];
        $updated = [];
        $unchanged = [];

        foreach ($guidelineTargets as $target) {
            $filePath = $root . DIRECTORY_SEPARATOR . $target;
            [$action, $hint, $lineDelta] = SyncReporter::planGuidelineAction($filePath, $block);
            $entry = new SyncAction($target, hint: $hint !== '' ? $hint : null, lineDelta: $lineDelta);

            match ($action) {
                'new' => $new[] = $entry,
                'updated' => $updated[] = $entry,
                'unchanged' => $unchanged[] = $entry,
            };
        }

        return [new SyncPlan(new: $new, updated: $updated, unchanged: $unchanged), $block];
    }

    /**
     * @return array{0: SyncPlan, 1: array<mixed>}  plan + desired config for write phase
     */
    public static function planMcp(string $root, bool $boostInstalled): array
    {
        if (! $boostInstalled) {
            return [SyncPlan::skipped('laravel-boost-not-installed'), []];
        }

        $mcpPath = $root . DIRECTORY_SEPARATOR . '.mcp.json';
        [$action, $desired] = SyncReporter::planMcpAction($mcpPath, SyncSources::mcpConfig($mcpPath));

        $entry = new SyncAction('.mcp.json');
        $plan = match ($action) {
            'new' => new SyncPlan(new: [$entry]),
            'updated' => new SyncPlan(updated: [$entry]),
            'unchanged' => new SyncPlan(unchanged: [$entry]),
        };

        return [$plan, $desired];
    }

    /**
     * @return array<int, string>
     */
    private static function existingSkillNames(string $targetDir): array
    {
        if (! is_dir($targetDir)) {
            return [];
        }

        $entries = glob($targetDir . DIRECTORY_SEPARATOR . '*');

        if ($entries === false) {
            return [];
        }

        return array_map(basename(...), $entries);
    }
}

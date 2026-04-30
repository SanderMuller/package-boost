<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;

/**
 * @internal Locates leftover sync output for agents that fell out of the
 * user's selection. When the user narrows `package-boost.agents` from
 * "all" to a subset, sync only writes to the selected paths — paths
 * for the removed agents stay populated and silently active. This
 * helper enumerates them so `SyncCommand` can warn the user;
 * auto-removal is intentionally not done here because guideline files
 * may carry user content outside our tag block.
 */
final class DeselectedAgentArtifacts
{
    /**
     * @param  array<int, Agent>  $selected
     * @return array<int, string>  human-readable orphan path descriptions
     */
    public static function locate(string $root, array $selected): array
    {
        $orphans = self::collectAgentOrphans(
            $root,
            array_flip(Registry::skillTargets($selected)),
            array_flip(Registry::guidelineTargets($selected)),
        );

        if (! self::containsClaude($selected)) {
            $description = self::describeOrphanMcpFile($root);

            if ($description !== null) {
                $orphans['.mcp.json'] = $description;
            }
        }

        return array_values($orphans);
    }

    /**
     * @param  array<string, int>  $selectedSkillPaths
     * @param  array<string, int>  $selectedGuidelinePaths
     * @return array<string, string>
     */
    private static function collectAgentOrphans(string $root, array $selectedSkillPaths, array $selectedGuidelinePaths): array
    {
        $orphans = [];

        foreach (Registry::all() as $agent) {
            if (! isset($selectedSkillPaths[$agent->skillsPath])) {
                $orphans = self::merge($orphans, $agent->skillsPath, self::describeOrphanSkillDir($root, $agent->skillsPath));
            }

            if (! isset($selectedGuidelinePaths[$agent->guidelinesPath])) {
                $orphans = self::merge($orphans, $agent->guidelinesPath, self::describeOrphanGuidelineFile($root, $agent->guidelinesPath));
            }
        }

        return $orphans;
    }

    /**
     * @param  array<string, string>  $orphans
     * @return array<string, string>
     */
    private static function merge(array $orphans, string $key, ?string $description): array
    {
        if ($description !== null) {
            $orphans[$key] = $description;
        }

        return $orphans;
    }

    private static function describeOrphanSkillDir(string $root, string $relative): ?string
    {
        $dir = $root . DIRECTORY_SEPARATOR . $relative;

        if (! is_dir($dir)) {
            return null;
        }

        $entries = glob($dir . '/*');

        if ($entries === false || $entries === []) {
            return null;
        }

        $count = count($entries);

        return $relative . '/ (' . $count . ' entr' . ($count === 1 ? 'y' : 'ies') . ')';
    }

    private static function describeOrphanGuidelineFile(string $root, string $relative): ?string
    {
        $file = $root . DIRECTORY_SEPARATOR . $relative;

        if (! is_file($file)) {
            return null;
        }

        if (! str_contains((string) file_get_contents($file), '<package-boost-guidelines>')) {
            return null;
        }

        return $relative . ' (contains <package-boost-guidelines> block)';
    }

    private static function describeOrphanMcpFile(string $root): ?string
    {
        $path = $root . DIRECTORY_SEPARATOR . '.mcp.json';

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $servers = is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];

        if (! isset($servers['laravel-boost'])) {
            return null;
        }

        return '.mcp.json (laravel-boost mcpServers entry present)';
    }

    /**
     * @param  array<int, Agent>  $selected
     */
    private static function containsClaude(array $selected): bool
    {
        foreach ($selected as $agent) {
            if ($agent->name === 'claude_code') {
                return true;
            }
        }

        return false;
    }
}

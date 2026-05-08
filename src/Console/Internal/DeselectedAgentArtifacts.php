<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;

/**
 * @internal Locates (and optionally prunes) leftover sync output for agents
 * that fell out of the user's selection. When the user narrows
 * `package-boost.agents` from "all" to a subset, sync only writes to the
 * selected paths — paths for the removed agents stay populated and
 * silently active. {@see locate()} enumerates them for warnings; {@see
 * prune()} (gated behind `--prune-orphans`) removes them, refusing to
 * touch guideline files that carry user content outside our tag block.
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

    /**
     * Remove (or strip) every orphan artefact identified by {@see locate()}.
     *
     * Skill dirs are written entirely by sync, so they are removed wholesale.
     * Guideline files have the `<package-boost-guidelines>` block stripped;
     * the file itself is deleted only when the strip leaves nothing but
     * whitespace. `.mcp.json` has the `laravel-boost` mcpServers entry
     * removed; the file is deleted when no other servers remain.
     *
     * @param  array<int, Agent>  $selected
     * @return array<int, string>  list of pruned target descriptions (relative paths, with optional `(...)` suffix when only the package-boost block was stripped)
     */
    public static function prune(string $root, array $selected): array
    {
        $removed = [];
        $selectedSkillPaths = array_flip(Registry::skillTargets($selected));
        $selectedGuidelinePaths = array_flip(Registry::guidelineTargets($selected));

        foreach (Registry::all() as $agent) {
            if (! isset($selectedSkillPaths[$agent->skillsPath])) {
                self::pruneSkillDir($root, $agent->skillsPath, $removed);
            }

            if (! isset($selectedGuidelinePaths[$agent->guidelinesPath])) {
                self::pruneGuidelineFile($root, $agent->guidelinesPath, $removed);
            }
        }

        if (! self::containsClaude($selected)) {
            self::pruneMcpEntry($root, $removed);
        }

        return array_values(array_unique($removed));
    }

    /**
     * @param  array<int, string>  $removed
     */
    private static function pruneSkillDir(string $root, string $relative, array &$removed): void
    {
        $dir = $root . DIRECTORY_SEPARATOR . $relative;

        if (! is_dir($dir)) {
            return;
        }

        File::deleteDirectory($dir);
        $removed[] = $relative . '/';
    }

    /**
     * @param  array<int, string>  $removed
     */
    private static function pruneGuidelineFile(string $root, string $relative, array &$removed): void
    {
        $file = $root . DIRECTORY_SEPARATOR . $relative;

        if (! is_file($file)) {
            return;
        }

        $contents = (string) file_get_contents($file);

        if (! str_contains($contents, '<package-boost-guidelines>')) {
            return;
        }

        $stripped = (string) preg_replace(
            '/<package-boost-guidelines>.*?<\/package-boost-guidelines>\s*/s',
            '',
            $contents,
        );

        if (trim($stripped) === '') {
            File::delete($file);
            $removed[] = $relative;

            return;
        }

        File::put($file, rtrim($stripped) . "\n");
        $removed[] = $relative . ' (block stripped, user content kept)';
    }

    /**
     * @param  array<int, string>  $removed
     */
    private static function pruneMcpEntry(string $root, array &$removed): void
    {
        $path = $root . DIRECTORY_SEPARATOR . '.mcp.json';

        if (! is_file($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return;
        }

        $servers = is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];

        if (! isset($servers['laravel-boost'])) {
            return;
        }

        unset($servers['laravel-boost']);

        if ($servers === [] && array_keys($decoded) === ['mcpServers']) {
            File::delete($path);
            $removed[] = '.mcp.json';

            return;
        }

        if ($servers === []) {
            unset($decoded['mcpServers']);
        } else {
            $decoded['mcpServers'] = $servers;
        }

        File::put($path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $removed[] = '.mcp.json (laravel-boost entry stripped)';
    }
}

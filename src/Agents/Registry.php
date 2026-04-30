<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Agents;

/**
 * Frozen list of supported AI agents. Paths and names mirror
 * `laravel/boost` `main` @ `8ed9f84` (`src/Install/Agents/*` +
 * `src/BoostManager.php:22-32`). Detection markers are package-boost's
 * own first-run heuristic; not sourced from Boost.
 */
final class Registry
{
    /**
     * @return array<int, Agent>
     */
    public static function all(): array
    {
        return [
            new Agent(
                name: 'claude_code',
                label: 'Claude Code',
                guidelinesPath: 'CLAUDE.md',
                skillsPath: '.claude/skills',
                detectionMarkers: ['CLAUDE.md', '.claude'],
            ),
            new Agent(
                name: 'cursor',
                label: 'Cursor',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.cursor/skills',
                detectionMarkers: ['.cursor', '.cursorrules'],
            ),
            new Agent(
                name: 'copilot',
                label: 'GitHub Copilot',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.github/skills',
                detectionMarkers: ['.github/copilot-instructions.md', '.github/instructions'],
            ),
            new Agent(
                name: 'codex',
                label: 'Codex CLI',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                detectionMarkers: ['.codex', '.codex/config.toml'],
            ),
            new Agent(
                name: 'gemini',
                label: 'Gemini CLI',
                guidelinesPath: 'GEMINI.md',
                skillsPath: '.agents/skills',
                detectionMarkers: ['GEMINI.md', '.gemini'],
            ),
            new Agent(
                name: 'junie',
                label: 'Junie (JetBrains)',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.junie/skills',
                detectionMarkers: ['.junie'],
            ),
            new Agent(
                name: 'kiro',
                label: 'Kiro',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.kiro/skills',
                detectionMarkers: ['.kiro'],
            ),
            new Agent(
                name: 'opencode',
                label: 'OpenCode',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                detectionMarkers: ['opencode.json', '.opencode'],
            ),
            new Agent(
                name: 'amp',
                label: 'Amp',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                detectionMarkers: ['.amp'],
            ),
        ];
    }

    public static function find(string $name): ?Agent
    {
        foreach (self::all() as $agent) {
            if ($agent->name === $name) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Filter the registry by a user-selected name list. `null` returns
     * the full set (zero-config default). Unknown names are dropped
     * silently here — callers that want to surface typos must compare
     * the input list against the result.
     *
     * @param  ?array<int, string>  $names
     * @return array<int, Agent>
     */
    public static function forSelection(?array $names): array
    {
        if ($names === null) {
            return self::all();
        }

        $set = array_flip($names);

        return array_values(array_filter(
            self::all(),
            static fn (Agent $agent): bool => isset($set[$agent->name]),
        ));
    }

    /**
     * Unique skill destination paths for a given selection, deduped so
     * `.agents/skills` (shared by Codex, Gemini, OpenCode, Amp) is
     * written once even when multiple of those agents are selected.
     *
     * @param  array<int, Agent>  $agents
     * @return array<int, string>
     */
    public static function skillTargets(array $agents): array
    {
        return array_values(array_unique(array_map(
            static fn (Agent $agent): string => $agent->skillsPath,
            $agents,
        )));
    }

    /**
     * @param  array<int, Agent>  $agents
     * @return array<int, string>
     */
    public static function guidelineTargets(array $agents): array
    {
        return array_values(array_unique(array_map(
            static fn (Agent $agent): string => $agent->guidelinesPath,
            $agents,
        )));
    }

    /**
     * @return array<int, string>  registered agent names
     */
    public static function names(): array
    {
        return array_map(static fn (Agent $agent): string => $agent->name, self::all());
    }

    /**
     * Names supplied by the user that don't correspond to a registered
     * agent. Used by `SyncCommand` to surface typos without breaking
     * sync — `forSelection()` already drops unknowns silently.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public static function unknownNames(array $names): array
    {
        return array_values(array_diff($names, self::names()));
    }
}

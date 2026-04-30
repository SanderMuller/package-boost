<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests\Agents;

use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;

it('exposes the 9 supported agents in a frozen order', function (): void {
    $names = array_map(static fn (Agent $a): string => $a->name, Registry::all());

    expect($names)->toBe([
        'claude_code',
        'cursor',
        'copilot',
        'codex',
        'gemini',
        'junie',
        'kiro',
        'opencode',
        'amp',
    ]);
});

/**
 * @return array{guidelinesPath: string, skillsPath: string}
 */
function agentPaths(string $name): array
{
    $agent = Registry::find($name);

    if (! $agent instanceof Agent) {
        throw new \RuntimeException("Registry has no agent named '{$name}'.");
    }

    return ['guidelinesPath' => $agent->guidelinesPath, 'skillsPath' => $agent->skillsPath];
}

it('mirrors laravel/boost agent paths verbatim', function (): void {
    expect(agentPaths('claude_code'))->toBe(['guidelinesPath' => 'CLAUDE.md', 'skillsPath' => '.claude/skills'])
        ->and(agentPaths('gemini'))->toBe(['guidelinesPath' => 'GEMINI.md', 'skillsPath' => '.agents/skills'])
        ->and(agentPaths('cursor'))->toBe(['guidelinesPath' => 'AGENTS.md', 'skillsPath' => '.cursor/skills'])
        ->and(agentPaths('copilot'))->toBe(['guidelinesPath' => 'AGENTS.md', 'skillsPath' => '.github/skills'])
        ->and(agentPaths('junie'))->toBe(['guidelinesPath' => 'AGENTS.md', 'skillsPath' => '.junie/skills'])
        ->and(agentPaths('kiro'))->toBe(['guidelinesPath' => 'AGENTS.md', 'skillsPath' => '.kiro/skills']);
});

it('returns null on unknown agent name', function (): void {
    expect(Registry::find('unknown'))->toBeNull();
});

it('finds an agent by name', function (): void {
    $agent = Registry::find('claude_code');

    expect($agent)->toBeInstanceOf(Agent::class);
    assert($agent instanceof Agent);

    expect($agent->name)->toBe('claude_code')
        ->and($agent->label)->toBe('Claude Code');
});

it('returns the full set when selection is null', function (): void {
    expect(Registry::forSelection(null))->toHaveCount(9);
});

it('filters the registry to selected names', function (): void {
    $selected = Registry::forSelection(['claude_code', 'cursor']);

    expect($selected)->toHaveCount(2)
        ->and(array_map(static fn (Agent $a): string => $a->name, $selected))
        ->toBe(['claude_code', 'cursor']);
});

it('drops unknown names from a selection', function (): void {
    $selected = Registry::forSelection(['claude_code', 'bogus']);

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->name)->toBe('claude_code');
});

it('returns an empty list for an empty selection', function (): void {
    expect(Registry::forSelection([]))->toBe([]);
});

it('dedupes shared .agents/skills across Codex/Gemini/OpenCode/Amp', function (): void {
    $shared = Registry::forSelection(['codex', 'gemini', 'opencode', 'amp']);

    expect(Registry::skillTargets($shared))->toBe(['.agents/skills']);
});

it('produces 6 unique skill targets when all 9 agents are selected', function (): void {
    $targets = Registry::skillTargets(Registry::all());

    expect($targets)->toBe([
        '.claude/skills',
        '.cursor/skills',
        '.github/skills',
        '.agents/skills',
        '.junie/skills',
        '.kiro/skills',
    ]);
});

it('produces 3 unique guideline targets when all 9 agents are selected', function (): void {
    $targets = Registry::guidelineTargets(Registry::all());

    expect($targets)->toBe([
        'CLAUDE.md',
        'AGENTS.md',
        'GEMINI.md',
    ]);
});

it('returns a single guideline target when only Claude is selected', function (): void {
    $selected = Registry::forSelection(['claude_code']);

    expect(Registry::guidelineTargets($selected))->toBe(['CLAUDE.md'])
        ->and(Registry::skillTargets($selected))->toBe(['.claude/skills']);
});

it('returns AGENTS.md only when Cursor and Junie share guidelines', function (): void {
    $selected = Registry::forSelection(['cursor', 'junie']);

    expect(Registry::guidelineTargets($selected))->toBe(['AGENTS.md']);
});

it('exposes detection markers as a non-empty list per agent', function (): void {
    foreach (Registry::all() as $agent) {
        expect($agent->detectionMarkers)->not->toBeEmpty();
    }
});

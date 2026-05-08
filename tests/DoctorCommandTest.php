<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

const DOCTOR_AGENT_DIRS = [
    '.claude/skills',
    '.cursor/skills',
    '.agents/skills',
    '.github/skills',
    '.junie/skills',
    '.kiro/skills',
];

/**
 * Real `.gitattributes` is committed; back it up around tests that
 * exercise the lean-driven path so `.gitattributes` ends up untouched
 * regardless of pass/fail.
 */
$doctorGitAttributes = null;

beforeEach(function () use (&$doctorGitAttributes): void {
    foreach (DOCTOR_AGENT_DIRS as $dir) {
        File::deleteDirectory(package_path($dir));
    }

    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('GEMINI.md'));
    File::delete(package_path('.mcp.json'));

    File::deleteDirectory(package_path('.ai/skills/bad-frontmatter-skill'));

    $doctorGitAttributes = File::exists(package_path('.gitattributes'))
        ? (string) file_get_contents(package_path('.gitattributes'))
        : null;
});

afterEach(function () use (&$doctorGitAttributes): void {
    foreach (DOCTOR_AGENT_DIRS as $dir) {
        File::deleteDirectory(package_path($dir));
    }

    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('GEMINI.md'));
    File::delete(package_path('.mcp.json'));

    File::deleteDirectory(package_path('.ai/skills/bad-frontmatter-skill'));

    if ($doctorGitAttributes === null) {
        File::delete(package_path('.gitattributes'));
    } else {
        File::put(package_path('.gitattributes'), $doctorGitAttributes);
    }
});

it('exits non-zero when sync drift is present', function (): void {
    $exit = Artisan::call('package-boost:doctor');

    expect($exit)->toBe(1);
});

it('reports unknown agents in config', function (): void {
    config()->set('package-boost.agents', ['claude_code', 'made_up_agent']);

    Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)
        ->toContain('Unknown agent name')
        ->toContain('made_up_agent');
});

it('reports SKILL.md frontmatter issues', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/bad-frontmatter-skill'));
    File::put(
        package_path('.ai/skills/bad-frontmatter-skill/SKILL.md'),
        "no frontmatter here\n",
    );

    Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)
        ->toContain('SKILL.md frontmatter issues')
        ->toContain('bad-frontmatter-skill');
});

it('emits machine-readable JSON when --format=json', function (): void {
    Artisan::call('package-boost:doctor', ['--format' => 'json']);
    $output = Artisan::output();

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect(is_array($decoded) ? $decoded : [])->toMatchArray([
        'schema' => 1,
    ]);
    expect(is_array($decoded) && isset($decoded['drift']) && is_array($decoded['drift']))->toBeTrue();
    expect(is_array($decoded) && isset($decoded['agents']) && is_array($decoded['agents']))->toBeTrue();
});

it('exits zero on a fully synced package', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)->toContain('Drift: skills=0');
    expect($exit)->toBe(0);
});

it('reports `laravel-boost-not-installed` as MCP status, not drift, on framework-agnostic hosts', function (): void {
    app()->bind('package-boost.boost-detector', static fn (): callable => static fn (): bool => false);

    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:doctor', ['--format' => 'json']);
    $output = Artisan::output();
    $decoded = json_decode($output, true);

    $mcp = is_array($decoded) && is_array($decoded['drift'] ?? null) && is_string($decoded['drift']['mcp'] ?? null)
        ? $decoded['drift']['mcp']
        : '';

    expect($mcp)->toBe('laravel-boost-not-installed');
    expect($exit)->toBe(0);
});

it('counts a stale skill dir (host source removed) as drift', function (): void {
    Artisan::call('package-boost:sync', ['--skills' => true]);

    // Plant a stale skill dir under one of the agent skill targets.
    File::ensureDirectoryExists(package_path('.claude/skills/orphaned-skill-dir'));
    File::put(package_path('.claude/skills/orphaned-skill-dir/SKILL.md'), "---\nname: orphaned-skill-dir\ndescription: Stale.\n---\n");

    $exit = Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($exit)->toBe(1);
    expect($output)->not->toContain('Drift: skills=0');
});

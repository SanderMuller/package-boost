<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    // Clean up any generated files from previous runs
    File::deleteDirectory(package_path('.claude/skills'));
    File::deleteDirectory(package_path('.github/skills'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('.github/copilot-instructions.md'));
});

it('syncs skills to agent directories', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/test-skill'));
    File::put(package_path('.ai/skills/test-skill/SKILL.md'), "---\nname: test-skill\ndescription: A test skill.\n---\n\n# Test Skill\n");

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('Synced 1 skills to 2 agent directories')
        ->assertSuccessful();

    expect(File::exists(package_path('.claude/skills/test-skill/SKILL.md')))->toBeTrue();
    expect(File::exists(package_path('.github/skills/test-skill/SKILL.md')))->toBeTrue();

    // Clean up
    File::deleteDirectory(package_path('.ai/skills/test-skill'));
    File::deleteDirectory(package_path('.claude/skills/test-skill'));
    File::deleteDirectory(package_path('.github/skills/test-skill'));
});

it('syncs guidelines to agent files', function (): void {
    File::ensureDirectoryExists(package_path('.ai/guidelines'));
    File::put(package_path('.ai/guidelines/test.md'), "## Test Guideline\n\nDo the thing.\n");

    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('Synced guidelines to 3 agent files')
        ->assertSuccessful();

    $claude = File::get(package_path('CLAUDE.md'));
    expect($claude)->toContain('<package-boost-guidelines>');
    expect($claude)->toContain('Do the thing.');

    expect(File::exists(package_path('AGENTS.md')))->toBeTrue();
    expect(File::exists(package_path('.github/copilot-instructions.md')))->toBeTrue();

    // Clean up
    File::delete(package_path('.ai/guidelines/test.md'));
    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('.github/copilot-instructions.md'));
});

it('removes stale skills from target directories', function (): void {
    // Create a skill and sync it
    File::ensureDirectoryExists(package_path('.ai/skills/keep-me'));
    File::put(package_path('.ai/skills/keep-me/SKILL.md'), "---\nname: keep-me\ndescription: Keep.\n---\n");

    // Manually place a stale skill in the target
    File::ensureDirectoryExists(package_path('.claude/skills/stale-skill'));
    File::put(package_path('.claude/skills/stale-skill/SKILL.md'), 'stale');

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->assertSuccessful();

    expect(File::exists(package_path('.claude/skills/keep-me/SKILL.md')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/stale-skill')))->toBeFalse();

    // Clean up
    File::deleteDirectory(package_path('.ai/skills/keep-me'));
    File::deleteDirectory(package_path('.claude/skills'));
    File::deleteDirectory(package_path('.github/skills'));
});

it('warns when no skills directory exists', function (): void {
    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('No .ai/skills/ directory found')
        ->assertSuccessful();
});

it('warns when no guidelines directory exists', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('No .ai/guidelines/ directory found')
        ->assertSuccessful();
});

it('replaces existing guideline block on re-sync', function (): void {
    File::ensureDirectoryExists(package_path('.ai/guidelines'));
    File::put(package_path('.ai/guidelines/test.md'), "## Version 1\n");

    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    File::put(package_path('.ai/guidelines/test.md'), "## Version 2\n");

    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $content = File::get(package_path('CLAUDE.md'));
    expect($content)->toContain('Version 2');
    expect($content)->not->toContain('Version 1');
    // Only one block
    expect(substr_count($content, '<package-boost-guidelines>'))->toBe(1);

    // Clean up
    File::delete(package_path('.ai/guidelines/test.md'));
    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('.github/copilot-instructions.md'));
});

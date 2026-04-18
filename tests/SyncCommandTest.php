<?php declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    File::deleteDirectory(package_path('.ai'));
    File::deleteDirectory(package_path('.claude/skills'));
    File::deleteDirectory(package_path('.github/skills'));
    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('.github/copilot-instructions.md'));
});

it('syncs user and shipped skills to agent directories', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/test-skill'));
    File::put(package_path('.ai/skills/test-skill/SKILL.md'), "---\nname: test-skill\ndescription: A test skill.\n---\n\n# Test Skill\n");

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('Synced 2 skills to 2 agent directories')
        ->assertSuccessful();

    expect(is_link(package_path('.claude/skills/test-skill')))->toBeTrue();
    expect(is_link(package_path('.claude/skills/package-development')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/test-skill/SKILL.md')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeTrue();
});

it('ships the package-development skill even without a user .ai/skills directory', function (): void {
    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('Synced 1 skills to 2 agent directories')
        ->assertSuccessful();

    expect(is_link(package_path('.claude/skills/package-development')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeTrue();
});

it('syncs shipped foundation and user guidelines into agent files', function (): void {
    File::ensureDirectoryExists(package_path('.ai/guidelines'));
    File::put(package_path('.ai/guidelines/test.md'), "## Test Guideline\n\nDo the thing.\n");

    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('Synced guidelines to 3 agent files')
        ->assertSuccessful();

    $claude = File::get(package_path('CLAUDE.md'));
    expect($claude)->toContain('<package-boost-guidelines>')
        ->and($claude)->toContain('# Package Boost Guidelines')
        ->and($claude)->toContain('Do the thing.');

    $foundationPos = strpos($claude, '# Package Boost Guidelines');
    $userPos = strpos($claude, 'Do the thing.');
    expect($foundationPos !== false && $userPos !== false && $foundationPos < $userPos)->toBeTrue();

    expect(File::exists(package_path('AGENTS.md')))->toBeTrue();
    expect(File::exists(package_path('.github/copilot-instructions.md')))->toBeTrue();
});

it('ships foundation guideline even without a user .ai/guidelines directory', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('Synced guidelines to 3 agent files')
        ->assertSuccessful();

    $claude = File::get(package_path('CLAUDE.md'));
    expect($claude)->toContain('# Package Boost Guidelines')
        ->and($claude)->toContain('Foundational Context');
});

it('removes stale skills from target directories', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/keep-me'));
    File::put(package_path('.ai/skills/keep-me/SKILL.md'), "---\nname: keep-me\ndescription: Keep.\n---\n");

    File::ensureDirectoryExists(package_path('.claude/skills/stale-skill'));
    File::put(package_path('.claude/skills/stale-skill/SKILL.md'), 'stale');

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->assertSuccessful();

    expect(File::exists(package_path('.claude/skills/keep-me/SKILL.md')))->toBeTrue();
    expect(file_exists(package_path('.claude/skills/stale-skill')))->toBeFalse();
    expect(is_link(package_path('.claude/skills/stale-skill')))->toBeFalse();
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
    expect(substr_count($content, '<package-boost-guidelines>'))->toBe(1);
});

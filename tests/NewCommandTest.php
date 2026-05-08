<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Console\Internal\SkillFrontmatter;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    File::deleteDirectory(package_path('.ai/skills/test-new-skill'));
    File::delete(package_path('.ai/guidelines/test-new-guideline.md'));
});

afterEach(function (): void {
    File::deleteDirectory(package_path('.ai/skills/test-new-skill'));
    File::delete(package_path('.ai/guidelines/test-new-guideline.md'));
});

it('scaffolds a SKILL.md with passing frontmatter', function (): void {
    $exit = Artisan::call('package-boost:new', [
        'kind' => 'skill',
        'name' => 'test-new-skill',
        '--description' => 'A scaffolded skill.',
    ]);

    expect($exit)->toBe(0)
        ->and(File::exists(package_path('.ai/skills/test-new-skill/SKILL.md')))->toBeTrue();

    $issues = SkillFrontmatter::lint([
        'test-new-skill' => package_path('.ai/skills/test-new-skill'),
    ]);

    expect($issues)->toBe([]);
});

it('scaffolds a guideline markdown file', function (): void {
    $exit = Artisan::call('package-boost:new', [
        'kind' => 'guideline',
        'name' => 'test-new-guideline',
    ]);

    expect($exit)->toBe(0)
        ->and(File::exists(package_path('.ai/guidelines/test-new-guideline.md')))->toBeTrue();

    expect((string) file_get_contents(package_path('.ai/guidelines/test-new-guideline.md')))
        ->toContain('# Test New Guideline');
});

it('refuses to overwrite an existing target without --force', function (): void {
    Artisan::call('package-boost:new', ['kind' => 'skill', 'name' => 'test-new-skill']);

    $exit = Artisan::call('package-boost:new', ['kind' => 'skill', 'name' => 'test-new-skill']);

    expect($exit)->toBe(1);
});

it('overwrites with --force', function (): void {
    Artisan::call('package-boost:new', ['kind' => 'skill', 'name' => 'test-new-skill', '--description' => 'first']);

    $exit = Artisan::call('package-boost:new', [
        'kind' => 'skill',
        'name' => 'test-new-skill',
        '--description' => 'second',
        '--force' => true,
    ]);

    expect($exit)->toBe(0)
        ->and((string) file_get_contents(package_path('.ai/skills/test-new-skill/SKILL.md')))
        ->toContain('description: second');
});

it('rejects invalid names', function (): void {
    $exit = Artisan::call('package-boost:new', ['kind' => 'skill', 'name' => 'BadName']);

    expect($exit)->toBe(1);
});

it('rejects unknown kinds', function (): void {
    $exit = Artisan::call('package-boost:new', ['kind' => 'gizmo', 'name' => 'whatever']);

    expect($exit)->toBe(1);
});

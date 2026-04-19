<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

beforeEach(fn () => wipeArtifacts());
afterEach(fn () => wipeArtifacts());

// Uses Artisan::call rather than Pest's $this->artisan(...) because
// PendingCommand's expectsOutputToContain line-matching doesn't see
// components->warn output reliably when it wraps.
it('warns about deprecation and then delegates to package-boost:sync', function (): void {
    expect(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeFalse();

    $exit = Artisan::call('boost:update', ['--skills' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('boost:update is deprecated')
        ->and($output)->toContain('package-boost:sync')
        ->and(is_link(package_path('.claude/skills/package-development')))->toBeTrue()
        ->and(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeTrue();
});

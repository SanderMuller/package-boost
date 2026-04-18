<?php declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use SanderMuller\PackageBoost\PackageBoostServiceProvider;

use function Orchestra\Testbench\workbench_path;

it('merges package excludes into boost guidelines exclude config', function (): void {
    config()->set('boost.guidelines.exclude', ['existing/key']);
    config()->set('package-boost.excluded_boost_guidelines', ['livewire/core', 'filament/core']);

    app()->register(PackageBoostServiceProvider::class, force: true);

    $excludes = (array) config('boost.guidelines.exclude');

    expect($excludes)->toContain('existing/key')
        ->and($excludes)->toContain('livewire/core')
        ->and($excludes)->toContain('filament/core');
});

it('deduplicates excludes when boost config already lists the same key', function (): void {
    config()->set('boost.guidelines.exclude', ['livewire/core']);
    config()->set('package-boost.excluded_boost_guidelines', ['livewire/core', 'volt/core']);

    app()->register(PackageBoostServiceProvider::class, force: true);

    /** @var array<int, string> $excludes */
    $excludes = (array) config('boost.guidelines.exclude');
    $counts = array_count_values($excludes);

    expect($counts['livewire/core'])->toBe(1)
        ->and($excludes)->toContain('volt/core');
});

it('ships sensible package-dev defaults in package-boost config', function (): void {
    $defaults = (array) config('package-boost.excluded_boost_guidelines');

    expect($defaults)->toContain('foundation')
        ->and($defaults)->toContain('livewire/core')
        ->and($defaults)->toContain('filament/core')
        ->and($defaults)->toContain('inertia-laravel/core')
        ->and($defaults)->toContain('herd')
        ->and($defaults)->toContain('sail');
});

it('leaves boost exclude config untouched when package list is empty', function (): void {
    config()->set('boost.guidelines.exclude', ['original']);
    config()->set('package-boost.excluded_boost_guidelines', []);

    app()->register(PackageBoostServiceProvider::class, force: true);

    expect(config('boost.guidelines.exclude'))->toBe(['original']);
});

it('does not touch boost config when laravel/boost is not installed', function (): void {
    config()->set('boost.guidelines.exclude', ['untouched']);
    config()->set('package-boost.excluded_boost_guidelines', ['livewire/core']);

    $provider = new class (app()) extends PackageBoostServiceProvider {
        protected function boostIsInstalled(): bool
        {
            return false;
        }
    };

    app()->register($provider, force: true);

    expect(config('boost.guidelines.exclude'))->toBe(['untouched']);
});

it('publishes config to the workbench config directory', function (): void {
    $paths = ServiceProvider::pathsToPublish(PackageBoostServiceProvider::class, PackageBoostServiceProvider::PUBLISH_TAG);

    expect($paths)->not->toBeEmpty();
    expect(array_values($paths)[0])->toBe(workbench_path('config/package-boost.php'));
});

it('merges workbench/config/package-boost.php into package config', function (): void {
    $workbenchDir = workbench_path('config');
    $workbenchFile = workbench_path('config/package-boost.php');

    File::ensureDirectoryExists($workbenchDir);
    file_put_contents(
        $workbenchFile,
        "<?php return ['excluded_boost_guidelines' => ['custom/entry']];\n"
    );

    try {
        config()->set('package-boost.excluded_boost_guidelines', []);
        app()->register(PackageBoostServiceProvider::class, force: true);

        /** @var array<int, string> $excludes */
        $excludes = (array) config('package-boost.excluded_boost_guidelines');

        expect($excludes)->toContain('custom/entry');
    } finally {
        @unlink($workbenchFile);
    }
});

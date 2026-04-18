<?php declare(strict_types=1);

use SanderMuller\PackageBoost\PackageBoostServiceProvider;

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

    expect($defaults)->toContain('livewire/core')
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

<?php declare(strict_types=1);

arch('internal console helpers are final')
    ->expect('SanderMuller\PackageBoost\Console\Internal')
    ->toBeFinal();

it('marks every Console\\Internal class as @internal', function (): void {
    $files = glob(__DIR__ . '/../src/Console/Internal/*.php');

    expect($files)->not->toBeEmpty();

    $missing = [];

    foreach ((array) $files as $file) {
        if (! str_contains((string) file_get_contents((string) $file), '@internal')) {
            $missing[] = basename((string) $file);
        }
    }

    expect($missing)->toBe([]);
});

<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\workbench_path;

beforeEach(function (): void {
    wipeWorkbenchConfig();
});

afterEach(function (): void {
    wipeWorkbenchConfig();
});

function wipeWorkbenchConfig(): void
{
    File::delete(workbench_path('config/package-boost.php'));
}

function workbenchConfig(): string
{
    return (string) File::get(workbench_path('config/package-boost.php'));
}

function shippedConfigPath(): string
{
    return package_path('config/package-boost.php');
}

it('writes agents = null on --all and copies the shipped template when missing', function (): void {
    expect(File::exists(workbench_path('config/package-boost.php')))->toBeFalse();

    $exit = Artisan::call('package-boost:install', ['--all' => true]);

    expect($exit)->toBe(0)
        ->and(File::exists(workbench_path('config/package-boost.php')))->toBeTrue()
        ->and(workbenchConfig())->toContain("'agents' => null,")
        // Comment header from the shipped template must survive the copy.
        ->and(workbenchConfig())->toContain('Selected Agents');
});

it('writes the explicit list passed via --agents=', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code,cursor']);

    expect($exit)->toBe(0)
        ->and(workbenchConfig())->toContain("'agents' => ['claude_code', 'cursor'],");
});

it('rejects unknown agent names from --agents=', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code,bogus_agent']);

    expect($exit)->toBe(1);

    $output = Artisan::output();
    expect($output)->toContain('Unknown agent name')
        ->and($output)->toContain('bogus_agent');
});

it('preserves user comments and other keys when re-running --agents=', function (): void {
    File::ensureDirectoryExists(workbench_path('config'));
    File::put(workbench_path('config/package-boost.php'), <<<'PHP'
<?php declare(strict_types=1);

// User custom comment that must survive.
return [

    'agents' => null,

    // User-added override.
    'discover_vendor_packages' => false,

];
PHP);

    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code']);

    expect($exit)->toBe(0);
    $contents = workbenchConfig();
    expect($contents)->toContain("'agents' => ['claude_code'],")
        ->and($contents)->toContain('User custom comment that must survive')
        ->and($contents)->toContain('User-added override')
        ->and($contents)->toContain("'discover_vendor_packages' => false,");
});

it('refuses to write when the agents key is on multiple lines', function (): void {
    File::ensureDirectoryExists(workbench_path('config'));
    File::put(workbench_path('config/package-boost.php'), <<<'PHP'
<?php declare(strict_types=1);

return [

    'agents' => [
        'claude_code',
        'cursor',
    ],

];
PHP);

    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code']);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain("Couldn't locate")
        ->and($output)->toContain("'agents'");

    // File should not have been corrupted.
    expect(workbenchConfig())->toContain("'cursor',");
});

it('refuses to write when the workbench file is missing the agents key entirely', function (): void {
    File::ensureDirectoryExists(workbench_path('config'));
    File::put(workbench_path('config/package-boost.php'), <<<'PHP'
<?php declare(strict_types=1);

return [
    'discover_vendor_packages' => true,
];
PHP);

    $exit = Artisan::call('package-boost:install', ['--all' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain("Couldn't locate");
});

it('updates an existing single-line agents = [...] cleanly', function (): void {
    File::ensureDirectoryExists(workbench_path('config'));
    File::put(workbench_path('config/package-boost.php'), <<<'PHP'
<?php declare(strict_types=1);

return [

    'agents' => ['claude_code'],

];
PHP);

    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code,cursor,gemini']);

    expect($exit)->toBe(0)
        ->and(workbenchConfig())->toContain("'agents' => ['claude_code', 'cursor', 'gemini'],");
});

it('rejects --agents= when the value parses to an empty list', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => ',,,']);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('parsed to an empty list')
        ->and(File::exists(workbench_path('config/package-boost.php')))->toBeFalse();
});

it('parses --agents= with whitespace and trailing commas', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => ' claude_code , cursor , ']);

    expect($exit)->toBe(0)
        ->and(workbenchConfig())->toContain("'agents' => ['claude_code', 'cursor'],");
});

it('warns when legacy .github/copilot-instructions.md is present at install time', function (): void {
    File::ensureDirectoryExists(package_path('.github'));
    File::put(
        package_path('.github/copilot-instructions.md'),
        "<package-boost-guidelines>\nstale\n</package-boost-guidelines>\n",
    );

    try {
        $exit = Artisan::call('package-boost:install', ['--all' => true]);
        $output = Artisan::output();

        expect($exit)->toBe(0)
            ->and($output)->toContain('Legacy .github/copilot-instructions.md detected')
            ->and($output)->toContain('--prune');
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('does not warn at install time when the legacy file is absent', function (): void {
    File::delete(package_path('.github/copilot-instructions.md'));

    $exit = Artisan::call('package-boost:install', ['--all' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('Legacy .github/copilot-instructions.md detected');
});

it('dedupes and reorders to registry order on --agents=', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => 'cursor,claude_code,cursor']);

    expect($exit)->toBe(0)
        // Registry order is claude_code first, cursor second; duplicates collapsed.
        ->and(workbenchConfig())->toContain("'agents' => ['claude_code', 'cursor'],");
});

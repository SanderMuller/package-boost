<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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

/**
 * Load the workbench config file as PHP and return the value under
 * the `agents` key. Catches a class of regressions string assertions
 * miss — like the 0.10.0 bug where the regex replacement wrote a
 * literal `${indent}` prefix to the line, producing invalid PHP that
 * still passed every `toContain('agents...')` test.
 *
 * Spawns a subprocess (rather than `require`-ing in-process) because
 * PHP's include-file cache returns the first-loaded compiled version
 * even after the file changes on disk, defeating the point of the
 * test. A clean child-process `require` always sees the latest bytes.
 */
function workbenchAgents(): mixed
{
    $path = workbench_path('config/package-boost.php');
    $code = sprintf(
        '$c = require %s; '
        . 'echo serialize(is_array($c) ? (array_key_exists(\'agents\', $c) ? $c[\'agents\'] : \'__missing__\') : \'__not-an-array__\');',
        var_export($path, true),
    );

    $process = new Process([PHP_BINARY, '-r', $code]);
    $process->run();

    if (! $process->isSuccessful()) {
        return '__exec-failed__: ' . $process->getErrorOutput();
    }

    return unserialize($process->getOutput());
}

it('writes agents = null on --all and copies the shipped template when missing', function (): void {
    expect(File::exists(workbench_path('config/package-boost.php')))->toBeFalse();

    $exit = Artisan::call('package-boost:install', ['--all' => true]);

    expect($exit)->toBe(0)
        ->and(File::exists(workbench_path('config/package-boost.php')))->toBeTrue()
        ->and(workbenchConfig())->toMatch("/^    'agents' => null,$/m")
        // Comment header from the shipped template must survive the copy.
        ->and(workbenchConfig())->toContain('Selected Agents')
        ->and(workbenchAgents())->toBeNull();
});

it('writes the explicit list passed via --agents=', function (): void {
    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code,cursor']);

    expect($exit)->toBe(0)
        ->and(workbenchConfig())->toMatch("/^    'agents' => \\['claude_code', 'cursor'\\],$/m")
        ->and(workbenchAgents())->toBe(['claude_code', 'cursor']);
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
    expect($contents)->toMatch("/^    'agents' => \\['claude_code'\\],$/m")
        ->and($contents)->toContain('User custom comment that must survive')
        ->and($contents)->toContain('User-added override')
        ->and($contents)->toContain("'discover_vendor_packages' => false,")
        ->and(workbenchAgents())->toBe(['claude_code']);
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
        ->and(workbenchConfig())->toMatch("/^    'agents' => \\['claude_code', 'cursor', 'gemini'\\],$/m")
        ->and(workbenchAgents())->toBe(['claude_code', 'cursor', 'gemini']);
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
        ->and(workbenchConfig())->toMatch("/^    'agents' => \\['claude_code', 'cursor'\\],$/m")
        ->and(workbenchAgents())->toBe(['claude_code', 'cursor']);
});

it('preserves the indent of the existing agents line (regression: ${indent} backref leak)', function (): void {
    // 0.10.0 regression — replacement used `${indent}` (named-group
    // backref), which PHP preg_replace doesn't expand for replacements.
    // The literal string `${indent}` was written to disk, producing
    // invalid PHP. Tab indent here exercises a non-default whitespace
    // shape so we'd notice if the indent capture stops working too.
    File::ensureDirectoryExists(workbench_path('config'));
    File::put(workbench_path('config/package-boost.php'), "<?php declare(strict_types=1);\n\nreturn [\n\n\t'agents' => null,\n\n];\n");

    $exit = Artisan::call('package-boost:install', ['--agents' => 'claude_code,copilot']);

    expect($exit)->toBe(0)
        ->and(workbenchConfig())->not->toContain('${indent}')
        ->and(workbenchConfig())->toMatch("/^\\t'agents' => \\['claude_code', 'copilot'\\],$/m")
        ->and(workbenchAgents())->toBe(['claude_code', 'copilot']);
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

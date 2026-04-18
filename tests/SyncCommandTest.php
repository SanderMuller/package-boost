<?php declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

/** Shipped skills that `package-boost:sync` always installs. Keep in sync with `resources/boost/skills/`. */
const SHIPPED_SKILLS = [
    'cross-version-laravel-support',
    'package-development',
];

beforeEach(function (): void {
    wipeArtifacts();
});

afterEach(function (): void {
    wipeArtifacts();
});

function wipeArtifacts(): void
{
    File::deleteDirectory(package_path('.ai'));
    File::deleteDirectory(package_path('.claude/skills'));
    File::deleteDirectory(package_path('.github/skills'));
    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('.github/copilot-instructions.md'));
    File::delete(package_path('.mcp.json'));
}

it('syncs user and shipped skills to agent directories', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/test-skill'));
    File::put(package_path('.ai/skills/test-skill/SKILL.md'), "---\nname: test-skill\ndescription: A test skill.\n---\n\n# Test Skill\n");

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('Skills:')
        ->expectsOutputToContain('+ .claude/skills/test-skill')
        ->expectsOutputToContain('+ .claude/skills/package-development')
        ->assertSuccessful();

    expect(is_link(package_path('.claude/skills/test-skill')))->toBeTrue();
    expect(is_link(package_path('.claude/skills/package-development')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/test-skill/SKILL.md')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeTrue();
});

it('ships the package-development skill even without a user .ai/skills directory', function (): void {
    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('+ .claude/skills/package-development')
        ->assertSuccessful();

    expect(is_link(package_path('.claude/skills/package-development')))->toBeTrue();
    expect(File::exists(package_path('.claude/skills/package-development/SKILL.md')))->toBeTrue();
});

it('ships every skill listed in SHIPPED_SKILLS after a bare sync', function (): void {
    $this->artisan('package-boost:sync', ['--skills' => true])->assertSuccessful();

    foreach (SHIPPED_SKILLS as $skill) {
        expect(is_link(package_path('.claude/skills/' . $skill)))
            ->toBeTrue(".claude/skills/{$skill} should be symlinked")
            ->and(File::exists(package_path('.claude/skills/' . $skill . '/SKILL.md')))
            ->toBeTrue(".claude/skills/{$skill}/SKILL.md should be readable");
    }
});

it('syncs shipped foundation and user guidelines into agent files', function (): void {
    File::ensureDirectoryExists(package_path('.ai/guidelines'));
    File::put(package_path('.ai/guidelines/test.md'), "## Test Guideline\n\nDo the thing.\n");

    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('Guidelines:')
        ->expectsOutputToContain('+ CLAUDE.md')
        ->assertSuccessful();

    $claude = File::get(package_path('CLAUDE.md'));
    expect($claude)->toContain('<package-boost-guidelines>')
        ->and($claude)->toContain('# Package Boost Guidelines')
        ->and($claude)->toContain('Do the thing.');

    $foundationPos = strpos($claude, '# Package Boost Guidelines');
    $dividerPos = strpos($claude, "\n\n---\n\n");
    $userPos = strpos($claude, 'Do the thing.');
    expect($foundationPos !== false && $dividerPos !== false && $userPos !== false
        && $foundationPos < $dividerPos && $dividerPos < $userPos)->toBeTrue();

    expect(File::exists(package_path('AGENTS.md')))->toBeTrue();
    expect(File::exists(package_path('.github/copilot-instructions.md')))->toBeTrue();
});

it('ships foundation guideline even without a user .ai/guidelines directory', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('+ CLAUDE.md')
        ->assertSuccessful();

    $claude = File::get(package_path('CLAUDE.md'));
    expect($claude)->toContain('# Package Boost Guidelines')
        ->and($claude)->toContain('Foundational Context')
        ->and($claude)->toContain('configured test runner')
        ->and($claude)->toContain('Commands that require `laravel/boost`')
        ->and($claude)->not->toContain("\n\n---\n\n");
});

it('removes stale skills from target directories', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/keep-me'));
    File::put(package_path('.ai/skills/keep-me/SKILL.md'), "---\nname: keep-me\ndescription: Keep.\n---\n");

    File::ensureDirectoryExists(package_path('.claude/skills/stale-skill'));
    File::put(package_path('.claude/skills/stale-skill/SKILL.md'), 'stale');

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('- .claude/skills/stale-skill')
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

    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('~ CLAUDE.md')
        ->assertSuccessful();

    $content = File::get(package_path('CLAUDE.md'));
    expect($content)->toContain('Version 2');
    expect($content)->not->toContain('Version 1');
    expect(substr_count($content, '<package-boost-guidelines>'))->toBe(1);
});

it('reports unchanged on a clean re-sync', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--guidelines' => true])
        ->expectsOutputToContain('total: 3 unchanged')
        ->assertSuccessful();
});

it('exits non-zero with --check when sources diverge', function (): void {
    $this->artisan('package-boost:sync', ['--check' => true, '--guidelines' => true])
        ->expectsOutputToContain('+ CLAUDE.md')
        ->expectsOutputToContain('Generated files are out of sync')
        ->assertExitCode(1);

    expect(File::exists(package_path('CLAUDE.md')))->toBeFalse();
});

it('exits zero with --check when output is up to date', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--check' => true, '--guidelines' => true])
        ->expectsOutputToContain('total: 3 unchanged')
        ->assertExitCode(0);
});

it('--check does not write skill targets when drift detected', function (): void {
    $this->artisan('package-boost:sync', ['--check' => true, '--skills' => true])
        ->expectsOutputToContain('+ .claude/skills/package-development')
        ->assertExitCode(1);

    expect(file_exists(package_path('.claude/skills/package-development')))->toBeFalse();
});

/**
 * @return array<mixed>
 */
function readMcpConfig(): array
{
    $decoded = json_decode((string) File::get(package_path('.mcp.json')), true);

    return is_array($decoded) ? $decoded : [];
}

it('syncs MCP config and preserves unrelated user keys', function (): void {
    File::put(package_path('.mcp.json'), (string) json_encode([
        'mcpServers' => [
            'custom-server' => ['command' => '/usr/bin/thing'],
        ],
        'unrelatedRoot' => ['keep' => 'me'],
    ], JSON_PRETTY_PRINT));

    $this->artisan('package-boost:sync', ['--mcp' => true])
        ->expectsOutputToContain('MCP:')
        ->expectsOutputToContain('~ .mcp.json')
        ->assertSuccessful();

    $config = readMcpConfig();
    $servers = is_array($config['mcpServers'] ?? null) ? $config['mcpServers'] : [];
    $boost = is_array($servers['laravel-boost'] ?? null) ? $servers['laravel-boost'] : [];

    expect($config)->toHaveKey('unrelatedRoot')
        ->and($servers)->toHaveKey('custom-server')
        ->and($servers)->toHaveKey('laravel-boost')
        ->and($boost['command'] ?? null)->toBe('vendor/bin/testbench');
});

it('recovers when .mcp.json contains a non-array scalar root', function (): void {
    File::put(package_path('.mcp.json'), '"this is not an object"');

    $this->artisan('package-boost:sync', ['--mcp' => true])
        ->expectsOutputToContain('~ .mcp.json')
        ->assertSuccessful();

    $config = readMcpConfig();
    $servers = is_array($config['mcpServers'] ?? null) ? $config['mcpServers'] : [];
    $boost = is_array($servers['laravel-boost'] ?? null) ? $servers['laravel-boost'] : [];

    expect($boost['command'] ?? null)->toBe('vendor/bin/testbench');
});

it('recovers when .mcp.json mcpServers key is not an array', function (): void {
    File::put(package_path('.mcp.json'), (string) json_encode([
        'mcpServers' => 'typo',
    ]));

    $this->artisan('package-boost:sync', ['--mcp' => true])
        ->expectsOutputToContain('~ .mcp.json')
        ->assertSuccessful();

    $config = readMcpConfig();
    $servers = is_array($config['mcpServers'] ?? null) ? $config['mcpServers'] : [];
    $boost = is_array($servers['laravel-boost'] ?? null) ? $servers['laravel-boost'] : [];

    expect($boost['command'] ?? null)->toBe('vendor/bin/testbench');
});

it('reports MCP unchanged on a clean re-sync with --show-unchanged', function (): void {
    $this->artisan('package-boost:sync', ['--mcp' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--mcp' => true, '--show-unchanged' => true])
        ->expectsOutputToContain('= .mcp.json')
        ->expectsOutputToContain('total: 1 unchanged')
        ->assertSuccessful();
});

it('always emits a MCP summary line, matching skills and guidelines output', function (): void {
    $this->artisan('package-boost:sync', ['--mcp' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--mcp' => true])
        ->expectsOutputToContain('MCP:')
        ->expectsOutputToContain('total: 1 unchanged')
        ->assertSuccessful();
});

it('annotates skill updates with the symlink target', function (): void {
    File::ensureDirectoryExists(package_path('.claude/skills'));
    symlink('/tmp/bogus-target', package_path('.claude/skills/package-development'));

    $this->artisan('package-boost:sync', ['--skills' => true])
        ->expectsOutputToContain('~ .claude/skills/package-development (symlink →')
        ->assertSuccessful();
});

it('shows unchanged entries per line with --show-unchanged', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--guidelines' => true, '--show-unchanged' => true])
        ->expectsOutputToContain('= CLAUDE.md')
        ->expectsOutputToContain('= AGENTS.md')
        ->assertSuccessful();
});

it('--check fails when only one category is drifting', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--check' => true])
        ->expectsOutputToContain('+ .claude/skills/package-development')
        ->expectsOutputToContain('Generated files are out of sync')
        ->assertExitCode(1);
});

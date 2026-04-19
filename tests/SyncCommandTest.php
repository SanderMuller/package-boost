<?php declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

/** Keep in sync with `resources/boost/skills/` — adding a directory there requires adding the name here. */
const SHIPPED_SKILLS = [
    'ci-matrix-troubleshooting',
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
    // Delete test-created fixtures only; `.ai/guidelines/*.md` belonging to
    // the repo is committed content and must survive across test runs.
    foreach (['test-skill', 'keep-me', 'stale-skill'] as $name) {
        File::deleteDirectory(package_path('.ai/skills/' . $name));
    }

    File::delete(package_path('.ai/guidelines/test.md'));

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

it('ships skill after a bare sync', function (string $skill): void {
    $this->artisan('package-boost:sync', ['--skills' => true])->assertSuccessful();

    expect(is_link(package_path('.claude/skills/' . $skill)))->toBeTrue()
        ->and(File::exists(package_path('.claude/skills/' . $skill . '/SKILL.md')))->toBeTrue();
})->with(SHIPPED_SKILLS);

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
    // This repo dogfoods .ai/guidelines/ (release-automation, verification-
    // before-completion). Temporarily hide it so we can exercise the
    // no-user-guidelines path — the one downstream consumers will hit
    // before authoring their own content.
    $guidelines = package_path('.ai/guidelines');
    $stash = package_path('.ai/guidelines.stash');
    $hasDogfood = is_dir($guidelines);

    if ($hasDogfood) {
        rename($guidelines, $stash);
    }

    try {
        $this->artisan('package-boost:sync', ['--guidelines' => true])
            ->expectsOutputToContain('+ CLAUDE.md')
            ->assertSuccessful();

        $claude = File::get(package_path('CLAUDE.md'));
        expect($claude)->toContain('# Package Boost Guidelines')
            ->and($claude)->toContain('Foundational Context')
            ->and($claude)->toContain('configured test runner')
            ->and($claude)->toContain('Commands that require `laravel/boost`')
            ->and($claude)->not->toContain("\n\n---\n\n");
    } finally {
        if ($hasDogfood) {
            rename($stash, $guidelines);
        }
    }
});

it('ships the Authoring guidelines section in the package-development skill', function (): void {
    $this->artisan('package-boost:sync', ['--skills' => true])->assertSuccessful();

    $skill = File::get(package_path('.claude/skills/package-development/SKILL.md'));

    expect($skill)->toContain('## Authoring guidelines')
        ->and($skill)->toContain('Guideline file shape')
        ->and($skill)->toContain('Skill file shape');
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

/**
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: array<mixed>}
 */
function captureJsonSync(array $options): array
{
    $exitCode = Artisan::call('package-boost:sync', $options);
    $decoded = json_decode(trim(Artisan::output()), true);

    return [$exitCode, is_array($decoded) ? $decoded : []];
}

it('emits JSON when --format=json is passed', function (): void {
    [$exit, $json] = captureJsonSync(['--format' => 'json', '--check' => true]);

    expect($exit)->toBe(1)
        ->and($json)->toHaveKey('schema', 1)
        ->and($json)->toHaveKey('check', true)
        ->and($json)->toHaveKey('drift', true)
        ->and($json)->toHaveKeys(['skills', 'guidelines', 'mcp']);

    $skills = is_array($json['skills'] ?? null) ? $json['skills'] : [];
    expect($skills)->toHaveKeys(['new', 'updated', 'removed', 'unchanged']);
    expect($skills['unchanged'])->toBeInt();
});

it('JSON mode treats unchanged as an array with --show-unchanged', function (): void {
    Artisan::call('package-boost:sync');

    [$exit, $json] = captureJsonSync(['--format' => 'json', '--show-unchanged' => true, '--check' => true]);

    $guidelines = is_array($json['guidelines'] ?? null) ? $json['guidelines'] : [];
    $unchanged = is_array($guidelines['unchanged'] ?? null) ? $guidelines['unchanged'] : [];
    $firstEntry = is_array($unchanged[0] ?? null) ? $unchanged[0] : [];

    expect($exit)->toBe(0)
        ->and($unchanged)->not->toBeEmpty()
        ->and($firstEntry['target'] ?? null)->toBeString();
});

it('JSON mode reports drift=false on a clean repo', function (): void {
    Artisan::call('package-boost:sync');

    [$exit, $json] = captureJsonSync(['--format' => 'json', '--check' => true]);

    expect($exit)->toBe(0)
        ->and($json['drift'] ?? null)->toBeFalse();
});

it('JSON mode renders the MCP action object, not a collection', function (): void {
    [$exit, $json] = captureJsonSync(['--format' => 'json', '--mcp' => true, '--check' => true]);

    $mcp = is_array($json['mcp'] ?? null) ? $json['mcp'] : [];

    expect($exit)->toBe(1)
        ->and($mcp)->toHaveKey('action')
        ->and($mcp['action'])->toBeIn(['new', 'updated', 'unchanged'])
        ->and($mcp)->toHaveKey('target', '.mcp.json');
});

it('rejects an unknown --format value', function (): void {
    $this->artisan('package-boost:sync', ['--format' => 'yaml'])
        ->expectsOutputToContain("Invalid --format value 'yaml'; expected 'text' or 'json'.")
        ->assertExitCode(1);
});

it('--check detects content drift on a copied (non-symlink) skill dest', function (): void {
    // User ships a skill; simulate the symlink-fallback copy path by
    // pre-seeding .claude/skills/keep-me as a plain directory with stale
    // SKILL.md content.
    File::ensureDirectoryExists(package_path('.ai/skills/keep-me'));
    File::put(package_path('.ai/skills/keep-me/SKILL.md'), "---\nname: keep-me\ndescription: Fresh.\n---\n");

    File::ensureDirectoryExists(package_path('.claude/skills/keep-me'));
    File::put(package_path('.claude/skills/keep-me/SKILL.md'), "---\nname: keep-me\ndescription: Stale.\n---\n");

    $this->artisan('package-boost:sync', ['--check' => true, '--skills' => true])
        ->expectsOutputToContain('~ .claude/skills/keep-me (content: SKILL.md differs)')
        ->assertExitCode(1);
});

it('--check fails when only one category is drifting', function (): void {
    $this->artisan('package-boost:sync', ['--guidelines' => true])->assertSuccessful();

    $this->artisan('package-boost:sync', ['--check' => true])
        ->expectsOutputToContain('+ .claude/skills/package-development')
        ->expectsOutputToContain('Generated files are out of sync')
        ->assertExitCode(1);
});

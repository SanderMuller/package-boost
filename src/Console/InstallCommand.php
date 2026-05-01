<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\BoostImporter;
use SanderMuller\PackageBoost\Agents\Registry;

use function Laravel\Prompts\multiselect;

/**
 * Interactive selector that captures which AI agents `package-boost:sync`
 * should target, persisting the choice to the host's workbench config.
 *
 * Resolution of the default-checked set in the prompt:
 *   1. `--all` / `--agents=` flags if present → non-interactive write, no prompt.
 *   2. Currently configured `package-boost.agents` (re-running as editor).
 *   3. Boost import from `boost.json` if Boost is installed and `--no-import` is not set.
 *   4. Detection-marker scan against the project root.
 *   5. All 9 agents (first-run zero-signal default).
 */
class InstallCommand extends Command
{
    protected $signature = 'package-boost:install
        {--all : Sync all supported agents (non-interactive)}
        {--agents= : Comma-separated agent names (non-interactive)}
        {--no-import : Skip importing the selection from laravel/boost\'s boost.json}';

    protected $description = 'Configure which AI agents package-boost syncs to';

    public function handle(): int
    {
        $root = $this->resolvePackageRoot();

        $this->warnAboutLegacyCopilotInstructions($root);

        if ($this->option('all') === true) {
            return $this->persist(null, $root);
        }

        $explicit = $this->option('agents');

        if (is_string($explicit) && $explicit !== '') {
            $parsed = $this->parseAgentList($explicit);

            if ($parsed === []) {
                $this->components->error(
                    "Invalid --agents value '{$explicit}': parsed to an empty list. "
                    . 'Pass `--all` for the zero-config default, or a comma-separated agent name list.'
                );

                return self::FAILURE;
            }

            return $this->persist($parsed, $root);
        }

        $defaults = $this->resolveDefaults($root);

        /** @var array<int, string> $selected */
        $selected = multiselect(
            label: 'Which AI agents should package-boost sync skills and guidelines for?',
            options: $this->agentOptions(),
            default: $defaults,
            required: false,
            hint: 'Space to toggle, Enter to confirm. Leave none selected for "all" (recommended).',
        );

        // Empty multiselect result is interpreted as "all" — matches the
        // null-config zero-config default and avoids an awkward "you
        // selected nothing, now what" branch.
        return $this->persist($selected === [] ? null : array_values($selected), $root);
    }

    /**
     * @return array<string, string>  agent name => label
     */
    private function agentOptions(): array
    {
        $options = [];

        foreach (Registry::all() as $agent) {
            $options[$agent->name] = $agent->label;
        }

        return $options;
    }

    /**
     * @return array<int, string>  default-checked agent names
     */
    private function resolveDefaults(string $root): array
    {
        $configured = config('package-boost.agents');

        if (is_array($configured)) {
            $names = array_values(array_filter($configured, is_string(...)));

            if ($names !== []) {
                return $names;
            }
        }

        $boostImport = $this->boostImport($root);

        if ($boostImport !== null && $boostImport !== []) {
            return $boostImport;
        }

        $detected = $this->detectInstalledAgents($root);

        return $detected !== [] ? $detected : array_map(
            static fn (Agent $agent): string => $agent->name,
            Registry::all(),
        );
    }

    /**
     * @return ?array<int, string>
     */
    private function boostImport(string $root): ?array
    {
        if ($this->option('no-import') === true) {
            return null;
        }

        return BoostImporter::fromBoost($root);
    }

    /**
     * @return array<int, string>  agent names whose detection markers exist in the project root
     */
    private function detectInstalledAgents(string $root): array
    {
        $detected = [];

        foreach (Registry::all() as $agent) {
            foreach ($agent->detectionMarkers as $marker) {
                if (file_exists($root . DIRECTORY_SEPARATOR . $marker)) {
                    $detected[] = $agent->name;
                    break;
                }
            }
        }

        return $detected;
    }

    /**
     * @param  ?array<int, string>  $names  null = all (zero-config); array = explicit subset
     */
    private function persist(?array $names, string $root): int
    {
        if ($names !== null) {
            $known = array_map(static fn (Agent $a): string => $a->name, Registry::all());
            $unknown = array_values(array_diff($names, $known));

            if ($unknown !== []) {
                $this->components->error(
                    'Unknown agent name(s): ' . implode(', ', $unknown)
                    . '. Supported: ' . implode(', ', $known) . '.'
                );

                return self::FAILURE;
            }

            // Drop duplicates while preserving registry order.
            $set = array_flip($names);
            $names = array_values(array_filter(
                array_map(static fn (Agent $a): string => $a->name, Registry::all()),
                static fn (string $name): bool => isset($set[$name]),
            ));
        }

        $path = $this->resolveWorkbenchConfigPath();

        if ($path === null) {
            $this->components->error(
                'Cannot resolve workbench config path. `package-boost:install` requires Orchestra Testbench.'
            );

            return self::FAILURE;
        }

        if (! $this->writeAgentsKey($path, $names)) {
            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Wrote agent selection (%s) to %s.',
            $names === null ? 'all' : implode(', ', $names),
            $this->relativeTo($path, $root),
        ));
        $this->components->info('Run `vendor/bin/testbench package-boost:sync` to apply.');

        return self::SUCCESS;
    }

    private function resolveWorkbenchConfigPath(): ?string
    {
        if (! function_exists('Orchestra\Testbench\workbench_path')) {
            return null;
        }

        return \Orchestra\Testbench\workbench_path('config/package-boost.php');
    }

    private function resolvePackageRoot(): string
    {
        if (function_exists('Orchestra\Testbench\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return (string) getcwd();
    }

    /**
     * Parse `--agents=claude_code,cursor` into `['claude_code', 'cursor']`,
     * tolerating whitespace and discarding empty segments.
     *
     * @return array<int, string>
     */
    private function parseAgentList(string $raw): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $raw),
        ), static fn (string $s): bool => $s !== ''));
    }

    /**
     * Regex-replace the `'agents' => ...,` line on a single line. On a
     * missing file, copy the shipped config template first. On a regex
     * mismatch (multi-line array, hand-customised formatting), refuse
     * with a diagnostic — caller decides recovery (manual edit).
     *
     * @param  ?array<int, string>  $names
     */
    private function writeAgentsKey(string $path, ?array $names): bool
    {
        if (! is_file($path)) {
            $shipped = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'package-boost.php';

            if (! is_file($shipped)) {
                $this->components->error("Shipped config template missing at {$shipped}. Reinstall the package.");

                return false;
            }

            File::ensureDirectoryExists(dirname($path));
            File::copy($shipped, $path);
        }

        $content = (string) file_get_contents($path);
        $rendered = $this->renderAgentsValue($names);

        // Match `'agents' => null,` or `'agents' => ['a', 'b'],` strictly
        // on one line. Multi-line arrays are the documented failure mode.
        // `$1` is the captured indent — PHP `preg_replace` only honours
        // positional backreferences in replacements, not named ones, so a
        // `${indent}` literal would leak verbatim into the file.
        $pattern = "/^([ \t]*)'agents'\s*=>\s*(?:null|\[[^\]\n]*\])\s*,\s*$/m";
        $replacement = '$1\'agents\' => ' . $rendered . ',';
        $count = 0;
        $updated = preg_replace($pattern, $replacement, $content, 1, $count);

        if ($updated === null || $count === 0) {
            $this->components->error(
                "Couldn't locate a single-line `'agents' => ...,` entry in {$path}. "
                . 'Edit the file manually to set the desired value, or remove the entry and rerun '
                . '`package-boost:install`.'
            );

            return false;
        }

        File::put($path, $updated);

        return true;
    }

    /**
     * @param  ?array<int, string>  $names
     */
    private function renderAgentsValue(?array $names): string
    {
        if ($names === null) {
            return 'null';
        }

        if ($names === []) {
            return '[]';
        }

        return '[' . implode(', ', array_map(
            static fn (string $name): string => "'" . addslashes($name) . "'",
            $names,
        )) . ']';
    }

    /**
     * Mirror `SyncCommand`'s legacy-Copilot warning so users running
     * `package-boost:install` (the recommended first step) see the
     * stale file alongside the prompt rather than waiting until their
     * next sync.
     */
    private function warnAboutLegacyCopilotInstructions(string $root): void
    {
        if (LegacyCopilotInstructions::read($root) === null) {
            return;
        }

        $this->components->warn(
            'Legacy ' . LegacyCopilotInstructions::PATH . ' detected. '
            . 'package-boost no longer writes this file. '
            . 'Run `vendor/bin/testbench package-boost:sync --prune` to remove it.'
        );
    }

    private function relativeTo(string $absolute, string $root): string
    {
        if (str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            return substr($absolute, strlen($root) + 1);
        }

        return $absolute;
    }
}

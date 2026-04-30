<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Laravel\Boost\BoostServiceProvider;
use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}
        {--check : Report drift without writing; exits non-zero if sources diverge from generated files}
        {--show-unchanged : Print unchanged entries per line instead of only counting them in the summary}
        {--format=text : Output format — "text" (default, glyph-per-line) or "json" (structured, for CI parsing)}
        {--prune : Remove the legacy .github/copilot-instructions.md file when it contains only our tag block; refuses if user content is present}';

    protected $description = 'Sync .ai/ skills and guidelines to agent directories';

    /** @var ?array<int, Agent> Memoised selection — built once per `handle()` invocation. */
    private ?array $selectedAgents = null;

    /**
     * Resolve the user-selected agent set from `config('package-boost.agents')`.
     * `null` config = all 9. Unknown names are dropped silently here; the
     * `warnAboutUnknownAgents()` companion surfaces typos to the user
     * without breaking sync.
     *
     * @return array<int, Agent>
     */
    private function selectedAgents(): array
    {
        if ($this->selectedAgents !== null) {
            return $this->selectedAgents;
        }

        $configured = config('package-boost.agents');
        $names = is_array($configured)
            ? array_values(array_filter($configured, is_string(...)))
            : null;

        return $this->selectedAgents = Registry::forSelection($names);
    }

    /**
     * Skill destination paths derived from the selected agents. `array_unique`
     * collapses the `.agents/skills` shared dir (Codex, Gemini, OpenCode,
     * Amp) to a single write target.
     *
     * @return array<int, string>
     */
    private function skillTargets(): array
    {
        return Registry::skillTargets($this->selectedAgents());
    }

    /**
     * Guideline destination paths derived from the selected agents.
     *
     * @return array<int, string>
     */
    private function guidelineTargets(): array
    {
        return Registry::guidelineTargets($this->selectedAgents());
    }

    private function warnAboutDeselectedAgentArtifacts(string $root): void
    {
        $orphans = DeselectedAgentArtifacts::locate($root, $this->selectedAgents());

        if ($orphans === []) {
            return;
        }

        $this->components->warn(
            "Generated artifacts exist for agents NOT in `package-boost.agents`:\n  - "
            . implode("\n  - ", $orphans)
            . "\nThese were synced under a previous selection. Re-include the agent or delete the paths manually."
        );
    }

    public function handle(): int
    {
        // Reset the per-invocation memo. Console commands are singleton-bound
        // in the container, so a long-lived test runner (or any process that
        // calls Artisan::call twice) would otherwise see stale selection.
        $this->selectedAgents = null;

        $formatOption = $this->option('format');
        $format = is_string($formatOption) ? $formatOption : 'text';

        if (! in_array($format, ['text', 'json'], true)) {
            $this->components->error("Invalid --format value '{$format}'; expected 'text' or 'json'.");

            return self::FAILURE;
        }

        $root = $this->resolvePackageRoot();
        $check = $this->option('check') === true;
        $showUnchanged = $this->option('show-unchanged') === true;

        if ($format === 'text') {
            $this->warnAboutUnknownAgents();
        }

        $plans = $this->runCategories(
            $this->selectedCategories(),
            $root,
            $check,
            $format === 'text' ? $showUnchanged : null,
        );

        $this->renderPostCategoryOutput($format, $plans, $check, $showUnchanged, $root);

        return $this->finalExit($plans, $format, $check);
    }

    /**
     * @param  array<string, SyncPlan>  $plans
     */
    private function renderPostCategoryOutput(string $format, array $plans, bool $check, bool $showUnchanged, string $root): void
    {
        if ($format === 'json') {
            $this->output->writeln(rtrim(SyncFormatter::renderJson($plans, $check, $showUnchanged)));

            return;
        }

        $this->warnAboutDeselectedAgentArtifacts($root);

        if ($this->option('prune') === true && ! $check) {
            $this->pruneLegacyCopilotInstructions($root);

            return;
        }

        $this->warnAboutLegacyCopilotInstructions($root);
    }

    /**
     * @param  array<string, SyncPlan>  $plans
     */
    private function finalExit(array $plans, string $format, bool $check): int
    {
        $drift = collect($plans)->contains(static fn (SyncPlan $plan): bool => $plan->hasDrift());

        if (! $check || ! $drift) {
            return self::SUCCESS;
        }

        if ($format === 'text') {
            $this->components->error('Generated files are out of sync. Run `package-boost:sync` without --check.');
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, string>  selected category names; all three when no subcommand flag is set
     */
    private function selectedCategories(): array
    {
        $flagged = array_keys(array_filter([
            'skills' => $this->option('skills') === true,
            'guidelines' => $this->option('guidelines') === true,
            'mcp' => $this->option('mcp') === true,
        ]));

        return $flagged === [] ? ['skills', 'guidelines', 'mcp'] : $flagged;
    }

    /**
     * @param  array<int, string>  $categories
     * @param  ?bool  $showUnchanged  null suppresses text rendering (JSON mode)
     * @return array<string, SyncPlan>
     */
    private function runCategories(array $categories, string $root, bool $check, ?bool $showUnchanged): array
    {
        $formatter = $this->makeFormatter();
        $plans = [];

        foreach ($categories as $category) {
            $plans[$category] = match ($category) {
                'skills' => $this->categoriseSkills($root, $check),
                'guidelines' => $this->categoriseGuidelines($root, $check),
                'mcp' => $this->categoriseMcp($root, $check),
                default => SyncPlan::skipped('unknown-category'),
            };

            if ($showUnchanged !== null) {
                $formatter->renderText($category, $plans[$category], $showUnchanged);
            }
        }

        return $plans;
    }

    private function categoriseSkills(string $root, bool $check): SyncPlan
    {
        $sources = SyncSources::skills($root);
        $plan = $this->planSkills($root, $sources);

        if (! $check) {
            $this->applySkills($plan, $root, $sources);
        }

        return $plan;
    }

    private function categoriseGuidelines(string $root, bool $check): SyncPlan
    {
        [$plan, $block] = $this->planGuidelines($root);

        if (! $check) {
            $this->applyGuidelines($plan, $root, $block);
        }

        return $plan;
    }

    private function categoriseMcp(string $root, bool $check): SyncPlan
    {
        if (! in_array('claude_code', array_map(static fn (Agent $a): string => $a->name, $this->selectedAgents()), true)) {
            return SyncPlan::skipped('claude-not-selected');
        }

        [$plan, $desired] = $this->planMcp($root);

        if (! $check) {
            $this->applyMcp($plan, $root, $desired);
        }

        return $plan;
    }

    private function makeFormatter(): SyncFormatter
    {
        return new SyncFormatter(
            writeLine: fn (string $line) => $this->line($line),
            warn: fn (string $line) => $this->components->warn($line),
        );
    }

    private function resolvePackageRoot(): string
    {
        if (function_exists('Orchestra\Testbench\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return (string) getcwd();
    }

    /**
     * Surface configured-but-unknown agent names so a typo in
     * `config/package-boost.php` is visible without breaking sync.
     * `Registry::forSelection()` already drops unknowns from the
     * effective list — this is the human-facing nudge.
     */
    private function warnAboutUnknownAgents(): void
    {
        $configured = config('package-boost.agents');

        if (! is_array($configured)) {
            return;
        }

        $names = array_values(array_filter($configured, is_string(...)));
        $unknown = Registry::unknownNames($names);

        if ($unknown === []) {
            return;
        }

        $this->components->warn(
            "Unknown agent name(s) in config('package-boost.agents'): "
            . implode(', ', $unknown)
            . '. Supported: ' . implode(', ', Registry::names()) . '.'
        );
    }

    /**
     * `--prune` companion to `warnAboutLegacyCopilotInstructions()`.
     * Removes the legacy file when its content is only the
     * `<package-boost-guidelines>` tag block (with surrounding
     * whitespace). If user content is present outside the block, we
     * refuse and re-emit the warning — never destructive.
     */
    private function pruneLegacyCopilotInstructions(string $root): void
    {
        $contents = LegacyCopilotInstructions::read($root);

        if ($contents === null) {
            return;
        }

        $expectedBlock = "<package-boost-guidelines>\n" . SyncSources::guidelines($root) . "\n</package-boost-guidelines>";

        if (! LegacyCopilotInstructions::isPrunable($contents, $expectedBlock)) {
            $this->components->warn(
                'Refusing to prune ' . LegacyCopilotInstructions::PATH . ': '
                . 'either user content lives outside the package-boost-guidelines block, '
                . 'or the block has been edited / is out of sync with `.ai/`. '
                . 'Run `package-boost:sync` first to refresh, or delete the file manually.'
            );

            return;
        }

        LegacyCopilotInstructions::delete($root);

        $this->components->info('Removed legacy ' . LegacyCopilotInstructions::PATH . '.');
    }

    /**
     * Detect a leftover `.github/copilot-instructions.md` from prior
     * package-boost versions and nudge the user to delete it. We only
     * warn when our wrapping tag is present — a hand-authored Copilot
     * file should not trigger noise. Auto-removal is gated behind the
     * `--prune` flag.
     */
    private function warnAboutLegacyCopilotInstructions(string $root): void
    {
        if (LegacyCopilotInstructions::read($root) === null) {
            return;
        }

        $this->components->warn(
            'Legacy ' . LegacyCopilotInstructions::PATH . ' detected. '
            . 'package-boost no longer writes this file (Copilot now reads AGENTS.md). '
            . 'Delete it manually to silence this warning.'
        );
    }

    /**
     * @param  array<string, string>  $skills  name => source path
     */
    private function planSkills(string $root, array $skills): SyncPlan
    {
        if ($skills === []) {
            return SyncPlan::skipped('no-sources');
        }

        $new = [];
        $updated = [];
        $unchanged = [];
        $removed = [];

        foreach ($this->skillTargets() as $target) {
            $targetDir = $root . DIRECTORY_SEPARATOR . $target;

            foreach ($skills as $name => $source) {
                $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
                [$action, $hint] = SyncReporter::planSkillAction($source, $dest);
                $entry = new SyncAction("{$target}/{$name}", hint: $hint !== '' ? $hint : null);

                match ($action) {
                    'new' => $new[] = $entry,
                    'updated' => $updated[] = $entry,
                    'unchanged' => $unchanged[] = $entry,
                };
            }

            foreach ($this->existingSkillNames($targetDir) as $existing) {
                if (isset($skills[$existing])) {
                    continue;
                }

                $removed[] = new SyncAction("{$target}/{$existing}");
            }
        }

        return new SyncPlan(new: $new, updated: $updated, unchanged: $unchanged, removed: $removed);
    }

    /**
     * @return array{0: SyncPlan, 1: string}  plan + composed block for write phase
     */
    private function planGuidelines(string $root): array
    {
        $guidelines = SyncSources::guidelines($root);

        if ($guidelines === '') {
            return [SyncPlan::skipped('no-sources'), ''];
        }

        $block = "<package-boost-guidelines>\n{$guidelines}\n</package-boost-guidelines>";

        $new = [];
        $updated = [];
        $unchanged = [];

        foreach ($this->guidelineTargets() as $target) {
            $filePath = $root . DIRECTORY_SEPARATOR . $target;
            [$action, $hint, $lineDelta] = SyncReporter::planGuidelineAction($filePath, $block);
            $entry = new SyncAction($target, hint: $hint !== '' ? $hint : null, lineDelta: $lineDelta);

            match ($action) {
                'new' => $new[] = $entry,
                'updated' => $updated[] = $entry,
                'unchanged' => $unchanged[] = $entry,
            };
        }

        return [new SyncPlan(new: $new, updated: $updated, unchanged: $unchanged), $block];
    }

    /**
     * @return array{0: SyncPlan, 1: array<mixed>}  plan + desired config for write phase
     */
    private function planMcp(string $root): array
    {
        if (! class_exists(BoostServiceProvider::class, false)) {
            return [SyncPlan::skipped('laravel-boost-not-installed'), []];
        }

        $mcpPath = $root . DIRECTORY_SEPARATOR . '.mcp.json';
        [$action, $desired] = SyncReporter::planMcpAction($mcpPath, SyncSources::mcpConfig($mcpPath));

        $entry = new SyncAction('.mcp.json');
        $plan = match ($action) {
            'new' => new SyncPlan(new: [$entry]),
            'updated' => new SyncPlan(updated: [$entry]),
            'unchanged' => new SyncPlan(unchanged: [$entry]),
        };

        return [$plan, $desired];
    }

    /**
     * @param  array<string, string>  $skills  name => source path
     */
    private function applySkills(SyncPlan $plan, string $root, array $skills): void
    {
        foreach ([...$plan->new, ...$plan->updated] as $action) {
            $name = basename($action->target);
            $targetDir = $root . DIRECTORY_SEPARATOR . dirname($action->target);
            SyncWriter::linkOrCopy($skills[$name], $targetDir . DIRECTORY_SEPARATOR . $name);
        }

        foreach ($plan->removed as $action) {
            SyncWriter::removeSkill($root . DIRECTORY_SEPARATOR . $action->target);
        }
    }

    private function applyGuidelines(SyncPlan $plan, string $root, string $block): void
    {
        foreach ([...$plan->new, ...$plan->updated] as $action) {
            SyncWriter::writeGuidelineBlock($root . DIRECTORY_SEPARATOR . $action->target, $block);
        }
    }

    /**
     * @param  array<mixed>  $desired
     */
    private function applyMcp(SyncPlan $plan, string $root, array $desired): void
    {
        if (! $plan->hasDrift()) {
            return;
        }

        file_put_contents(
            $root . DIRECTORY_SEPARATOR . '.mcp.json',
            json_encode($desired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * @return array<int, string>
     */
    private function existingSkillNames(string $targetDir): array
    {
        if (! is_dir($targetDir)) {
            return [];
        }

        $entries = glob($targetDir . DIRECTORY_SEPARATOR . '*');

        if ($entries === false) {
            return [];
        }

        return array_map(basename(...), $entries);
    }
}

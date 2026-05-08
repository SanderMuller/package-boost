<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;
use SanderMuller\PackageBoost\Console\Internal\BoostDetector;
use SanderMuller\PackageBoost\Console\Internal\DeselectedAgentArtifacts;
use SanderMuller\PackageBoost\Console\Internal\LegacyCopilotInstructions;
use SanderMuller\PackageBoost\Console\Internal\PackageRoot;
use SanderMuller\PackageBoost\Console\Internal\SkillFrontmatter;
use SanderMuller\PackageBoost\Console\Internal\SyncFormatter;
use SanderMuller\PackageBoost\Console\Internal\SyncPlan;
use SanderMuller\PackageBoost\Console\Internal\SyncPlanner;
use SanderMuller\PackageBoost\Console\Internal\SyncSources;
use SanderMuller\PackageBoost\Console\Internal\SyncWriter;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}
        {--check : Report drift without writing; exits non-zero if sources diverge from generated files}
        {--show-unchanged : Print unchanged entries per line instead of only counting them in the summary}
        {--format=text : Output format — "text" (default, glyph-per-line) or "json" (structured, for CI parsing)}
        {--prune : Remove the legacy .github/copilot-instructions.md file when it contains only our tag block; refuses if user content is present}
        {--prune-orphans : Remove generated artefacts (skill dirs, guideline blocks, .mcp.json entries) for agents that fell out of `package-boost.agents`. Strips the package-boost block from guideline files; deletes the file only if no user content remains.}';

    protected $description = 'Sync .ai/ skills and guidelines to agent directories';

    /** @var ?array<int, Agent> Memoised selection — built once per `handle()` invocation. */
    private ?array $selectedAgents = null;

    /** @var array<int, array{name: string, path: string, problems: array<int, string>}> */
    private array $frontmatterIssues = [];

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
        // Reset per-invocation state. Console commands are singleton-bound
        // in the container, so a long-lived test runner (or any process that
        // calls Artisan::call twice) would otherwise see stale selection
        // and stale frontmatter findings.
        $this->selectedAgents = null;
        $this->frontmatterIssues = [];

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
            $this->output->writeln(rtrim(SyncFormatter::renderJson($plans, $check, $showUnchanged, $this->frontmatterIssues)));

            return;
        }

        $this->warnAboutFrontmatterIssues();

        if ($this->option('prune-orphans') === true && ! $check) {
            $this->pruneDeselectedAgentArtifacts($root);
        } else {
            $this->warnAboutDeselectedAgentArtifacts($root);
        }

        if ($this->option('prune') === true && ! $check) {
            $this->pruneLegacyCopilotInstructions($root);

            return;
        }

        $this->warnAboutLegacyCopilotInstructions($root);
    }

    private function pruneDeselectedAgentArtifacts(string $root): void
    {
        $removed = DeselectedAgentArtifacts::prune($root, $this->selectedAgents());

        if ($removed === []) {
            return;
        }

        $this->components->info(
            "Pruned orphan artefacts:\n  - " . implode("\n  - ", $removed)
        );
    }

    /**
     * Surface SKILL.md frontmatter problems collected during sync. We never
     * fail a normal sync on a lint issue — a malformed shipped/vendor skill
     * shouldn't block the host's update — but `--check` does fail-fast so
     * CI catches a host-authored skill before it ships.
     */
    private function warnAboutFrontmatterIssues(): void
    {
        if ($this->frontmatterIssues === []) {
            return;
        }

        $lines = [];

        foreach ($this->frontmatterIssues as $issue) {
            $lines[] = $issue['name'] . ': ' . implode('; ', $issue['problems']);
        }

        $this->components->warn(
            "SKILL.md frontmatter issues detected:\n  - " . implode("\n  - ", $lines)
            . "\nFix the source files (or remove the offending skill) and re-run sync."
        );
    }

    /**
     * @param  array<string, SyncPlan>  $plans
     */
    private function finalExit(array $plans, string $format, bool $check): int
    {
        $drift = collect($plans)->contains(static fn (SyncPlan $plan): bool => $plan->hasDrift());
        $hostFrontmatterDrift = $check && $this->hasHostFrontmatterIssues();

        if (! $check || (! $drift && ! $hostFrontmatterDrift)) {
            return self::SUCCESS;
        }

        if ($format === 'text') {
            $this->components->error(
                $drift
                    ? 'Generated files are out of sync. Run `package-boost:sync` without --check.'
                    : 'SKILL.md frontmatter issues detected in host `.ai/skills/` content (see warning above).'
            );
        }

        return self::FAILURE;
    }

    /**
     * Only fail `--check` for issues under the host's `.ai/skills/` —
     * shipped / vendor skills that drift would otherwise block the host's
     * CI on something they don't control.
     */
    private function hasHostFrontmatterIssues(): bool
    {
        $hostPrefix = $this->resolvePackageRoot() . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR;

        foreach ($this->frontmatterIssues as $issue) {
            if (str_starts_with($issue['path'], $hostPrefix)) {
                return true;
            }
        }

        return false;
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
        $this->frontmatterIssues = SkillFrontmatter::lint($sources);
        $plan = SyncPlanner::planSkills($root, $sources, $this->skillTargets());

        if (! $check) {
            $this->applySkills($plan, $root, $sources);
        }

        return $plan;
    }

    private function categoriseGuidelines(string $root, bool $check): SyncPlan
    {
        [$plan, $block] = SyncPlanner::planGuidelines($root, $this->guidelineTargets());

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

        [$plan, $desired] = SyncPlanner::planMcp($root, $this->boostInstalled());

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
        return PackageRoot::resolve();
    }

    private function boostInstalled(): bool
    {
        return (new BoostDetector($this->getLaravel()))->installed();
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
}

<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Laravel\Boost\BoostServiceProvider;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}
        {--check : Report drift without writing; exits non-zero if sources diverge from generated files}
        {--show-unchanged : Print unchanged entries per line instead of only counting them in the summary}
        {--format=text : Output format — "text" (default, glyph-per-line) or "json" (structured, for CI parsing)}';

    protected $description = 'Sync .ai/ skills and guidelines to agent directories';

    /** @var array<int, string> */
    private const SKILL_TARGETS = [
        '.claude/skills',
        '.github/skills',
    ];

    /** @var array<int, string> */
    private const GUIDELINE_TARGETS = [
        'CLAUDE.md',
        'AGENTS.md',
        '.github/copilot-instructions.md',
    ];

    public function handle(): int
    {
        $formatOption = $this->option('format');
        $format = is_string($formatOption) ? $formatOption : 'text';

        if (! in_array($format, ['text', 'json'], true)) {
            $this->components->error("Invalid --format value '{$format}'; expected 'text' or 'json'.");

            return self::FAILURE;
        }

        $root = $this->resolvePackageRoot();
        $check = $this->option('check') === true;
        $showUnchanged = $this->option('show-unchanged') === true;
        $categories = $this->selectedCategories();

        $plans = $this->runCategories($categories, $root, $check, $format === 'text' ? $showUnchanged : null);

        if ($format === 'json') {
            $this->output->writeln(rtrim(SyncFormatter::renderJson($plans, $check, $showUnchanged)));
        }

        $drift = collect($plans)->contains(static fn (SyncPlan $plan): bool => $plan->hasDrift());

        if ($format === 'text' && $check && $drift) {
            $this->components->error('Generated files are out of sync. Run `package-boost:sync` without --check.');

            return self::FAILURE;
        }

        return $check && $drift ? self::FAILURE : self::SUCCESS;
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

        foreach (self::SKILL_TARGETS as $target) {
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

        foreach (self::GUIDELINE_TARGETS as $target) {
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

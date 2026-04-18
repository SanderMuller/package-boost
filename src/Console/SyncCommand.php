<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Boost\BoostServiceProvider;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}
        {--check : Report drift without writing; exits non-zero if sources diverge from generated files}
        {--show-unchanged : Print unchanged entries per line instead of only counting them in the summary}';

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
        $root = $this->resolvePackageRoot();
        $check = $this->option('check') === true;
        $showUnchanged = $this->option('show-unchanged') === true;

        $syncSkills = $this->option('skills') === true;
        $syncGuidelines = $this->option('guidelines') === true;
        $syncMcp = $this->option('mcp') === true;
        $syncAll = ! $syncSkills && ! $syncGuidelines && ! $syncMcp;

        $formatter = $this->makeFormatter();
        $drift = false;

        if (($syncAll || $syncSkills) && $this->runCategory('skills', $root, $check, $showUnchanged, $formatter)) {
            $drift = true;
        }

        if (($syncAll || $syncGuidelines) && $this->runCategory('guidelines', $root, $check, $showUnchanged, $formatter)) {
            $drift = true;
        }

        if (($syncAll || $syncMcp) && $this->runCategory('mcp', $root, $check, $showUnchanged, $formatter)) {
            $drift = true;
        }

        if ($check && $drift) {
            $this->components->error('Generated files are out of sync. Run `package-boost:sync` without --check.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function runCategory(string $category, string $root, bool $check, bool $showUnchanged, SyncFormatter $formatter): bool
    {
        $plan = match ($category) {
            'skills' => $this->runSkillsCategory($root, $check, $showUnchanged, $formatter),
            'guidelines' => $this->runGuidelinesCategory($root, $check, $showUnchanged, $formatter),
            'mcp' => $this->runMcpCategory($root, $check, $showUnchanged, $formatter),
            default => SyncPlan::skipped('unknown-category'),
        };

        return $plan->hasDrift();
    }

    private function runSkillsCategory(string $root, bool $check, bool $showUnchanged, SyncFormatter $formatter): SyncPlan
    {
        $sources = SyncSources::skills($root);
        $plan = $this->planSkills($root, $sources);
        $formatter->renderText('skills', $plan, $showUnchanged);

        if (! $check) {
            $this->applySkills($plan, $root, $sources);
        }

        return $plan;
    }

    private function runGuidelinesCategory(string $root, bool $check, bool $showUnchanged, SyncFormatter $formatter): SyncPlan
    {
        [$plan, $block] = $this->planGuidelines($root);
        $formatter->renderText('guidelines', $plan, $showUnchanged);

        if (! $check) {
            $this->applyGuidelines($plan, $root, $block);
        }

        return $plan;
    }

    private function runMcpCategory(string $root, bool $check, bool $showUnchanged, SyncFormatter $formatter): SyncPlan
    {
        [$plan, $desired] = $this->planMcp($root);
        $formatter->renderText('mcp', $plan, $showUnchanged);

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

            $expectedNames = array_keys($skills);
            foreach ($this->existingSkillNames($targetDir) as $existing) {
                if (in_array($existing, $expectedNames, true)) {
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
            [$action, $hint] = SyncReporter::planGuidelineAction($filePath, $block);
            $entry = new SyncAction($target, hint: $hint !== '' ? $hint : null);

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
            $this->linkOrCopy($skills[$name], $targetDir . DIRECTORY_SEPARATOR . $name);
        }

        foreach ($plan->removed as $action) {
            $this->removeSkill($root . DIRECTORY_SEPARATOR . $action->target);
        }
    }

    private function applyGuidelines(SyncPlan $plan, string $root, string $block): void
    {
        foreach ([...$plan->new, ...$plan->updated] as $action) {
            $this->writeGuidelineBlock($root . DIRECTORY_SEPARATOR . $action->target, $block);
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

    private function removeSkill(string $entry): void
    {
        is_link($entry) ? File::delete($entry) : File::deleteDirectory($entry);
    }

    private function linkOrCopy(string $source, string $dest): void
    {
        if (file_exists($dest) || is_link($dest)) {
            is_link($dest) ? File::delete($dest) : File::deleteDirectory($dest);
        }

        File::ensureDirectoryExists(dirname($dest));

        $resolvedSource = realpath($source);
        $relativePath = SyncReporter::relativePath($resolvedSource !== false ? $resolvedSource : $source, dirname($dest));

        if (@symlink($relativePath, $dest)) {
            return;
        }

        File::ensureDirectoryExists($dest);
        File::copyDirectory($source, $dest);
    }

    private function writeGuidelineBlock(string $filePath, string $block): void
    {
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        if (file_exists($filePath)) {
            $content = (string) file_get_contents($filePath);

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, SyncReporter::escapeReplacement($block), $content, 1);
            } else {
                $content = rtrim($content) . "\n\n" . $block . "\n";
            }
        } else {
            File::ensureDirectoryExists(dirname($filePath));
            $content = $block . "\n";
        }

        file_put_contents($filePath, $content);
    }
}

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

        $drift = false;

        if (($syncAll || $syncSkills) && $this->runSkills($root, $check, $showUnchanged)) {
            $drift = true;
        }

        if (($syncAll || $syncGuidelines) && $this->runGuidelines($root, $check, $showUnchanged)) {
            $drift = true;
        }

        if (($syncAll || $syncMcp) && $this->runMcp($root, $check, $showUnchanged)) {
            $drift = true;
        }

        if ($check && $drift) {
            $this->components->error('Generated files are out of sync. Run `package-boost:sync` without --check.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolvePackageRoot(): string
    {
        if (function_exists('Orchestra\Testbench\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return (string) getcwd();
    }

    private function runSkills(string $root, bool $check, bool $showUnchanged): bool
    {
        $skills = SyncSources::skills($root);

        if ($skills === []) {
            $this->components->warn('No skills found in .ai/skills/ or shipped package-boost skills.');

            return false;
        }

        $this->line('Skills:');

        $counts = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0];

        foreach (self::SKILL_TARGETS as $target) {
            $this->syncSkillsForTarget($root, $target, $skills, $check, $showUnchanged, $counts);
        }

        $this->line('  ' . SyncReporter::summaryLine($counts));

        return $counts['new'] + $counts['updated'] + $counts['removed'] > 0;
    }

    /**
     * @param  array<string, string>  $skills
     * @param  array<string, int>     $counts
     */
    private function syncSkillsForTarget(string $root, string $target, array $skills, bool $check, bool $showUnchanged, array &$counts): void
    {
        $targetDir = $root . DIRECTORY_SEPARATOR . $target;

        foreach ($skills as $name => $source) {
            $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
            [$action, $hint] = SyncReporter::planSkillAction($source, $dest);
            $counts[$action]++;

            if ($action === 'unchanged' && ! $showUnchanged) {
                continue;
            }

            $this->line('  ' . SyncReporter::glyph($action) . " {$target}/{$name}{$hint}");

            if ($action !== 'unchanged' && ! $check) {
                $this->linkOrCopy($source, $dest);
            }
        }

        $expectedNames = array_keys($skills);

        foreach ($this->existingSkillNames($targetDir) as $existing) {
            if (in_array($existing, $expectedNames, true)) {
                continue;
            }

            $counts['removed']++;
            $this->line('  ' . SyncReporter::glyph('removed') . " {$target}/{$existing}");

            if (! $check) {
                $this->removeSkill($targetDir . DIRECTORY_SEPARATOR . $existing);
            }
        }
    }

    private function runGuidelines(string $root, bool $check, bool $showUnchanged): bool
    {
        $guidelines = SyncSources::guidelines($root);

        if ($guidelines === '') {
            $this->components->warn('No guideline files found in .ai/guidelines/ or shipped package-boost guidelines.');

            return false;
        }

        $block = "<package-boost-guidelines>\n{$guidelines}\n</package-boost-guidelines>";
        $drift = false;
        $counts = ['new' => 0, 'updated' => 0, 'unchanged' => 0];

        $this->line('Guidelines:');

        foreach (self::GUIDELINE_TARGETS as $target) {
            $filePath = $root . DIRECTORY_SEPARATOR . $target;
            [$action, $diff] = SyncReporter::planGuidelineAction($filePath, $block);
            $counts[$action]++;

            if ($action === 'unchanged') {
                if ($showUnchanged) {
                    $this->line('  ' . SyncReporter::glyph($action) . " {$target}");
                }

                continue;
            }

            $drift = true;
            $this->line('  ' . SyncReporter::glyph($action) . " {$target}{$diff}");

            if (! $check) {
                $this->writeGuidelineBlock($filePath, $block);
            }
        }

        $this->line('  ' . SyncReporter::summaryLine($counts));

        return $drift;
    }

    private function runMcp(string $root, bool $check, bool $showUnchanged): bool
    {
        if (! class_exists(BoostServiceProvider::class, false)) {
            $this->components->warn('Laravel Boost is not installed — skipping MCP config.');

            return false;
        }

        $mcpPath = $root . DIRECTORY_SEPARATOR . '.mcp.json';
        [$action, $desired] = SyncReporter::planMcpAction($mcpPath, SyncSources::mcpConfig($mcpPath));

        $this->line('MCP:');

        if ($action === 'unchanged') {
            if ($showUnchanged) {
                $this->line('  ' . SyncReporter::glyph($action) . ' .mcp.json');
            }

            return false;
        }

        $this->line('  ' . SyncReporter::glyph($action) . ' .mcp.json');

        if (! $check) {
            file_put_contents(
                $mcpPath,
                json_encode($desired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );
        }

        return true;
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

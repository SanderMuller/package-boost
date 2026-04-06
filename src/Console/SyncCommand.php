<?php

declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Orchestra\Testbench\package_path;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}';

    protected $description = 'Sync .ai/ skills and guidelines to agent directories';

    /** @var array<string, string> */
    private const SKILL_TARGETS = [
        '.claude/skills',
        '.github/skills',
    ];

    /** @var array<string, string> */
    private const GUIDELINE_TARGETS = [
        'CLAUDE.md',
        'AGENTS.md',
        '.github/copilot-instructions.md',
    ];

    public function handle(): int
    {
        $root = package_path();
        $syncAll = ! $this->option('skills') && ! $this->option('guidelines') && ! $this->option('mcp');

        if ($syncAll || $this->option('skills')) {
            $this->syncSkills($root);
        }

        if ($syncAll || $this->option('guidelines')) {
            $this->syncGuidelines($root);
        }

        if ($syncAll || $this->option('mcp')) {
            $this->syncMcp($root);
        }

        return self::SUCCESS;
    }

    private function syncSkills(string $root): void
    {
        $sourceDir = $root.DIRECTORY_SEPARATOR.'.ai'.DIRECTORY_SEPARATOR.'skills';

        if (! is_dir($sourceDir)) {
            $this->components->warn('No .ai/skills/ directory found.');

            return;
        }

        $skills = glob($sourceDir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);

        if ($skills === false || $skills === []) {
            $this->components->warn('No skills found in .ai/skills/.');

            return;
        }

        $count = 0;

        foreach (self::SKILL_TARGETS as $target) {
            $targetDir = $root.DIRECTORY_SEPARATOR.$target;

            foreach ($skills as $skillPath) {
                $skillName = basename($skillPath);
                $dest = $targetDir.DIRECTORY_SEPARATOR.$skillName;

                File::ensureDirectoryExists($dest);
                File::copyDirectory($skillPath, $dest);
                $count++;
            }
        }

        $skillCount = count($skills);
        $targetCount = count(self::SKILL_TARGETS);
        $this->components->info("Synced {$skillCount} skills to {$targetCount} agent directories.");
    }

    private function syncGuidelines(string $root): void
    {
        $sourceDir = $root.DIRECTORY_SEPARATOR.'.ai'.DIRECTORY_SEPARATOR.'guidelines';

        if (! is_dir($sourceDir)) {
            $this->components->warn('No .ai/guidelines/ directory found.');

            return;
        }

        $guidelines = $this->collectGuidelines($sourceDir);

        if ($guidelines === '') {
            $this->components->warn('No guideline files found in .ai/guidelines/.');

            return;
        }

        $block = "<package-boost-guidelines>\n{$guidelines}\n</package-boost-guidelines>";

        foreach (self::GUIDELINE_TARGETS as $target) {
            $filePath = $root.DIRECTORY_SEPARATOR.$target;
            $this->writeGuidelineBlock($filePath, $block);
        }

        $this->components->info('Synced guidelines to '.count(self::GUIDELINE_TARGETS).' agent files.');
    }

    private function collectGuidelines(string $dir): string
    {
        $finder = Finder::create()
            ->files()
            ->in($dir)
            ->name('*.md')
            ->sortByName();

        return collect($finder)
            ->map(fn (SplFileInfo $file): string => trim($file->getContents()))
            ->filter(fn (string $content): bool => $content !== '')
            ->implode("\n\n");
    }

    private function writeGuidelineBlock(string $filePath, string $block): void
    {
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $block, $content, 1);
            } else {
                $content = rtrim($content)."\n\n".$block."\n";
            }
        } else {
            File::ensureDirectoryExists(dirname($filePath));
            $content = $block."\n";
        }

        file_put_contents($filePath, $content);
    }

    private function syncMcp(string $root): void
    {
        $mcpPath = $root.DIRECTORY_SEPARATOR.'.mcp.json';

        $config = file_exists($mcpPath)
            ? json_decode(file_get_contents($mcpPath), true) ?? []
            : [];

        $hasBoost = class_exists(\Laravel\Boost\BoostServiceProvider::class);

        if ($hasBoost) {
            $config['mcpServers']['laravel-boost'] = [
                'command' => 'vendor/bin/testbench',
                'args' => ['boost:mcp'],
            ];
        }

        file_put_contents(
            $mcpPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->components->info('Synced MCP config to .mcp.json.');
    }
}

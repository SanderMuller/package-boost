<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Boost\BoostServiceProvider;
use Symfony\Component\Finder\Finder;

class SyncCommand extends Command
{
    protected $signature = 'package-boost:sync
        {--skills : Only sync skills}
        {--guidelines : Only sync guidelines}
        {--mcp : Only sync MCP config}';

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
        $syncSkills = $this->option('skills') === true;
        $syncGuidelines = $this->option('guidelines') === true;
        $syncMcp = $this->option('mcp') === true;
        $syncAll = ! $syncSkills && ! $syncGuidelines && ! $syncMcp;

        if ($syncAll || $syncSkills) {
            $this->syncSkills($root);
        }

        if ($syncAll || $syncGuidelines) {
            $this->syncGuidelines($root);
        }

        if ($syncAll || $syncMcp) {
            $this->syncMcp($root);
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

    private function syncSkills(string $root): void
    {
        $skills = $this->collectSkills($root);

        if ($skills === []) {
            $this->components->warn('No skills found in .ai/skills/ or shipped package-boost skills.');

            return;
        }

        $skillNames = array_values(array_map(basename(...), $skills));

        foreach (self::SKILL_TARGETS as $target) {
            $targetDir = $root . DIRECTORY_SEPARATOR . $target;

            $this->removeStaleSkills($targetDir, $skillNames);

            foreach ($skills as $skillName => $skillPath) {
                $dest = $targetDir . DIRECTORY_SEPARATOR . $skillName;

                $this->linkOrCopy($skillPath, $dest);
            }
        }

        $skillCount = count($skills);
        $targetCount = count(self::SKILL_TARGETS);
        $this->components->info("Synced {$skillCount} skills to {$targetCount} agent directories.");
    }

    /**
     * Collect skills from package-boost's shipped resources and the user's
     * `.ai/skills/`. User skills override shipped skills of the same name.
     *
     * @return array<string, string>
     */
    private function collectSkills(string $root): array
    {
        $sources = [
            __DIR__ . '/../../resources/boost/skills',
            $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'skills',
        ];

        $skills = [];

        foreach ($sources as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $entries = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                $skills[basename($entry)] = $entry;
            }
        }

        return $skills;
    }

    private function linkOrCopy(string $source, string $dest): void
    {
        if (file_exists($dest) || is_link($dest)) {
            is_link($dest) ? File::delete($dest) : File::deleteDirectory($dest);
        }

        File::ensureDirectoryExists(dirname($dest));

        $resolvedSource = realpath($source);
        $relativePath = $this->relativePath($resolvedSource !== false ? $resolvedSource : $source, dirname($dest));

        if (@symlink($relativePath, $dest)) {
            return;
        }

        File::ensureDirectoryExists($dest);
        File::copyDirectory($source, $dest);
    }

    private function relativePath(string $target, string $from): string
    {
        $target = str_replace('\\', '/', $target);
        $resolvedFrom = realpath($from);
        $from = str_replace('\\', '/', $resolvedFrom !== false ? $resolvedFrom : $from);

        $targetParts = explode('/', $target);
        $fromParts = explode('/', $from);

        $common = 0;

        while ($common < count($targetParts) && $common < count($fromParts) && $targetParts[$common] === $fromParts[$common]) {
            ++$common;
        }

        $ups = count($fromParts) - $common;

        return str_repeat('../', $ups) . implode('/', array_slice($targetParts, $common));
    }

    /**
     * @param  array<int, string>  $currentSkillNames
     */
    private function removeStaleSkills(string $targetDir, array $currentSkillNames): void
    {
        if (! is_dir($targetDir)) {
            return;
        }

        $existing = glob($targetDir . DIRECTORY_SEPARATOR . '*');

        if ($existing === false) {
            return;
        }

        foreach ($existing as $entry) {
            if (! in_array(basename($entry), $currentSkillNames, true)) {
                is_link($entry) ? File::delete($entry) : File::deleteDirectory($entry);
            }
        }
    }

    private function syncGuidelines(string $root): void
    {
        $guidelines = $this->collectGuidelines($root);

        if ($guidelines === '') {
            $this->components->warn('No guideline files found in .ai/guidelines/ or shipped package-boost guidelines.');

            return;
        }

        $block = "<package-boost-guidelines>\n{$guidelines}\n</package-boost-guidelines>";

        foreach (self::GUIDELINE_TARGETS as $target) {
            $filePath = $root . DIRECTORY_SEPARATOR . $target;
            $this->writeGuidelineBlock($filePath, $block);
        }

        $this->components->info('Synced guidelines to ' . count(self::GUIDELINE_TARGETS) . ' agent files.');
    }

    /**
     * Collect guideline markdown from package-boost's shipped resources first,
     * then the user's `.ai/guidelines/`. Shipped foundation appears ahead of
     * user-authored content.
     */
    private function collectGuidelines(string $root): string
    {
        $sources = [
            __DIR__ . '/../../resources/boost/guidelines',
            $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'guidelines',
        ];

        $parts = [];

        foreach ($sources as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($dir)
                ->name('*.md')
                ->sortByName();

            foreach ($finder as $file) {
                $content = trim($file->getContents());

                if ($content !== '') {
                    $parts[] = $content;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    private function writeGuidelineBlock(string $filePath, string $block): void
    {
        $pattern = '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s';

        if (file_exists($filePath)) {
            $content = (string) file_get_contents($filePath);

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $block, $content, 1);
            } else {
                $content = rtrim($content) . "\n\n" . $block . "\n";
            }
        } else {
            File::ensureDirectoryExists(dirname($filePath));
            $content = $block . "\n";
        }

        file_put_contents($filePath, $content);
    }

    private function syncMcp(string $root): void
    {
        if (! class_exists(BoostServiceProvider::class, false)) {
            $this->components->warn('Laravel Boost is not installed — skipping MCP config.');

            return;
        }

        $mcpPath = $root . DIRECTORY_SEPARATOR . '.mcp.json';

        /** @var array<string, array<string, mixed>> $config */
        $config = file_exists($mcpPath)
            ? json_decode((string) file_get_contents($mcpPath), true) ?? []
            : [];

        $config['mcpServers']['laravel-boost'] = [
            'command' => 'vendor/bin/testbench',
            'args' => ['boost:mcp'],
        ];

        file_put_contents(
            $mcpPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->components->info('Synced MCP config to .mcp.json.');
    }
}

<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * Renders {@see SyncPlan} instances to text output. JSON rendering lands in
 * 0.6.0 Phase 2 on the same formatter.
 */
final readonly class SyncFormatter
{
    public function __construct(
        private \Closure $writeLine,
        private \Closure $warn,
    ) {}

    public function renderText(string $category, SyncPlan $plan, bool $showUnchanged): void
    {
        if ($plan->skipped !== null) {
            ($this->warn)($this->skippedMessage($category, $plan->skipped));

            return;
        }

        $this->writeLine($this->categoryHeader($category));

        foreach ($plan->new as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('new') . ' ' . $action->target . ($action->hint ?? ''));
        }

        foreach ($plan->updated as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('updated') . ' ' . $action->target . ($action->hint ?? ''));
        }

        foreach ($plan->removed as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('removed') . ' ' . $action->target);
        }

        if ($showUnchanged) {
            foreach ($plan->unchanged as $action) {
                $this->writeLine('  ' . SyncReporter::glyph('unchanged') . ' ' . $action->target);
            }
        }

        $this->writeLine('  ' . SyncReporter::summaryLine($plan->counts()));
    }

    private function writeLine(string $line): void
    {
        ($this->writeLine)($line);
    }

    private function categoryHeader(string $category): string
    {
        return match ($category) {
            'skills' => 'Skills:',
            'guidelines' => 'Guidelines:',
            'mcp' => 'MCP:',
            default => ucfirst($category) . ':',
        };
    }

    private function skippedMessage(string $category, string $reason): string
    {
        return match (true) {
            $category === 'skills' && $reason === 'no-sources' => 'No skills found in .ai/skills/ or shipped package-boost skills.',
            $category === 'guidelines' && $reason === 'no-sources' => 'No guideline files found in .ai/guidelines/ or shipped package-boost guidelines.',
            $category === 'mcp' && $reason === 'laravel-boost-not-installed' => 'Laravel Boost is not installed — skipping MCP config.',
            default => ucfirst($category) . " skipped: {$reason}",
        };
    }
}

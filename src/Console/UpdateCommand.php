<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/** Deprecated alias for package-boost:sync; scheduled for removal in 0.11.0 per ROADMAP Sunset. */
final class UpdateCommand extends SyncCommand
{
    public function __construct()
    {
        parent::__construct();

        $this->setName('boost:update');
        $this->setDescription('[deprecated] Alias for package-boost:sync.');
        $this->setHidden(true);
    }

    public function handle(): int
    {
        $this->components->warn(
            'boost:update is deprecated and will be removed in a future release. Use package-boost:sync instead.',
        );

        return parent::handle();
    }
}

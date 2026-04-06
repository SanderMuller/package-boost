<?php

declare(strict_types=1);

namespace SanderMuller\PackageBoost;

use Illuminate\Support\ServiceProvider;
use SanderMuller\PackageBoost\Console\SyncCommand;

class PackageBoostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            SyncCommand::class,
        ]);
    }
}

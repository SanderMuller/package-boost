<?php declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use SanderMuller\PackageBoost\PackageBoostServiceProvider;

if (! class_exists('Laravel\\Boost\\BoostServiceProvider')) {
    require __DIR__ . '/Stubs/BoostServiceProvider.php';
}

uses(TestCase::class)->in(__DIR__);

uses()->beforeEach(function (): void {
    $this->app->register(PackageBoostServiceProvider::class);
})->in(__DIR__);

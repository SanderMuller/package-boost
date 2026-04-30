<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests\Agents;

use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Agents\BoostImporter;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp/boost-import'));
    File::ensureDirectoryExists(package_path('tests/tmp/boost-import'));
});

afterEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp/boost-import'));
});

function fixtureRoot(): string
{
    return package_path('tests/tmp/boost-import');
}

function writeBoostJson(mixed $contents): void
{
    File::put(
        fixtureRoot() . '/boost.json',
        is_string($contents) ? $contents : (string) json_encode($contents),
    );
}

it('returns null when boost.json is absent', function (): void {
    expect(BoostImporter::fromBoost(fixtureRoot()))->toBeNull();
});

it('returns null when boost.json is not valid JSON', function (): void {
    writeBoostJson('{this is not json');

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBeNull();
});

it('returns null when boost.json decodes to a non-array root', function (): void {
    writeBoostJson('"a bare string"');

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBeNull();
});

it('returns null when boost.json has no agents key', function (): void {
    writeBoostJson(['guidelines' => [], 'skills' => []]);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBeNull();
});

it('returns null when agents is not an array', function (): void {
    writeBoostJson(['agents' => 'claude_code']);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBeNull();
});

it('returns the agents array when valid', function (): void {
    writeBoostJson(['agents' => ['claude_code', 'cursor']]);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBe(['claude_code', 'cursor']);
});

it('filters out unknown agent names from the imported list', function (): void {
    writeBoostJson(['agents' => ['claude_code', 'some_third_party_agent', 'cursor']]);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBe(['claude_code', 'cursor']);
});

it('returns an empty array when all imported names are unknown', function (): void {
    writeBoostJson(['agents' => ['unknown_one', 'unknown_two']]);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBe([]);
});

it('drops non-string entries in the agents array', function (): void {
    writeBoostJson(['agents' => ['claude_code', 42, null, 'cursor']]);

    expect(BoostImporter::fromBoost(fixtureRoot()))->toBe(['claude_code', 'cursor']);
});

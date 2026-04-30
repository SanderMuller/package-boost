<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Agents;

/**
 * Reads the user's Boost-side agent selection from `boost.json` at the
 * project root so `package-boost:install` can pre-fill the prompt
 * without re-asking. The Boost file shape is verified at
 * `laravel/boost:src/Support/Config.php:11,153-155` (`main` @ `8ed9f84`).
 */
final class BoostImporter
{
    /**
     * @return ?array<int, string>  agent names known to package-boost's
     *                              registry, or null when boost.json is
     *                              absent / malformed / has no `agents`
     *                              key. Unknown names (e.g. a third-
     *                              party Boost agent we don't yet
     *                              support) are filtered out so the
     *                              install prompt never shows ghosts.
     */
    public static function fromBoost(string $root): ?array
    {
        $path = $root . DIRECTORY_SEPARATOR . 'boost.json';

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $agents = $decoded['agents'] ?? null;

        if (! is_array($agents)) {
            return null;
        }

        $names = array_values(array_filter($agents, is_string(...)));
        $known = array_map(static fn (Agent $agent): string => $agent->name, Registry::all());

        return array_values(array_intersect($names, $known));
    }
}

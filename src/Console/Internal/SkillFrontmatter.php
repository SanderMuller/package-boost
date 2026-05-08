<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

/**
 * @internal Lints the YAML-style frontmatter at the top of a SKILL.md.
 *
 * Required keys: `name`, `description`. The validator is intentionally
 * minimal — it does not parse arbitrary YAML, only the `key: value` shape
 * that downstream agents (Claude Code, Cursor, Copilot) expect at the top
 * of every shipped SKILL.md. Anything more elaborate is the host's
 * responsibility.
 */
final class SkillFrontmatter
{
    public const REQUIRED_KEYS = ['name', 'description'];

    /**
     * Validate every discovered SKILL.md across host + vendor + shipped.
     *
     * @param  array<string, string>  $skills  name => absolute source dir (as returned by SyncSources::skills)
     * @return array<int, array{name: string, path: string, problems: array<int, string>}>  empty when no issues
     */
    public static function lint(array $skills): array
    {
        $issues = [];

        foreach ($skills as $name => $dir) {
            $skillFile = $dir . DIRECTORY_SEPARATOR . 'SKILL.md';
            $problems = self::lintFile($name, $skillFile);

            if ($problems !== []) {
                $issues[] = [
                    'name' => $name,
                    'path' => $skillFile,
                    'problems' => $problems,
                ];
            }
        }

        return $issues;
    }

    /**
     * @return array<int, string>  human-readable problem strings; `[]` when valid
     */
    public static function lintFile(string $name, string $skillFile): array
    {
        if (! is_file($skillFile)) {
            return ['SKILL.md missing'];
        }

        $contents = (string) file_get_contents($skillFile);

        if ($contents === '') {
            return ['SKILL.md is empty'];
        }

        $frontmatter = self::extract($contents);

        if ($frontmatter === null) {
            return ['frontmatter block missing (expected YAML between `---` fences at the top of the file)'];
        }

        $parsed = self::parseScalars($frontmatter);
        $problems = [];

        foreach (self::REQUIRED_KEYS as $key) {
            if (! isset($parsed[$key]) || $parsed[$key] === '') {
                $problems[] = "missing required key: {$key}";
            }
        }

        if (isset($parsed['name']) && $parsed['name'] !== $name) {
            $problems[] = "name mismatch: frontmatter says '{$parsed['name']}', directory is '{$name}'";
        }

        return $problems;
    }

    private static function extract(string $contents): ?string
    {
        if (! str_starts_with(ltrim($contents), '---')) {
            return null;
        }

        $stripped = ltrim($contents);

        if (preg_match('/^---\r?\n(.*?)\r?\n---\s*(?:\r?\n|$)/s', $stripped, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Tiny `key: value` extractor — covers the shape every shipped SKILL.md
     * currently uses. Quoted values are unwrapped; multi-line / list /
     * nested values are intentionally ignored (not a YAML parser).
     *
     * @return array<string, string>
     */
    private static function parseScalars(string $frontmatter): array
    {
        $values = [];

        $lines = preg_split('/\r?\n/', $frontmatter);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*:\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $value = trim($matches[2]);

            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $values[$matches[1]] = $value;
        }

        return $values;
    }
}

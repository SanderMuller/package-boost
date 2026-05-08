<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SanderMuller\PackageBoost\Console\Internal\PackageRoot;

/**
 * Scaffolds a new `.ai/skills/<name>/SKILL.md` or `.ai/guidelines/<name>.md`
 * with the activating frontmatter / shape every shipped skill follows.
 *
 * Names are validated against the same kebab-case shape as shipped skills
 * (`^[a-z][a-z0-9-]*$`) so a skill that passes scaffolding can also pass
 * the frontmatter lint without further edits.
 */
final class NewCommand extends Command
{
    protected $signature = 'package-boost:new
        {kind : Either "skill" or "guideline"}
        {name : Kebab-case identifier (e.g. "deploy-checks")}
        {--description= : One-line description (used for skill auto-activation)}
        {--force : Overwrite an existing target}';

    protected $description = 'Scaffold a new .ai/skills/<name>/ or .ai/guidelines/<name>.md';

    public function handle(): int
    {
        $kind = (string) $this->argument('kind');
        $name = (string) $this->argument('name');
        $description = $this->option('description');
        $description = is_string($description) ? trim($description) : '';

        if (! in_array($kind, ['skill', 'guideline'], true)) {
            $this->components->error("Unknown kind '{$kind}'; expected 'skill' or 'guideline'.");

            return self::FAILURE;
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            $this->components->error(
                "Invalid name '{$name}': must be kebab-case starting with a lowercase letter (e.g. 'deploy-checks')."
            );

            return self::FAILURE;
        }

        $root = $this->resolvePackageRoot();
        $force = $this->option('force') === true;

        return $kind === 'skill'
            ? $this->scaffoldSkill($root, $name, $description, $force)
            : $this->scaffoldGuideline($root, $name, $description, $force);
    }

    private function scaffoldSkill(string $root, string $name, string $description, bool $force): int
    {
        $dir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . $name;
        $file = $dir . DIRECTORY_SEPARATOR . 'SKILL.md';

        if (file_exists($file) && ! $force) {
            $this->components->error(".ai/skills/{$name}/SKILL.md already exists; pass --force to overwrite.");

            return self::FAILURE;
        }

        $body = $description !== '' ? $description : 'TODO: one-line description used for auto-activation.';
        $rendered = "---\nname: {$name}\ndescription: {$body}\n---\n\n# " . Str::headline($name) . "\n\nTODO: skill body.\n";

        File::ensureDirectoryExists($dir);
        File::put($file, $rendered);

        $this->components->info("Created .ai/skills/{$name}/SKILL.md.");
        $this->components->info('Run `vendor/bin/testbench package-boost:sync` to propagate.');

        return self::SUCCESS;
    }

    private function scaffoldGuideline(string $root, string $name, string $description, bool $force): int
    {
        $dir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'guidelines';
        $file = $dir . DIRECTORY_SEPARATOR . $name . '.md';

        if (file_exists($file) && ! $force) {
            $this->components->error(".ai/guidelines/{$name}.md already exists; pass --force to overwrite.");

            return self::FAILURE;
        }

        $heading = Str::headline($name);
        $rendered = "# {$heading}\n";

        if ($description !== '') {
            $rendered .= "\n{$description}\n";
        }

        $rendered .= "\nTODO: guideline body.\n";

        File::ensureDirectoryExists($dir);
        File::put($file, $rendered);

        $this->components->info("Created .ai/guidelines/{$name}.md.");
        $this->components->info('Run `vendor/bin/testbench package-boost:sync` to propagate.');

        return self::SUCCESS;
    }

    private function resolvePackageRoot(): string
    {
        return PackageRoot::resolve();
    }
}

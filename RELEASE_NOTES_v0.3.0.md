# Package Boost v0.3.0

## Strip app-only Boost guidelines from package projects

Laravel Boost ships guidelines aimed at application development — Inertia,
Livewire, Filament, deployments, Herd/Sail, and so on. In a package
repository these sections just add noise to `CLAUDE.md` / `AGENTS.md`.

This release wires Package Boost into Boost's existing
`boost.guidelines.exclude` config so app-only guidance is dropped during
guideline composition, without manual edits.

### What's new

- **`config/package-boost.php`** — new publishable config with
  `excluded_boost_guidelines`, merged into `boost.guidelines.exclude` at
  service provider boot.
- **Opinionated defaults** for package development: `deployments`, `herd`,
  `sail`, `laravel/style`, `laravel/api`, `laravel/localization`,
  `inertia-*`, `livewire/*`, `volt/*`, `fluxui-*`, `folio/*`,
  `pennant/*`, `wayfinder/*`, `filament/*`, `nightwatch/*`, `pulse/*`,
  `tailwindcss/*`, `vite/core`.
- **No-op when Boost is absent** — the merge is guarded on
  `Laravel\Boost\BoostServiceProvider` existing, so nothing leaks into
  foreign config.
- **`publishes()` only runs in console**, avoiding wasted work on HTTP
  boots.
- **Publishes to `workbench/config/`** so Testbench's
  `LoadConfigurationWithWorkbench` bootstrap picks it up automatically
  — no edits inside `vendor/`.

### Upgrading

```bash
composer update sandermuller/package-boost
```

To customise the exclusion list:

```bash
vendor/bin/testbench vendor:publish --tag=package-boost-config
```

The file is published to `workbench/config/package-boost.php` in your
package repo. Edit it and add or remove keys. Keys match Boost's
`GuidelineComposer` keys exactly (e.g. `livewire/core`, `filament/v4`,
`herd`).

### Fixes

- `SyncCommandTest` `beforeEach` now wipes `.ai/` and `CLAUDE.md`,
  fixing pre-existing flakiness in the "warns when no … directory
  exists" tests.

### Compatibility

No breaking changes. If you were already setting
`boost.guidelines.exclude` yourself, your entries are preserved and
de-duplicated against the package defaults.

### Note for early testers

If you published the config before v0.3.0 and it landed inside
`vendor/orchestra/testbench-core/laravel/config/package-boost.php`
(shown as `@laravel/config/...` in CLI output), delete that file — it's
in vendor and will be wiped by composer. Upgrade, then re-run
`vendor:publish --tag=package-boost-config` to get the file at
`workbench/config/package-boost.php`.

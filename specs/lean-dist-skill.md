# Lean Dist Skill

## Overview

Ship a `lean-dist` skill under `resources/boost/skills/` that on-ramps consumers to `stolt/lean-package-validator` (lpv) for `.gitattributes` hygiene, including AI-era `export-ignore` patterns (`.ai/`, `.claude/`, `AGENTS.md`, `CLAUDE.md`) that lpv's default PHP preset doesn't cover. Content-only — no `src/` changes; the only test edit is one row added to the `SHIPPED_SKILLS` dataset. Complements vendor-skill discovery (0.9.0) which already surfaces lpv's three shipped skills once consumers install the dev dep.

---

## 1. Current State

`.gitattributes` `export-ignore` controls what lands in `composer require --prefer-dist` tarballs and GitHub release archives. Two gaps exist in the ecosystem:

- **AI-era artifacts ship to dist.** Consumer-facing repos that adopted `.ai/` / `.claude/` / `AGENTS.md` / `CLAUDE.md` rarely have these in their `.gitattributes`. Result: every install pulls dev tooling and occasionally leaks working-copy context.
- **lpv's default preset predates AI tooling.** `stolt/lean-package-validator` v5.6.1 ships `creating-` / `updating-` / `validating-gitattributes-file` skills under `resources/boost/skills/` (verified via GitHub API tree walk). Vendor-skill discovery (commit 535aaec, shipped 0.9.0) surfaces them automatically once installed. But its preset doesn't include AI-era paths, and the install + AI-era-pattern step has nowhere to live.

Existing shipped skills (`resources/boost/skills/`):

- `package-development/SKILL.md` — Testbench framing, source layout, sync workflow
- `cross-version-laravel-support/SKILL.md` — preventive cross-version patterns
- `ci-matrix-troubleshooting/SKILL.md` — diagnostic CI matrix workflow

Test gate: `tests/SyncCommandTest.php:9-13` `SHIPPED_SKILLS` dataset — every shipped skill has automatic per-skill coverage via Pest dataset-driven test (`it ships skill after a bare sync`). Adding a directory under `resources/boost/skills/` requires adding the name to that constant.

## 2. Proposed Changes

### New skill: `resources/boost/skills/lean-dist/SKILL.md`

YAML frontmatter (`name`, `description` with multi-keyword activation surface) + markdown body. Body order:

1. **Header framing** — what export-ignore controls, why AI-era artifacts matter; closes with on-ramp framing (lpv's three skills take over once installed).
2. **When to activate** — release prep, missing/thin `.gitattributes`, AI-tooling adoption audit.
3. **Step 1 — Install lpv** — `composer require --dev stolt/lean-package-validator`. Note vendor-discovery surfaces lpv's three skills automatically.
4. **Step 2 — Audit** — `validate`, three branches.
5. **Step 3a — Bootstrap** — dedicated `create` command (not `validate --create`, deprecated in v5.x).
6. **Step 3b — Update existing** — `validate --diff` first as a mandatory pre-overwrite review step (warn against blindly running `update` against a curated `.gitattributes`), then dedicated `update` command (not `validate --refresh`; `refresh` operates on `.lpv`, not `.gitattributes`).
7. **Step 4 — AI-era lines** — append directly to `.gitattributes`. Do **not** use a `.lpv` file: lpv's `.lpv` overrides the default preset (verified `vendor/stolt/lean-package-validator/src/Analyser.php:199-229,276-279` at lpv v5.6.1), not extends it. Includes warning that `lpv update` regenerates from preset and may strip manually-appended lines; recommends treating appended block as source of truth. Exact ignore block (12 entries):

   ```
   .ai                 export-ignore
   .claude             export-ignore
   .cursor             export-ignore
   .junie              export-ignore
   .cache              export-ignore
   AGENTS.md           export-ignore
   CLAUDE.md           export-ignore
   GEMINI.md           export-ignore
   .cursorrules        export-ignore
   .windsurfrules      export-ignore
   RELEASE_NOTES_*.md  export-ignore
   ROADMAP.md          export-ignore
   ```

   Excludes `.github/copilot-instructions.md` (already preset-ignored via `.github/`).

8. **Step 5 — Wire CI** — composer script + GitHub Actions snippet using `validate --validate-git-archive` (verified `vendor/stolt/lean-package-validator/src/Commands/ValidateCommand.php:149-154` at lpv v5.6.1).
9. **Step 6 — Verify locally** — same flag, plus optional eyeball-the-tarball fallback.
10. **Edge cases** — testing-helper packages that ship `tests/`, monorepos (per-leaf), hand-rolled `.gitattributes`.
11. **Why this matters** — closing framing on cost of ecosystem-wide bloat.

### Test dataset

Add `'lean-dist'` to `tests/SyncCommandTest.php:9-13` `SHIPPED_SKILLS`. Pest dataset-driven test (`it ships skill after a bare sync`) auto-covers it.

### Docs

- `README.md` — add bullet under *What It Does* listing the new skill.
- `ROADMAP.md` — add Open entry describing intent + lpv interplay.

`CHANGELOG.md` is auto-generated from GitHub Releases per repo convention; not edited manually.

### Out of scope

- Modifying `SyncCommand` / `SyncSources` — vendor discovery already routes lpv's three skills.
- Shipping a `.lpv` template — lpv treats `.lpv` as override, not extension. Documenting append-to-`.gitattributes` is the safe path.
- Custom `git archive | tar | grep` verification snippet — lpv's `--validate-git-archive` flag is more reliable.

## Implementation

- [x] Write `resources/boost/skills/lean-dist/SKILL.md` — frontmatter + 6-step body + edge cases — match voice of `cross-version-laravel-support` / `ci-matrix-troubleshooting`
- [x] Add `'lean-dist'` to `SHIPPED_SKILLS` in `tests/SyncCommandTest.php` — keeps comment-stated invariant
- [x] Update `README.md` *What It Does* — one bullet, link to lpv
- [x] Update `ROADMAP.md` *Open* section — describe skill + lpv interplay
- [x] Tests — `vendor/bin/pest --filter="ships skill after a bare sync"` covers the new dataset row; full suite must stay green (54 expected)
- [x] Quality — Pint clean, PHPStan 0 errors
- [x] Smoke-test fold-in — applied Findings #9–#14 to `resources/boost/skills/lean-dist/SKILL.md` (Symfony 8 / `-W` warning, trimmed outdated AI-era list, lpv-preset-is-opinionated note, JSON output mention, commit-before-archive-validate sequence, lpv house syntax). Net trim from 185 → 140 lines; structure shifted from "wrapper that mirrors lpv's commands" to "on-ramp + AI-era extras + commit-ordering + handoff."

---

## Open Questions

None.

---

## Resolved Questions

1. **Wrap lpv with a thin meta-skill or duplicate its functionality?** **Decision:** Wrap thin. **Rationale:** lpv ships three package-boost-format skills already; vendor-discovery picks them up. Duplicating creates maintenance burden + potential drift. Skill scope reduced to install + AI-era patterns + edge cases lpv doesn't know about.

2. **Ship a `.lpv` template seed or recommend appending to `.gitattributes`?** **Decision:** Append directly to `.gitattributes`. **Rationale:** `vendor/stolt/lean-package-validator/src/Analyser.php:199-229,276-279` (lpv v5.6.1) shows `.lpv` *overrides* the default preset rather than extending it — a minimal `.lpv` would silently drop lpv's PHP-preset coverage. Appending is the correct primitive even though `lpv update` may strip manual lines (mitigated by inline warning in Step 4).

3. **Verify dist with custom `tar | grep` or lpv's flag?** **Decision:** Use `validate --validate-git-archive`. **Rationale:** Custom regex was buggy (required leading `/` that `tar -tzf` doesn't emit for root entries → false negatives). lpv ships a purpose-built flag (`vendor/stolt/lean-package-validator/src/Commands/ValidateCommand.php:149-154` at v5.6.1). Less guidance to maintain, more accurate.

4. **`validate --create` / `validate --refresh` or dedicated `create` / `update` subcommands?** **Decision:** Dedicated subcommands. **Rationale:** `vendor/stolt/lean-package-validator/src/Commands/ValidateCommand.php:307-311` (lpv v5.6.1) prints deprecation notices for `--create` / `--overwrite`; `refresh` exists as a top-level command but operates on `.lpv` not `.gitattributes` (`vendor/stolt/lean-package-validator/src/Commands/RefreshCommand.php:57-58,121`). Skill cited deprecated paths in initial draft; rewrite landed before ship.

---

## Findings

Shipped on `main` (uncommitted as of spec write). Codex adversarial review caught three [high] findings against the initial draft, all confirmed against lpv source:

1. `.lpv` documented as additive — verified override-only via `src/Analyser.php`. Replaced with append-to-`.gitattributes` + explicit warning.
2. CLI workflow used deprecated `validate --create` / non-existent `validate --refresh`. Rewritten to `create` / `update` subcommands.
3. Custom verify regex required leading `/` not present in `tar -tzf` root entries → false negatives. Replaced with `validate --validate-git-archive`.

Self code-review pass added four further fixes:

4. Added explicit warning that `lpv update` may strip manually-appended AI-era lines (Step 4 callout).
5. Softened unverified preset-content assertions to "common dev artifacts" + handoff to lpv's `creating-gitattributes-file`.
6. Trimmed over-specified `--validate-git-archive` description to factual minimum.
7. Removed redundant `copilot-instructions.md` line (file lives at `.github/copilot-instructions.md`, already preset-ignored).

Final state: 54 Pest tests pass (187 assertions), Pint clean, PHPStan 0 errors. Spec written retroactively as a design record; will be pruned with the next `specs/` sweep per repo convention (precedent: commit 708c3aa).

8. **README drift caught during `/implement-spec` verification.** Initial README bullet said "AI-era `.lpv` patterns" — wording predated the codex-review rewrite which removed the `.lpv` template approach (lpv treats `.lpv` as override-not-additive). Updated bullet to "AI-era `export-ignore` entries" so README matches the shipped Step 4 guidance.

### Dogfood findings (smoke-test on package-boost itself, lpv v5.7.0)

Walked the skill end-to-end against this repo. Surfaced six issues the source-citation passes missed; all blockers for a real consumer following the skill verbatim:

9. **`composer require --dev` happy-path is broken on Laravel 13 / Symfony 8.** lpv v5.7.0's `composer.json` requires `symfony/console ^7.2.1||^v5.4.8`. Bare `composer require --dev stolt/lean-package-validator` fails with a Symfony 8 host. Workaround: `-W` resolves but **silently downgrades `symfony/console` from v8.x to v7.4.8**, taking transitive deps with it. Skill must call this out — either as a Step 1 prerequisite check or as a dedicated edge case for Laravel-13 hosts.

10. **lpv v5.7.0's preset already includes `.ai/` and `.claude/`.** Step 4 in the shipped skill claims "lpv's default preset doesn't include `.ai/`, `.claude/`, `AGENTS.md`, `CLAUDE.md`." First two are wrong as of v5.7.0. The genuine AI-era gaps are: `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.cursor/`, `.junie/`, `.cache/`, `.cursorrules`, `.windsurfrules`. Step 4's appended block needs trimming + a "verify against your installed lpv version" caveat.

11. **lpv preset is over-aggressive for OSS norms.** Default `create` output ignores `LICENSE.md`, `README.md`, `CHANGELOG.md`, `SECURITY.md`. Many OSS maintainers want LICENSE/README in the dist. Skill should warn that lpv's `create` is opinionated and the maintainer should review the generated file before committing.

12. **Output format is JSON, not text, in agentic contexts.** lpv v5.6.1+ auto-detects agentic invocation and emits JSON. Skill's prose ("hand off to lpv's `creating-gitattributes-file` skill for flag choices") implicitly assumes a text-mode CLI. Either pin `--no-agentic` (if it exists) or document the JSON shape consumers will see when triggered through a Claude Code skill.

13. **`git archive` reads `.gitattributes` from HEAD.** `validate --validate-git-archive` reports stale results until `.gitattributes` is committed. Verified: created `.gitattributes` in working tree, ran the gate, every preset entry showed up under `unexpected_artifacts` because HEAD's archive ignored the uncommitted file. Skill must instruct: commit `.gitattributes` first, then run the gate.

14. **House syntax differs from skill's example.** lpv generates `.ai/ export-ignore` (trailing slash on dirs, single space). Skill's Step 4 example uses `.ai` (no slash) with aligned spacing. Inconsistent — the appended block should match lpv's house style so `validate` doesn't grumble about formatting drift later.

Smoke-test passed `validate --validate-git-archive` correctly identified the 15 leaked artifacts in package-boost's archive (`.ai/`, `.github/`, `tests/`, `LICENSE.md`, `README.md`, etc.) — gate works as advertised. Repo state restored after test (composer.lock floated `symfony/console` back to v8.0.8 after `composer update`).

### Smoke-test re-run on the rewrite

Walked the trimmed skill end-to-end on package-boost itself. Result:

- `composer require --dev stolt/lean-package-validator -W` — installed, `symfony/console` 8.0.8 → 7.4.8 as warned.
- `lean-package-validator create` — generated 20-line `.gitattributes`.
- Append AI-era block (8 entries) — `validate` reports `valid: true`.
- Pre-commit `validate --validate-git-archive` — `archive_valid: false`, 15 leaks (per Step 4 warning: HEAD doesn't see uncommitted file).
- Post-commit `validate --validate-git-archive` — `archive_valid: true`, 0 leaks.
- Dist size: 60,243 bytes / 77 files → 19,146 bytes / 27 files. **68% size reduction, 65% file reduction.** Zero AI artifacts in the resulting tarball.

Smoke test executed on a throwaway `temp-leandist-smoketest` branch; main rolled back, lpv removed, lock file restored to `symfony/console` v8.0.8. Spec ready to close after upstream lpv issue is filed (Symfony 8 / `console ^8.0` constraint widening — Finding #9).

### Smoke test #2 (after Step 1 trim, against lpv v5.7.1)

Ran the trimmed skill against package-boost again after Step 1 had been simplified (no `-W` workaround, plain `composer require --dev stolt/lean-package-validator`). lpv v5.7.1 had landed upstream the same day Finding #9 was filed: maintainer Raphael Stolt shipped both the `symfony/console`/`finder` constraint widening (`4f00d2d`) and the `Application::add()` → `addCommand()` test fix (`6e8d0e0`) in v5.7.1. Our preemptive PR #67 (Add Symfony 8 support) was opened against a stale base and closed by the maintainer as a duplicate within minutes — no harm beyond a brief detour.

Cache-staleness sub-finding: the first smoke test pulled v5.7.0 even though v5.7.1 had been published 18+ hours earlier. Root cause was Composer's local provider-metadata cache (`~/Library/Caches/composer/repo/https---repo.packagist.org/provider-stolt~lean-package-validator.json`) — file held 77 versions topped at v5.7.0; second smoke test (after the cache had refreshed via an unrelated install in `/tmp/smoke-repro`) correctly resolved v5.7.1. Skill's Step 1 now suggests `composer clear-cache` for users hitting the same window.

Smoke test #2 outcomes:

- `composer require --dev stolt/lean-package-validator` — clean install, no `-W`, no Symfony downgrade. v5.7.1 + symfony/console v8.0.8 coexist.
- `validate` (no `.gitattributes` present) — reports failure with `expected_gitattributes_content`. lpv v5.7.1 preset has grown to include `.agents/`, `.cursor/`, `.junie/`, `.kiro/` on top of v5.6.1's `.ai/` + `.claude/`. Auto-detection of unusual root files (e.g. `lpv-symfony8-issue.md` left over from this work) also lands in the suggested ignore list.
- `create` — generated `.gitattributes` with 24 entries from preset.
- AI-era append (per Step 3): trimmed from 8 lines → 6 (`.cursor/` and `.junie/` removed since both are now in lpv's preset). After append: `validate` reports `valid: true`.
- `validate --validate-git-archive` (pre-commit) — `archive_valid: false`, 15 leaks, confirming Step 4's "git archive reads HEAD, not working tree" warning still applies in v5.7.1.

Skipped the post-commit verification this round to avoid creating commits in a shared working tree (peer agent in flight on `specs/multi-agent-sync.md`). Skill's Step 4 post-commit assertion remains validated by smoke test #1 in the previous Findings entry.

Skill changes after smoke test #2:

- Step 1: dropped `-W` requirement and Symfony 8 install-break warning. Added one-line `composer clear-cache` mitigation for v5.7.0/v5.7.1 cache-staleness window.
- Step 3: AI-era list trimmed from 8 → 6 entries (removed `.cursor/`, `.junie/`). Updated preset-evolution caveat to name v5.7.1's additions.

Repo restored to peer's pre-existing state via `composer remove --dev stolt/lean-package-validator`. `tests/SyncCommandTest.php` left in `MM` state: my staged `'lean-dist'` row + peer's unstaged Phase-3 multi-agent-sync edits coexist (peer confirmed via claude-peers channel that the `SHIPPED_SKILLS` const is untouched in their branch).

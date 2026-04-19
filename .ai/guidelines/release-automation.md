# Release Automation

## `CHANGELOG.md` is updated automatically — do not edit by hand

`CHANGELOG.md` is kept in sync with GitHub releases by
`.github/workflows/update-changelog.yml`. When a release is
published (not just drafted), the workflow uses
`stefanzweifel/changelog-updater-action` to prepend the release
body to `CHANGELOG.md` and commits the update back to `main`.

This means:

- **Do not** add changelog entries manually when preparing a
  release. The release body — drafted in
  `RELEASE_NOTES_vX.Y.Z.md` (gitignored) and pasted into the
  GitHub release — becomes the changelog entry automatically.
- **Do not** include a changelog diff in the release PR; the
  post-release commit comes from CI.
- If the changelog needs a fix *after* a release (typo, formatting
  in the auto-generated entry), edit `CHANGELOG.md` directly and
  commit. Unusual.

## Release workflow (summary)

1. Draft release notes in `RELEASE_NOTES_vX.Y.Z.md` (gitignored;
   stays local).
2. Verify the code changes are on `main` via the pre-release
   checklist (`rector`, `pint`, `phpstan`, full `pest` suite,
   README freshness, synced `.ai/` artefacts).
3. `git tag -a X.Y.Z -m "Release X.Y.Z — <headline>"` on the
   commit, then `git push origin X.Y.Z`.
4. `gh release create X.Y.Z --title "vX.Y.Z" --notes-file
   RELEASE_NOTES_vX.Y.Z.md`.
5. CI's `update-changelog` workflow prepends the release body to
   `CHANGELOG.md` and commits it back to `main`.

No manual `CHANGELOG.md` edits during the release PR.

## Package-boost dogfoods itself

This repo's `.ai/` directory ships the sources; the generated
outputs (`CLAUDE.md`, `AGENTS.md`, `.github/copilot-instructions.md`,
`.claude/skills/`, `.github/skills/`) are **not committed** because
the sync-command tests exercise the same filesystem paths and
would clobber any committed copies on every `pest` run.

After `composer install`, generate them locally:

```bash
vendor/bin/testbench package-boost:sync
```

Re-run after editing anything under `.ai/` or `resources/boost/`.

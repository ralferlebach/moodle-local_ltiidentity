# Replacement of the development rump and migration (IP-01)

Status: release 0.3.0 (`2026102700`), 2026-10-27. Owner decision 2026-10-27: this implementation **replaces** the code
that was on the GitHub `development` branch; there is **no upgrade path** from that code.

## 1. What is replaced

| | Development rump (replaced) | This implementation |
|---|---|---|
| Commit on `development` | `059919a` "Initial push, version 0.1.0" | delivered as plugin ZIP 0.3.0 (owner pushes it to `development`) |
| Component / path | `local_ltiidentity` / `local/ltiidentity` | `local_ltiidentity` / `local/ltiidentity` (same) |
| Version / release | `2026042200` / `0.1.0`, requires 4.4 (`2024042200`) | `2026102700` / `0.3.0`, requires 4.5 (`2024100700`), supported 4.5-5.3 |
| Approach | post-login reconciliation (observer on `\core\event\user_loggedin`), own override table | pre-write protection in core's `before_user_updated` hook, values only in the core `user` table |
| Tables | `local_ltiidentity_override`, `_mailtoken`, `_namerule`, `_audit` (columns `userid, actorid, action, detailsjson, timecreated`) | `local_ltiidentity_audit` (columns `actorid, targetuserid, contextid, action, fieldnames, oldflag, newflag, timecreated`) |
| Capabilities | `:managesiteidentity`, `:managecourseidentity`, `:selfeditidentity`, `:viewaudit` | `:manage`, `:editown`, `:releaselock`, `:viewprovenance` |
| Language | `lang/en` only | `lang/en`, `lang/de` |
| Tests | none | PHPUnit, Behat, Playwright (see `docs/TESTING.md`) |

The component name, install path, language file names, table prefix, namespaces and package name of this implementation
are consistent (`tests/consistency_test.php`). The earlier ZIPs of this implementation used the name
`local_ltiidentityprofile` (0.1.0 alpha, 2026-10-24); it was renamed to `local_ltiidentity` in 0.2.0 (see
`docs/CHANGELOG.md`, `docs/NAMING.md`). From 0.2.0 on, the component of the ZIPs and of `development` is the same.

## 2. Why there is no upgrade path from the rump

* The rump was never installed productively (owner statement); there is no data to carry over.
* Both use the table name `local_ltiidentity_audit`, with **different columns**. Moodle would treat this implementation
  as an upgrade of the installed rump (`2026042200` < `2026102700`), skip `install.xml` and run on the rump's schema.
* Its override/name-rule model (a second copy of names and e-mail in a plugin table) contradicts the specification
  (IP-P02: names and e-mail only in the core `user` table) and cannot be mapped without inventing provenance.

Therefore `db/upgrade.php` stops with the message `upgradefromrump` for any installed version below `2026102501` (the
first build of this implementation) before anything is changed (`tests/upgrade_test.php::test_rump_is_refused`).

## 3. If a site has the rump installed anyway

1. Put the rump's code (commit `059919a`) back into `local/ltiidentity`.
2. Uninstall it: Site administration > Plugins > Plugins overview > LTI identity > Uninstall, or
   `php admin/cli/uninstall_plugins.php --plugins=local_ltiidentity --run`. This drops its four tables, capabilities and
   settings with the rump's own `install.xml`.
3. Remove the directory, deploy this release, run the upgrade: it installs as a new plugin.

Uninstalling with this implementation's code in place would only drop the tables named in this `install.xml` and leave the
rump's `_override`, `_mailtoken` and `_namerule` tables behind; do not do that.

## 4. Repository: files of the rump that must not survive

Copying this release over the rump's working tree leaves rump-only files behind. The dangerous one is `db/events.php`: it
would keep registering the rump's post-login observer (`classes/event/observer.php`), which reads tables this
implementation does not have. All rump-only files are listed in `db/removed_files.txt`; the CI job "Stale files" fails while
any of them exists, and `tests/consistency_test.php::test_removed_files_are_absent` does the same locally. Replace the
working tree as a whole (delete, then copy) rather than copying over it.

The rump's `.github/workflows/moodle-ci.yml` (4.5-5.2 only, PHPUnit skipped without tests) is on the list as well; the
workflows of this plugin are owned by the CI maintainer, so this release does not edit or delete workflow files itself.

The rump's documents `docs/Lastenheft.md`, `docs/Pflichtenheft.md`, `docs/Blueprint.md`, `docs/Moodle-Plugin Coding-Styles.md`,
`docs/coding-standards-prompt.md`, `docs/linting jobs.txt` and `docs/sessions/session-000.md` describe the rump's
post-login design. They are not code and are not on the removal list; they are superseded by `docs/DOCUMENTATION.md` and the
specification `01_lti_identity_profile.md` and should be removed or marked as historical by the owner.

## 5. Upgrade paths that are supported

| From | To | Step | Evidence |
|---|---|---|---|
| 0.2.0 (`2026102501`, `2026102502`) | 0.3.0 (`2026102700`) | provenance backfill from the 0.2.0 field locks (idempotent, no schema change) | `upgrade_test::test_upgrade_from_020_backfills_provenance` (both builds), `::test_backfill_keeps_existing_records` |
| fresh install | 0.3.0 | `install.xml` | `install_test`, `consistency_test::test_schema` |
| rump 0.1.0 (`2026042200`) or anything older than `2026102501` | - | refused with `upgradefromrump` | `upgrade_test::test_rump_is_refused` |
| `local_ltiidentityprofile` 0.1.0 alpha (other component) | - | uninstall the old component first (see CHANGELOG 0.2.0) | - |

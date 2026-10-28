# Changelog

## 0.3.0 (alpha) - 2026-10-27

Family audit 2026-10-27 (development branches), items IP-01 to IP-04 and FAM-00/01/04/05. Version `2026102700`.

### Replaces the development rump (IP-01)
* This implementation replaces the April 2026 rump that was on the GitHub `development` branch under the same component
  name (release 0.1.0, version `2026042200`, commit `059919a`). There is **no upgrade path from the development rump**
  (owner decision 2026-10-27; it was never installed productively): it has an incompatible schema with a conflicting
  `local_ltiidentity_audit` table and a post-login observer. `db/upgrade.php` now stops with a clear message
  (`upgradefromrump`) for any installed version below `2026102501` before anything is changed. Migration note:
  `docs/MIGRATION.md`.
* `db/removed_files.txt` lists the rump-only files (among them `db/events.php` with the post-login observer and its
  `classes/local/service/*`); the stale-files CI job and `consistency_test::test_removed_files_are_absent` fail while any of
  them exists.
* New `tests/consistency_test.php`: component, install path, language files (en/de, same keys), `install.xml` path and table
  names, installed tables, namespaces, `@package` tags, capability names, makefile/phpcs package name; no former or rump
  names in code.

### Added
* Field provenance (IP-04): for first name, last name and e-mail the plugin records where the current value came from -
  LTI platform (launch / membership sync), teacher, the user, the user confirmed by e-mail link, or another writer
  (administrator form, upload, web service) - in core user preferences `local_ltiidentity_src_<field>` as
  `<source>:<sha256 of the value>` (`local\provenance`). A record that no longer matches the stored value is ignored
  ("not recorded"); placeholders are recognised from the value. Shown on the user profile page (`myprofile_navigation`
  callback, category "Origin of LTI profile data", sources and lock states only, no values) and on the self-service page.
  Visible to the user, to teachers who may manage the user in the course of a course profile, and to holders of the new
  capability `local/ltiidentity:viewprovenance` (manager). Privacy export (source only, no hash), erasure, uninstall.
* Upgrade step `2026102700`: provenance records are created from the 0.2.0 field locks (teacher, self; verified only when
  the verification hash matches the current address); idempotent. Test: `upgrade_test` (from 2026102501 and 2026102502).
* Capability `local/ltiidentity:viewprovenance` (system, read, `RISK_PERSONAL`, manager).
* Tests: `claim_scenarios_test` (incomplete, contradictory, changing claims, repeated launches, existing accounts, all with
  real `auth_plugin_lti::complete_login()`), `sync_ordering_test` (no post-write step; the protected value is never written,
  observed directly before and after core's write; negative control), `provenance_test`, `recovery_test`,
  `capability_matrix_test`, `consistency_test`, `upgrade_test`; Behat `sync_protection.feature`, `provenance.feature`
  (real auth_lti membership sync); Playwright `identity-claims.spec.js` (two-site negative cases).

### Changed
* A claim that is only white space counts as missing and never replaces a non-empty local value (before, a
  `given_name` of blanks passed auth_lti unchanged and replaced an unlocked name under "claims win"); a blank stored
  value has nothing to protect.
* Confirming a new e-mail address deletes outstanding password reset tokens of the account (sent to the old address).
  Moodle 5.3 does this in `\core\user::update_user()`, Moodle 4.5 does not; now both behave the same.
* The hook callback records the provenance of values that are really written (one preference write per changed field; a
  relaunch that changes nothing writes nothing). `sync_detector_test::test_hook_cost_is_bounded` bounds it.
* Playwright story spec restores Site A's tool configuration after the run.
* Version `2026102700`, release `0.3.0`.

### Fixed
* README and DOCUMENTATION still described the stack-based caller check of 0.1.0 and claimed there was no scheduled task.

## 0.2.0 (alpha) - 2026-10-25

### Post-rename cleanup (2026-10-25, version unchanged)
* Uninstall now removes every user preference the plugin writes (field locks `local_ltiidentity_lock_firstname|lastname|email`, `local_ltiidentity_emailpending`, `local_ltiidentity_emailverified`): new `db/uninstall.php` (`xmldb_local_ltiidentity_uninstall()`) calls `local\preferences::delete_all()` and flags the affected users so cached session copies are reloaded. Moodle's own uninstall does not delete user preferences. Test: `uninstall_test` (3 tests; the real flows write exactly the listed names, all of them are removed, preferences of core and other components stay, the list equals the privacy metadata).
* Note for sites that ran the 0.1.0 alpha under the old name `local_ltiidentityprofile`: that version had no uninstall hook, so its preferences (`local_ltiidentityprofile_*`) survive its uninstall. They are not read by anything; remove them with `DELETE FROM {user_preferences} WHERE name LIKE 'local\_ltiidentityprofile\_%'` if wanted. (The four test databases contain none.)
* CI workflows: the reduced and full matrices (4.5 to 5.3, PostgreSQL and MariaDB) are unchanged. The dev pipeline's code checker runs without an installed Moodle and now gets the plugin path (`moodle-plugin-ci codechecker ... plugin`); without it the blocking step stopped with "Not enough arguments (missing: plugin)". Header comments state the real matrix; template wording about sibling plugins was removed. Validated with a YAML parser and a path/name check (see TESTING.md).

### Renamed (owner decision 2026-10-25)
* Renamed from `local_ltiidentityprofile` (install path `local/ltiidentityprofile`) to `local_ltiidentity` (install path
  `local/ltiidentity`); no upgrade path from 0.1.0 alpha. 0.2.0 is a fresh install: uninstall the old plugin (this deletes its
  audit rows and its settings; its field locks and pending e-mail requests in user preferences stay, see the note above) and
  install this one. Changed
  with the name: table `local_ltiidentity_audit`, capabilities `local/ltiidentity:*`, settings, user preference names
  (`local_ltiidentity_*`), language files, namespaces, URLs `/local/ltiidentity/...`, Behat tag `@local_ltiidentity` and step
  class `behat_local_ltiidentity`; display name "LTI Identity".
* `db/upgrade.php` has no steps any more; the 0.1.0 upgrade test was replaced by `install_test` (no-op upgrade function, index and
  setting defaults present after a fresh install, schema equals install.xml, release).

Re-audit rework (items FAM-01, IP-01, IP-02, FAM-04).

### Added
* `local\sync_detector`: recognition of the auth_lti sync by the fingerprint of the update object, without
  inspecting the call stack; `core\check` status check `sync_detection` that compares the fingerprint with the
  installed core source and warns when core changes it (Site administration -> Reports -> Status).
* `local\locking`: per-account lock (core lock factory) around e-mail request/confirmation and profile saves;
  setting `locktimeout` (default 10 s); results `busy` / error `errorbusy` on timeout.
* Scheduled task `purge_stale_data`: removes expired or undecodable pending e-mail requests and audit rows older
  than the new setting `auditretention` (default 730 days, 0 = keep).
* Event `sync_protected` (field names only) behind the new setting `logprotected` (default off).
* Index `timecreated` on the audit table.
* Tests: `sync_detector_test`, `identity_cases_test`, `install_test`, privacy export without token hash.
* tool_mergeusers integration (Gate-2 finding F4): callback for the optional hook
  `\tool_mergeusers\hook\after_merged_all_tables` (`local\merge_handler`). Inside the merge transaction, locks of
  the merged-away account move to the surviving account where it has none and holds a real value; the surviving
  account's own locks win; a verified e-mail lock and the verification flag move only for the identical address;
  a pending e-mail change of the merged-away account is cancelled; audit actions `mergeinto` / `mergecancel`.
  Inert when tool_mergeusers is not installed. Test: `merge_handler_test`.

### Changed
* The callback no longer uses `debug_backtrace()`. Layer 1 (placeholder values never overwrite real values) applies
  to every writer of an `auth=lti` account; layer 2 (locks and claim priority) applies to updates with the auth_lti
  sync fingerprint (exactly `id`, `firstname`, `lastname`, `email`), excluding web service requests and this
  plugin's own writes.
* The hook resolves the auth_lti bindings once per call instead of once per field and checks `auth` first, so
  updates of other accounts cost no query.
* E-mail confirmation runs under the account lock and in one transaction (address, verification flag, field lock,
  consumption of the request); a failed write leaves the link usable. The address is re-validated against the
  allow/deny lists at confirmation time.
* Teacher list shows "Name not provided by the platform (<platform id>)" instead of the raw placeholder; the edit
  forms start empty for placeholder values.
* Privacy export of the pending e-mail request omits the token hash.
* Version `2026102502`, release `0.2.0`.

### Fixed
* A change of the core call chain (renamed or wrapped caller) could silently disable the lock protection.
* Two simultaneous confirmations of the same link could both pass the token check.

### Removed
* `hook_callbacks::is_auth_lti_sync()` (stack based detection).

## 0.1.0 (alpha, MVP) - 2026-10-24

First functional version.

* Pre-sync protection through the core hook `\core_user\hook\before_user_updated` for updates started
  by `auth_plugin_lti::update_user_account()` (launch and NRPS membership sync); field priority
  locked value > transmitted claim > local value > placeholder; admin policy for claims versus unlocked
  local values.
* Lock/provenance flags in `user_preferences`; small audit table (no names or addresses).
* Teacher page (course navigation), self-service page (preferences navigation), both writing through
  `user_update_user()`.
* E-mail change with hashed one-time token, expiry, attempt limit, anti-replay, logged-in-owner check,
  duplicate policy (allow/warn/block, never weaker than `allowaccountssameemail`).
* Admin lock release with audit and events (`profile_updated`, `lock_released`, `email_verified`).
* Privacy API provider (audit rows, preferences), English and German strings.
* Tests: PHPUnit unit and integration (real `auth_plugin_lti::complete_login()`), Behat feature files,
  Playwright end-to-end story.
* Removed from the template: load-test files and the manual load/Playwright GitHub workflows (not
  applicable to this plugin), the sibling "ecosystem lockstep" CI job (the plugins are independent).

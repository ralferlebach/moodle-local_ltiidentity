# Acceptance - local_ltiidentity 0.3.0 (alpha)

Release 0.3.0, version `2026102700`, date of all local evidence below: **2026-10-27**. Audit basis: family audit
2026-10-27 (development branches), items IP-01..IP-04 and FAM-00..05; specification `01_lti_identity_profile.md`.

Status classes (strictly separated, audit section 1 / FAM-05):
**C** = code present, **T** = automated tests executed locally (by the developer, named tree/DB cells),
**CI** = CI passed on GitHub (**not executed by us**: no GitHub access this round; the owner pushes the ZIP to `development`
and the development CI of that SHA is the CI evidence), **F** = functionally accepted (owner; **nothing yet**),
**N** = not verified, **G** = core gate open. A skipped test is never counted; no item is counted on fixtures/mocks alone
where an end-to-end path is claimed.

Code base: this implementation (work directory, delivered as ZIP). It **replaces** the development rump at `059919a`
(0.1.0, `2026042200`) - no upgrade path, see `docs/MIGRATION.md`. No development SHA of this code exists yet; the owner's
push creates it, and its CI run is the first CI evidence.

## 1. Local runs of 0.3.0 (2026-10-27)

| Cell | phplint, validate, savepoints, mustache | codechecker (0 warnings) | phpdoc (0 warnings) | PHPUnit (`--fail-on-warning --fail-on-risky --fail-on-skipped`) |
|---|---|---|---|---|
| Moodle 4.5.15+ / PostgreSQL 16, PHP 8.3 | OK | OK | OK | **OK, 214 tests, 1620 assertions** |
| Moodle 4.5.15+ / MariaDB 11.4, PHP 8.3 | OK | OK | OK | **OK, 214 tests, 1620 assertions** |
| Moodle 5.3 / PostgreSQL 17, PHP 8.3 | OK | OK | OK | **OK, 214 tests, 1619 assertions**; 40 PHPUnit-11 deprecations (accepted) |
| Moodle 5.3 / MariaDB 11.4, PHP 8.3 | OK | OK | OK | **OK, 214 tests, 1619 assertions**; 40 PHPUnit-11 deprecations (doc-comment metadata, accepted by the brief) |

Failure classification (FAM-05): the first attempts of both PostgreSQL cells after the environment restart failed with
**setup failures** (`FATAL: the database system is starting up` / `not yet accepting connections` during `upgrade` and
`phpunit init`); static checks of those attempts were OK and the 5.3 attempt's PHPUnit ran green (214). They were repeated
once the servers accepted connections; the table shows the repeated runs. Moodle 5.0-5.2 trees exist but were not run by
this agent (the lead runs `ltifullmatrix`): **N** for 5.0-5.2.

| Behat (`ltibehat`, non-JavaScript, local) | Result |
|---|---|
| 4.5 / PostgreSQL | **14 scenarios / 149 steps passed** (5 feature files) |
| 5.3 / MariaDB | **14 scenarios / 149 steps passed** |
| 5.3 / PostgreSQL | **14 scenarios / 149 steps passed** |
| 4.5 / MariaDB | not run this round (N) |

`ltibehat` ends its log with "Terminated" when it stops its web server process group; the scenario/step summary lines
above are the result. `@javascript` scenarios: none in this plugin. CI Behat: not executed.

| Playwright (two-site stack: A = Moodle 4.5 platform, B = Moodle 5.3 tool, PostgreSQL, Chromium only; under `/tmp/e2e_stack.lock`) | Result |
|---|---|
| `identity-claims.spec.js` (8 tests, new) | **8 passed** (run 1 and run 2) |
| `identity-profile.spec.js` (5 tests) | run 1: 1 passed, 1 failed, 3 not run - **precondition failure of the shared stack**: the B account of studentA3 had `auth = manual` (changed outside this plugin, which never changes `auth`); auth_lti then skips the profile sync and the teacher list correctly omits non-LTI accounts. The spec now restores `auth = lti` together with the placeholder state. Run 2 (both specs, one flock'd invocation): **13 passed, 0 failed** |

Both specs restore what they change (A: send name/e-mail, launch container, learner name; B: claim policy, fixture account), verified after the run.

Mutation / negative controls (P0 guarantees of this release; executed 2026-10-27 on 4.5/MariaDB against a mutated copy,
both 4.5 locks held, clean code restored and verified identical afterwards): five mutations applied together, each
detected by its own tests - 72 tests run, **8 red**, 0 other failures:

| Mutation | Detected by |
|---|---|
| A: blank claim treated as real (`policy::decide` back to `$new === ''`) | `claim_scenarios_test::test_contradictory_claims_do_not_reset_real_values` ("white space only"), `policy_test::test_decide` ("claimswin blank claim keeps local") |
| B: invalid e-mail claim treated as real (`$newmissing` without `validate_email`) | `claim_scenarios_test::...` ("invalid e-mail address"); this test was also red on the unfixed code at its first run |
| C: provenance record not bound to the value | `provenance_test::test_record_is_bound_to_the_value` |
| D: reset tokens not deleted at confirmation | `recovery_test::test_confirmation_invalidates_outstanding_reset_tokens` (on 4.5; on 5.3 core deletes them itself, so the test is no control there) |
| E: rump guard removed | `upgrade_test::test_rump_is_refused` (3 data sets) |

Further negative controls that are part of the suite: `hook_gate_test::test_without_callback_the_launch_overwrites`,
`sync_ordering_test::test_without_plugin_the_probes_see_the_overwrite`, `merge_handler_test::test_without_transfer_claims_overwrite_b`.

## 2. Audit items

| ID | Status | Code location | Tests | Result (2026-10-27) |
|---|---|---|---|---|
| **IP-01** component/path/lang/schema/package consistent; replacement + migration | C, T (4 cells); CI not executed; F open | `version.php` (`local_ltiidentity`, `2026102700`, 0.3.0), `db/install.xml:2` (PATH), `lang/{en,de}/local_ltiidentity.php`, `db/upgrade.php:43-44` (rump refused), `db/removed_files.txt:14-30` (rump files), `docs/MIGRATION.md` | `consistency_test` (7), `upgrade_test::test_rump_is_refused`, `install_test` | green on all four cells; mutation E red |
| **IP-02** pre-sync control flow down to auth_lti, negative cases, no correction race | C, T (PHPUnit 4 cells, Behat 2-3 cells, Playwright 1 stack); 5.0-5.2 N; CI not executed | flow with file:line for 4.5 and 5.3: `docs/DOCUMENTATION.md` section 1; plugin: `classes/hook_callbacks.php:58` (callback), `:87` (priority), `:105` (invalid e-mail), `classes/local/policy.php:80,90` (blank values), `classes/local/sync_detector.php:49`; `db/hooks.php` (only pre-write hook; no `db/events.php`) | `claim_scenarios_test` (9 methods, 18 cases), `sync_ordering_test` (4), `hook_gate_test` (3), `lti_launch_test` (9), `hook_callbacks_test`, `sync_detector_test`; Behat `sync_protection.feature` (3 scenarios, real auth_lti membership sync); Playwright `identity-claims.spec.js` | see section 1; finding fixed in this release: blank and invalid e-mail claims replaced unlocked real values under "claims win" (mutations A, B) |
| **IP-03** real tests on every layer, requirement mapping | T (see section 1, section 3 mapping); CI not executed | `tests/` | PHPUnit 143 methods / 214 cases, Behat 14 scenarios, Playwright 13 tests | every IP-L/IP-P requirement mapped in section 3; coverage not measured locally (N) |
| **IP-04** e-mail link, expiry/replay, duplicates, recovery, privacy/uninstall, provenance, capability matrix | C, T (4 cells); browser parts T (Playwright, Behat); CI not executed; F open | `classes/local/email_verifier.php:195` (link from wwwroot), `:225-268` (confirm: hash_equals, attempts, transaction, reset tokens `:268`), `classes/local/provenance.php:96,132,171`, `classes/output/profile_provenance.php:96`, `lib.php:96`, `db/access.php:48`, `classes/privacy/provider.php:167`, `classes/local/preferences.php:38`, `db/upgradelib.php:42` | `email_verifier_test` (17), `identity_cases_test` (13), `recovery_test` (5), `provenance_test` (7), `capability_matrix_test` (4), `privacy/provider_test` (7), `uninstall_test` (3), `upgrade_test` (backfill); Behat `provenance.feature`; Playwright tests 3 and 8 of `identity-claims.spec.js` | green on all four cells; mutations C, D red |
| FAM-00 branch policy documented | C (README "Branch and release policy") | `README.md` | - | rulesets on `main` are the owner's task (N) |
| FAM-01 component/source/release map | C (table below) | section 4 | `consistency_test` | - |
| FAM-02 no lockstep, optional integrations degrade | C, T | `version.php` (`dependencies = []`), `db/hooks.php` (tool_mergeusers hook inert without the plugin) | `merge_handler_test::test_registration_without_tool_mergeusers` | green |
| FAM-03 family integration test | N (lead's scope) | - | - | not executed by this agent |
| FAM-04 security, privacy, resources | C, T for capability+sesskey, privacy export/erasure, hook cost bound, lock timeouts; **N** for load test, multi-process race, accessibility (WCAG/NVDA), browser matrix beyond Chromium | `docs/SECURITY.md`, `docs/PRIVACY.md`, `docs/DOCUMENTATION.md` 4a | `sync_detector_test::test_hook_cost_is_bounded`, `identity_cases_test`, `privacy/provider_test` | green |
| FAM-05 evidence separation | C | this file | - | local vs. CI separated; setup failures classified (section 1) |

## 3. Requirement mapping (specification 01)

| ID | Requirement | Status | Tests |
|---|---|---|---|
| IP-L01 | existing LTI link unchanged | T | `lti_launch_test::test_two_launches_without_claims_keep_teacher_values` (one binding, same id), `claim_scenarios_test::test_repeated_launch_writes_nothing`, `::test_existing_bound_manual_account_is_left_to_core`; Playwright test 6 (one binding after repeated launches) |
| IP-L02 | placeholders recognisable | T | `placeholder_test`, `identity_cases_test::test_presentation_hides_placeholders`, `provenance_test::test_launch_and_other_writers` ("placeholder"); Behat `teacher_profile.feature`; Playwright `identity-profile` test 2 (badge), `identity-claims` test 3 (provenance "not transmitted") |
| IP-L03 | teacher completes alias in own course | T | `profile_service_test::test_teacher_scope`, `::test_save_updates_core_user_locks_audits_and_triggers_event`, `capability_matrix_test::test_action_matrix`; Behat `teacher_profile.feature`; Playwright `identity-profile` test 2, `identity-claims` test 3 |
| IP-L04 | self-service | T | `profile_service_test::test_self_service_access`, `::test_self_service_save`, `navigation_test`; Behat `self_service.feature`, `provenance.feature` (scenario 2) |
| IP-L05 | e-mail confirmation by one-time link | T (service, page via Behat request step); confirmation click in a browser N | `email_verifier_test::test_request_and_confirm`, `::test_token_is_one_time`, `::test_token_expires`, `::test_wrong_token_and_attempt_limit`, `::test_other_user_cannot_confirm`, `::test_link_ignores_spoofed_host_headers`, `identity_cases_test::test_concurrent_confirmation`; Behat `self_service.feature` (request) |
| IP-L06 | accounts with the same e-mail handled by policy | T | `email_verifier_test::test_duplicate_policy`, `::test_duplicate_rechecked_at_confirmation`, `recovery_test::test_duplicate_is_never_linked`; Playwright `identity-claims` test 7 |
| IP-L07 | relaunch does not overwrite local values unintentionally | T | `claim_scenarios_test` (all), `lti_launch_test`, `sync_ordering_test`; Behat `sync_protection.feature`; Playwright `identity-profile` test 3, `identity-claims` tests 4-6 |
| IP-L08 | GDPR export/deletion | T | `privacy/provider_test` (7, incl. provenance), `uninstall_test` |
| IP-L09 | no account merge here | T | `email_verifier_test::test_same_email_two_identities_no_association_no_reset`, `recovery_test::test_duplicate_is_never_linked`, `claim_scenarios_test::test_existing_manual_account_with_same_email_is_not_touched`; Playwright `identity-claims` test 7 |
| IP-P01 | deterministic field priority | T | `policy_test` (33 cases), `claim_scenarios_test::test_changing_claims`, `lti_launch_test::test_launch_with_claims_versus_lock` |
| IP-P02 | only core fields + `user_preferences` | T | `lock_manager_test`, `provenance_test::test_record_is_bound_to_the_value` (hash only), `consistency_test::test_schema` (one audit table), `profile_service_test` (no values in audit) |
| IP-P03 | teacher UI only for authorised users, logged | T | `profile_service_test`, `capability_matrix_test`; Playwright `identity-profile` test 5 |
| IP-P04 | verification with expiry, anti-replay, no unconfirmed adoption, no linking | T | `email_verifier_test`, `recovery_test::test_pending_address_is_unknown_to_forgot_password`, `identity_cases_test::test_unconfirmed_value_is_never_verified` |
| IP-P05 | duplicate policy, recovery warning, issuer+sub logins | T | `email_verifier_test::test_duplicate_policy`, `recovery_test` (5); warning string `emailduplicatewarning` |
| IP-P06 | conflicts documented, admin release with audit | T | `lti_launch_test::test_release_lock_lets_claims_through`, `profile_service_test::test_release_lock_requires_capability_and_is_audited`; Behat `sync_protection.feature` scenario 3 (release through the confirm button, sesskey) |
| IP-P07 | messages without PII, translatable, WCAG, CSRF | T (no PII, en+de keys equal: `consistency_test::test_language_files`; sesskey on release via Behat); **N** (WCAG/axe, screen reader) | - |
| IP-G1 | pre-sync hook exists in 4.5 and 5.3 | T (4.5, 5.3); 5.0-5.2 N | `hook_gate_test` (3), `sync_ordering_test` |
| IP-G2 | sync detection limit (fingerprint) | documented limit; T for the detection | `sync_detector_test` |
| IP-G5 | profile page extension point | T (4.5, 5.3) | `provenance_test::test_core_profile_page_shows_provenance_to_the_owner`; Behat `provenance.feature` |
| AK 1-7 (spec section E) | see the rows above; AK 7 "full CI/Behat/browser matrix 4.5-5.3" | T for 4.5 and 5.3 (PHPUnit, Behat, Chromium); **N** for 5.0-5.2, other browsers, CI | - |

## 4. Component / source / release map (FAM-01)

| Frankenstyle | Install path | Repository | `development` SHA | `main` | Release / version | Moodle | Marketplace | Upgrade / rename scenario |
|---|---|---|---|---|---|---|---|---|
| `local_ltiidentity` | `local/ltiidentity` | `ralferlebach/moodle-local_ltiidentity` | rump `059919a` today; this ZIP after the owner's push (SHA then recorded here) | README/LICENSE only | 0.3.0 / `2026102700` | 4.5-5.3 (`requires 2024100700`, `supported [405, 503]`) | none | fresh install; upgrade from 0.2.0 (`2026102501/02`); rump `2026042200` refused (MIGRATION.md); former name `local_ltiidentityprofile` = other component |

## 5. Needed from the lead / owner

* CI (workflow owner): delete the rump workflow `.github/workflows/moodle-ci.yml` on `development` (listed in
  `db/removed_files.txt`, the stale-files job fails while it exists); the plugin's dev/main workflows were not edited.
* Owner: replace the `development` tree as a whole (not copy-over) so that no rump file survives; decide about the rump's
  documents in `docs/` (MIGRATION.md section 4).
* Lead: `ltifullmatrix local_ltiidentity` (5.0-5.2 and PHP 8.2/8.4 cells), FAM-03 family integration.

---

## History: 0.2.0 evidence (2026-10-25, superseded by the runs above)

Status classes (strictly separated): **code present** (C), **automated tests (local)** (T), **CI passed** (CI),
**functionally accepted** (F, by the owner), **not verified** (N).
**CI = not executed** for everything below (no GitHub access; workflow files are shipped but have never run).
**F = nothing is functionally accepted yet.** "T" always names the tree/database combinations that were run.

### Post-rename cleanup re-run (2026-10-25, version 2026102502 unchanged)

| Run | 4.5 PostgreSQL | 4.5 MariaDB | 5.3 PostgreSQL | 5.3 MariaDB |
|---|---|---|---|---|
| `ltirun ... local_ltiidentity all` (phplint, validate, savepoints, mustache, codechecker 0 warnings, phpdoc 0 warnings, PHPUnit) | OK, 149 tests, 572 assertions | OK, 149 tests, 572 assertions | OK, 149 tests, 572 assertions; 26 PHPUnit-11 deprecations (accepted) | OK, 149 tests, 572 assertions; 26 PHPUnit-11 deprecations (accepted) |
| `ltibehat ... all` (non-JS) | 9 scenarios / 70 steps passed | 9 scenarios / 70 steps passed | 9 scenarios / 70 steps passed | 9 scenarios / 70 steps passed |

* Uninstall: new `db/uninstall.php` removes every user preference of the plugin (core does not); `uninstall_test` (3 tests) green on all four combos. Uninstall leftovers, checked for real: on separate scratch databases (`moodle_upg` 4.5/PostgreSQL and `moodle53_upg` 5.3/PostgreSQL, gate data plus seeded plugin state) all five family plugins were uninstalled with `admin/cli/uninstall_plugins.php --run` and the core tables that can hold plugin state were searched for rows naming the component (config, user preferences, files, scheduled and ad hoc tasks, capabilities and role capabilities, message providers, external functions, events, tables, cache definitions); then reinstalled. Result on both: nothing left except `local_ltixsembed/consumed` in the cache configuration, which core rebuilds from the `db/caches.php` files on disk at the next upgrade (its data is purged by the uninstall). The 10 preferences of this plugin present before the uninstall (gate data plus seeded locks and e-mail state) were all removed; core alone deletes none.
* CI workflows: validated locally (YAML parser, job graph, matrix 4.5 to 5.3 x PostgreSQL/MariaDB, names and referenced paths; see
  TESTING.md "CI workflows"). CI itself: **not executed**.
* Playwright: no behaviour change in this plugin's browser story; not re-run.

### 1. Executed runs (2026-10-25, `ltirun <tree> <db> local_ltiidentity all`; last full run after the rename, fresh install)

| Tree / DB | phplint, validate, savepoints, mustache | codechecker (0 warnings) | phpdoc (0 warnings) | PHPUnit suite |
|---|---|---|---|---|
| Moodle 4.5.15+ / PostgreSQL 16 | OK | OK | OK | **OK, 146 tests, 551 assertions** |
| Moodle 4.5.15+ / MariaDB 11.4 | OK | OK | OK | **OK, 146 tests, 551 assertions** |
| Moodle 5.3 / PostgreSQL 17 | OK | OK | OK | **OK, 146 tests, 551 assertions**; 25 PHPUnit-11 deprecations (doc-comment metadata, accepted by the brief) |
| Moodle 5.3 / MariaDB 11.4 | OK | OK | OK | **OK, 146 tests, 551 assertions**; 25 PHPUnit-11 deprecations |

Moodle 5.0, 5.1, 5.2 are **not installed** in this environment: nothing is claimed for them (N). The test that
carries the evidence for them (`sync_detector_test::test_installed_core_matches_fingerprint`) must be run there.

### Rename and fresh install (FAM-01)

Owner decision 2026-10-25: the plugin is renamed to `local_ltiidentity` (former component name: see CHANGELOG.md and NAMING.md).
There is **no upgrade path** from the 0.1.0 alpha; 0.2.0 is a fresh install and `db/upgrade.php` has no steps. The earlier 0.1.0
ZIP upgrade check and `upgrade_test` were dropped with the upgrade steps; `install_test` checks the no-op upgrade function, the
index and setting defaults of a fresh install, schema = install.xml and the release information. On all four test databases the
old plugin was uninstalled with `admin/cli/uninstall_plugins.php --run` and this plugin installed fresh (section 1).

### Mutation check (IP-01)

The old stack-based detection was temporarily restored in `sync_detector::is_sync()` (4.5/PostgreSQL):
3 tests turned red (`test_fingerprint_positive`, `test_renamed_and_deep_core_caller_is_protected`,
`test_direct_changes_are_not_blocked`); the fingerprint implementation was restored and the suite is green again
(136/509 at that time). The test `test_renamed_and_deep_core_caller_is_protected` is the one that fails with the old code.

### Other layers

* Behat (non-JS): see section 2 (re-run after the rename on all four combinations: 9 scenarios / 70 steps passed).
* Family run (`ltifamily`, all five suites in one PHPUnit run) after the rename: 1369 tests OK on all four combinations.
* Playwright: see section 2.

### 2. Test inventory (PHPUnit, 149 tests incl. data-provider cases; 103 test methods)

| Layer | Methods | Files |
|---|---|---|
| Unit | 33 | `policy_test` (3, 29 cases), `placeholder_test` (4), `lock_manager_test` (2), `hook_callbacks_test` (4), `sync_detector_test` (11: fingerprint, negative controls, status check, event, cost bound), `install_test` (4), `uninstall_test` (3: uninstall hook removes every plugin preference and nothing else; list equals privacy metadata) |
| Gate evidence | 3 | `hook_gate_test` |
| Integration with real core flows | 67 | `merge_handler_test` (9, tool_mergeusers hook stand-in + real launches), `lti_launch_test` (9, real `auth_plugin_lti::complete_login()` / NRPS member sync), `email_verifier_test` (17, real mail sink, real password-reset function), `identity_cases_test` (13: duplicates, concurrency with a second lock holder, rollback with a failing write, purge task, no clear data), `profile_service_test` (10), `navigation_test` (5), `privacy/provider_test` (6) |
| Behat (non-JS) | 9 scenarios in 3 feature files | **executed 2026-10-25 with `ltibehat`**: 9 scenarios / 70 steps passed on 4.5 pgsql, 4.5 mariadb, 5.3 pgsql, 5.3 mariadb (5.3/mariadb at the third attempt: the first two were broken by another agent's run sharing the Behat port and gherkin cache, not by the plugin). Local, not CI |
| Playwright E2E | 5 tests, 1 file | **executed 2026-10-25 against the renamed 0.2.0 code (fresh install, re-seeded stack), Chromium only, two-site stack (A Moodle 4.5, B Moodle 5.3, PostgreSQL): 5 passed, 0 failed**; not CI, no Firefox/WebKit |

### 3. Requirements and acceptance criteria

In the Status column **T** = automated tests (local), run on all four tree/database combos unless stated; **N** = not verified;
**CI** and **F** are not reached by any row.

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| IP-G1 | pre-sync hook exists in every target version | T (4.5 pgsql+mariadb, 5.3 pgsql+mariadb); 5.0-5.2 N | `hook_gate_test::test_hook_exists_and_callback_registered`, `::test_hook_fires_before_write_and_can_veto_the_sync`, `::test_without_callback_the_launch_overwrites` |
| IP-L01 | existing LTI link unchanged | T | `lti_launch_test::test_two_launches_without_claims_keep_teacher_values` (one binding row, same user id); `auth_lti_linked_login` is never written |
| IP-L02 | placeholders recognisable | T (logic), N (UI rendering in a browser) | `placeholder_test`; list marker in `index.php` / `output\renderer` (Playwright: the placeholder marker and the teacher form are exercised in the browser) |
| IP-L03 | teacher completes alias in own context | T (service/ACL), N (UI) | `profile_service_test::test_teacher_scope`, `::test_save_updates_core_user_locks_audits_and_triggers_event` |
| IP-L04 | self-service | T (service/ACL), N (UI) | `profile_service_test::test_self_service_access`, `::test_self_service_save`, `navigation_test` |
| IP-L05 | e-mail confirmation by one-time link | T (service), N (page with sesskey) | `email_verifier_test::test_request_and_confirm` and others |
| IP-L06 | several accounts with the same e-mail handled by policy | T | `email_verifier_test::test_duplicate_policy`, `::test_duplicate_rechecked_at_confirmation` |
| IP-L07 | local values not overwritten by a relaunch | T | `lti_launch_test::*`, `hook_callbacks_test::test_repeated_syncs_are_idempotent` |
| IP-L08 | GDPR export/deletion | T | `privacy/provider_test` |
| IP-L09 | no account merge here | T | `email_verifier_test::test_same_email_two_identities_no_association_no_reset`; the code never links accounts |
| IP-P01 | deterministic field priority | T | `policy_test` (27 matrix cases), `lti_launch_test::test_launch_with_claims_versus_lock`, `::test_claims_replace_placeholders` |
| IP-P02 | only core fields + `user_preferences`, no PII table | T | `lock_manager_test::test_lock_cycle_uses_core_preferences`; `docs/PRIVACY.md`; the audit table holds no values (`profile_service_test::assert_no_personal_values_in_audit`) |
| IP-P03 | teacher UI shows only authorised LTI users, logged | T (ACL, audit), N (UI) | `profile_service_test` |
| IP-P04 | verification with expiry, anti-replay, no unconfirmed adoption | T | `email_verifier_test::test_token_expires`, `::test_token_is_one_time`, `::test_wrong_token_and_attempt_limit`, `::test_request_and_confirm` (address unchanged until confirmed) |
| IP-P05 | opt-in policy for duplicates, warning, issuer+sub logins unchanged | T | `email_verifier_test::test_duplicate_policy`; warning text `emailduplicatewarning` (display N) |
| IP-P06 | conflicts documented, admin release with audit | T | `lti_launch_test::test_release_lock_lets_claims_through`, `profile_service_test::test_release_lock_requires_capability_and_is_audited`; documented in `docs/DOCUMENTATION.md` |
| IP-P07 | messages without PII, translatable, WCAG, CSRF | T (no PII, lang en+de), **N (WCAG, browser CSRF)** | lang files, `profile_form`; axe/browser checks not run |
| AK 1 | same user, two launches without name/e-mail: entered values stay | T | `lti_launch_test::test_two_launches_without_claims_keep_teacher_values` |
| AK 2 | launch with real claims versus lock, per policy | T | `lti_launch_test::test_launch_with_claims_versus_lock` (all 3 policies x locked/unlocked) |
| AK 3 | duplicate e-mail with two LTI identities: no auto association, password reset checked | T | `email_verifier_test::test_same_email_two_identities_no_association_no_reset` (core sends only the informational message, no reset record) |
| AK 4 | verification link: expiry / one-time / CSRF / host spoofing | T for expiry, one-time, attempts, wrong user, host spoofing; **N for the CSRF/sesskey page flow** | `email_verifier_test`; `confirmemail.php` (POST + `require_sesskey`) not driven by a browser |
| AK 5 | teachers only authorised participants; users only own profile | T | `profile_service_test::test_teacher_scope`, `::test_suspended_and_deleted`, `::test_siteadmin_target_is_protected`, `::test_self_service_access` |
| AK 6 | core profile and third parties read the real `user` values | T | `profile_service_test::test_core_readers_see_the_values` |
| AK 7 | hook availability and full CI/Behat/browser matrix 4.5-5.3 | T for hook + PHPUnit on 4.5 and 5.3; **N for 5.0-5.2, browser, CI**; Behat (non-JS) executed locally | section 1 |
| E2E story | learner from A with minimal claims -> placeholder on B -> teacher enters name -> relaunch keeps it -> participants list | T (Playwright executed 2026-10-25, Chromium, 5/5 passed) | `tests/playwright/identity-profile.spec.js` |
| FAM-01 | release 0.2.0, version 2026102502; renamed, no upgrade path from 0.1.0 alpha; fresh install on PG/MariaDB | C + T (all four combos); old plugin uninstalled and this one installed fresh on all four test databases (section 1) | `version.php`, `db/upgrade.php` (no steps), `install_test`, section 1 |
| IP-01 | auth_lti detection without stack inspection; direct edits not blocked; locked values survive the sync; negative controls | T (4.5 and 5.3, both DBs); **5.0, 5.1, 5.2: N** (not installed) | `sync_detector_test` (renamed + 30-frame-deep caller still protected: red with the old code, see mutation check; direct and form-style edits pass; non-LTI untouched; installed-core fingerprint), `lti_launch_test`, `hook_callbacks_test`; status check `sync_detection` |
| IP-01 limit | a third-party writer using exactly the four properties id, firstname, lastname, email on an LTI account is filtered like the sync | documented limit, not removable without an upstream marker; web service requests and own writes are excluded | `sync_detector_test::test_direct_changes_are_not_blocked` (last block), DOCUMENTATION gate IP-G2 |
| IP-02a | identical e-mail on LTI-created accounts: no automatic merge or association | T | `identity_cases_test::test_same_email_as_local_account_creates_separate_lti_account`, `email_verifier_test::test_same_email_two_identities_no_association_no_reset` |
| IP-02b | no unconfirmed value is "verified" (also after a claim carrying the same address) | T | `identity_cases_test::test_unconfirmed_value_is_never_verified`, `email_verifier_test::test_verification_is_bound_to_the_address` |
| IP-02c | concurrent verification: second holder gets busy, token untouched; one confirmation wins; replay invalid; failed write keeps the link usable | T (lock contention simulated in one process with file locks; true multi-process race not executed) | `identity_cases_test::test_concurrent_confirmation`, `::test_failed_write_keeps_token_usable`, `::test_profile_save_busy`, `::test_locking_helper` |
| IP-02d | expired tokens rejected and purged; address re-validated at confirmation | T | `identity_cases_test::test_expired_token`, `::test_purge_task`, `::test_address_forbidden_after_request` |
| IP-02e | repeated sync keeps the locally chosen priority (every policy, verified e-mail stays verified) | T | `identity_cases_test::test_repeated_sync_keeps_verified_email`, `hook_callbacks_test::test_repeated_syncs_are_idempotent`, `lti_launch_test` |
| IP-02f | no clear data persisted, logged or shown as a name | T (storage, audit, event, list label) ; N (browser rendering of the list label) | `identity_cases_test::test_no_clear_data_persisted_or_logged`, `::test_presentation_hides_placeholders`, `sync_detector_test::test_protected_sync_event` |
| IP-02g | functional E2E of the cases | the existing Playwright story covers relaunch keeping the name; duplicate/concurrency/expiry are **not** covered by a browser test | **executed 2026-10-25 against the 0.2.0 code, Chromium only, two-site stack (A Moodle 4.5, B Moodle 5.3, PostgreSQL): 5 passed, 0 failed**; not CI, no Firefox/WebKit |
| Gate-2 F4 | locks / verification follow a tool_mergeusers merge: B keeps its locks, A's locks move where B has none (real value only), verified only for the same address, A's pending e-mail change cancelled | C + T (all four combos, stand-in of the hook; mutation check: with the transfer disabled 7 of 9 tests red); real merge with tool_mergeusers: T by the Gate 2 re-run after the rename (`/home/claude/gates`, run `rename2-20261025-1834`, real `tool_mergeusers`, all four combinations) (locks of A1 follow to B1) | `merge_handler_test` (incl. `test_registration_without_tool_mergeusers`: no debugging, callback inert without the plugin; `test_without_transfer_claims_overwrite_b` as negative control; `test_transferred_lock_protects_b_against_claims` with real launches) |
| FAM-04 | lock timeouts, privacy export/delete, hook cost per launch, logging | lock timeouts T (`locktimeout`, busy results); privacy T (`provider_test`, token hash not exported); hook cost T (bound: 0 queries for other accounts, <= 3 for an LTI sync); logging T (no values); **N**: load test, multi-process race, accessibility, CI | `identity_cases_test`, `sync_detector_test::test_hook_cost_is_bounded`, `privacy/provider_test` |

### 4. Open points / honest limits

* Gate IP-G1 is positive for 4.5 and 5.3; 5.0-5.2 were not run (the hook class exists in 4.5 already, so they are
  expected to behave the same - not claimed). Run `sync_detector_test` there.
* The sync detection is a fingerprint, not an upstream marker (see gate IP-G2 in `DOCUMENTATION.md`); the layer that
  keeps placeholders from overwriting real values does not depend on it.
* The hook never takes a lock; a teacher save racing with a launch is decided by write order (documented).
* Concurrency was tested with a second lock holder in one process, not with two OS processes.
* The navigation location ("More" menu of the course, preferences page) was verified at the navigation-node level
  (`navigation_test`), not visually.
* Accessibility, CI, `@javascript` Behat, functional acceptance: **N**.
* The rename uninstalled the old plugin on the shared test sites (4.5 and 5.3, both databases); its tables and settings were
  deleted. Its user preferences were not (the 0.1.0 alpha had no uninstall hook); checked afterwards: none of them exist on the
  four test databases (the E2E reset recreates the users). This plugin's own uninstall removes its preferences (section "Post-rename cleanup re-run").

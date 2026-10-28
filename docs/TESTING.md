# Testing

Layers, from atoms to user stories. PHPUnit tests use the PHPUnit 9 style (`@covers` and `@dataProvider` in
docblocks, no attributes) so that they run on Moodle 4.5 (PHPUnit 9.6) and 5.x (PHPUnit 11; deprecations accepted).

## Run

```bash
# whole matrix for one tree/database: mirror, upgrade, static checks, PHPUnit
/home/claude/bin/ltirun <45|53> <pgsql|mariadb> local_ltiidentity all
# only PHPUnit
/home/claude/bin/ltirun 45 pgsql local_ltiidentity phpunit
/home/claude/bin/ltirun 45 pgsql local_ltiidentity phpunit --filter lti_launch_test
# quick local style check without Moodle
/tmp/moodlecs/vendor/bin/phpcs --standard=moodle --extensions=php --ignore=tools .
```

`ltirun ... all` runs: `phplint`, `validate`, `savepoints`, `mustache` (no templates, passes trivially),
`codechecker --max-warnings 0`, `phpdoc --max-warnings 0`, then the suite `local_ltiidentity_testsuite` with
`--fail-on-warning --fail-on-risky --fail-on-skipped`.

Behat (non-JavaScript scenarios run locally, `@javascript` only in CI):

```bash
/home/claude/bin/ltibehat <45|53> <pgsql|mariadb> local_ltiidentity
```

(`ltibehat` shares the Behat port and gherkin cache with other agents' runs; a "connection refused" or
"gherkin cache" failure means a concurrent run, repeat it.) CI: `moodle-plugin-ci behat` is configured but not executed.

Playwright (end-to-end): needs the two-site stack (`/home/claude/e2e/README.md`, stack up and seeded). The stack is shared:
run every Playwright command (and every change to A or B) under the stack lock. Both specs restore what they change on A
and B in `afterAll`. The spec uses the stack library (`E2E_LIB`, default `/home/claude/e2e/lib/e2e`) for login and SQL
helpers; the config bypasses the sandbox proxy for the test hosts.

```bash
cd /home/claude/work/moodle-local_ltiidentity
flock /tmp/e2e_stack.lock env E2E_COMPONENTS=local_ltiidentity /home/claude/e2e/bin/e2e-mirror   # new code on A and B
flock /tmp/e2e_stack.lock env NODE_PATH=/home/claude/e2e/node_modules \
    /home/claude/e2e/node_modules/.bin/playwright test -c tests/playwright/playwright.config.js
```

See ACCEPTANCE for the execution status.

## Test matrix

| Layer | Files | What it proves |
|---|---|---|
| PHPUnit unit | `policy_test`, `placeholder_test`, `lock_manager_test`, `hook_callbacks_test` (apply_priority), `sync_detector_test` (detection, hook cost), `install_test` (fresh install: defaults, schema, release), `consistency_test` (IP-01: component, path, lang, schema, namespaces, package, no rump/old names, removed files absent), `uninstall_test` | decision matrix, placeholder detection, lock storage in preferences, update object mutation, package identity |
| PHPUnit gate evidence | `hook_gate_test` | gate IP-G1: hook exists, is registered, fires before the write; negative control without the callback |
| PHPUnit integration, real core flows | `lti_launch_test`, `claim_scenarios_test` (IP-02: incomplete, contradictory, changing claims, repeated launch, existing accounts; real `auth_plugin_lti::complete_login()`), `sync_ordering_test` (IP-02: no post-write step, protected value never written, negative control), `email_verifier_test`, `recovery_test` (IP-04: pending address vs. core forgot-password, reset tokens, duplicates never linked), `provenance_test` (IP-04: recording, binding to the value, visibility, real profile tree), `capability_matrix_test` (IP-04), `identity_cases_test`, `profile_service_test`, `navigation_test`, `merge_handler_test`, `upgrade_test` (0.2.0 -> 0.3.0 backfill, rump refused), `privacy/provider_test` | the acceptance criteria at the level of Moodle's own flows |
| Behat (non-JS) | `ltiidentity.feature`, `teacher_profile.feature`, `self_service.feature`, `sync_protection.feature` (real auth_lti membership sync: repeated, missing, contradicting, changing claims; lock release), `provenance.feature` (profile page: teacher, learner, peer) | settings, teacher list and form, self-service, ACL, sync protection in the UI, provenance display |
| Playwright E2E (two sites) | `identity-profile.spec.js` (story: placeholder -> teacher entry -> relaunch keeps it), `identity-claims.spec.js` (IP-02/IP-04: incomplete, changing, contradicting, withdrawn claims, repeated launches, existing account with the same e-mail, provenance on B's profile pages) | real LTI 1.3 launches from Moodle 4.5 (A) to Moodle 5.3 (B) |
| Load | none | see `tests/load/README.md` |

Data providers cover the policy matrix (3 policies x locked/unlocked), duplicate policy x core setting, invalid
names and invalid addresses.

Test fixtures: `tests/fixtures/launch_helper.php` (real launches), `before_write_probe.php` + `probe_hooks.php`
(hook ordering probe). `complete_user_login()` raises a session warning in CLI tests; the helper silences it
with `@` exactly like Moodle's own tests.

## CI workflows

Workflows are owned by the CI maintainer this round; this plugin does not edit them. The rump's
`.github/workflows/moodle-ci.yml` (4.5-5.2 only) is listed in `db/removed_files.txt` and must be deleted on `development`.

`.github/workflows/moodle-plugin-ci-dev.yml` (branches except main, reduced matrix 4.5/5.0/5.3) and
`moodle-plugin-ci-main.yml` (main, full matrix 4.5 to 5.3 x PostgreSQL/MariaDB, release artefact, coverage floor, stale
files). None has run (no GitHub access). Checked locally 2026-10-25 with `/home/claude/bin/ltici-check.py` (PyYAML
parse, job graph and `needs`, matrix cells, Behat tag, archive prefix, every referenced plugin path, no clone of plugin
repositories): 0 errors for all five family plugins. No workflow clones another plugin repository (the template's sibling
lockstep job was removed: the family plugins are independent).

# local_ltiidentity - architecture, gates, configuration

Spec: `01_lti_identity_profile.md` (requirements IP-L01..L09, IP-P01..P07, gate IP-G1); family audit 2026-10-27
(items IP-01..IP-04). Scope: exactly what is proven to work without a fork of Moodle core. Release 0.3.0 (`2026102700`).
This implementation replaces the development rump of the same component; see `docs/MIGRATION.md`.

## 1. Architecture

```
enrol/lti/launch.php:104 (launch_deeplink.php:77)       enrol_lti sync_members task (sync_members.php:382/393)
        |  $auth->complete_login(...)                          |  $auth->find_or_create_user_from_membership(...)
        v                                                      v
auth/lti/auth.php  complete_login():142 / :156          find_or_create_user_from_membership():202 / :214
        \______________________  ______________________________/
                               v
auth/lti/auth.php:386  auth_plugin_lti::update_user_account($user, $userdata, $iss)
        |  :389 only auth = 'lti';  :396-404 update = [id, firstname, lastname, email] (placeholders for missing claims)
        |  :405-408 only if something differs:
        |     4.5: user_update_user($update)                    user/lib.php:156
        |     5.3: \core\user::update_user((object) $update)    lib/classes/user.php:1887
        v
   read current row          4.5 user/lib.php:164           5.3 lib/classes/user.php:1890
   dispatch hook             4.5 user/lib.php:167-171       5.3 lib/classes/user.php:1892-1896
        |  \core\hook\manager::dispatch() calls the callbacks synchronously, highest priority first
        |  (lib/classes/hook/manager.php:281-327, call_user_func at :323, identical in 4.5 and 5.3)
        v
   local_ltiidentity\hook_callbacks::before_user_updated()     (db/hooks.php, priority -100)
        |  not auth=lti or own write -> return (no query)
        |  sync = sync_detector::is_sync()  (fingerprint id/firstname/lastname/email, no call stack)
        |  apply_priority(): per field policy::decide() -> KEEP: unset($hook->user->field)
        |  record_provenance(): "lti" / "other" for values that will really be written
        v
   validate/clean            4.5 user/lib.php:210-216       5.3 lib/classes/user.php:1924-1930
   write changed fields      4.5 user/lib.php:218-233       5.3 lib/classes/user.php:1932-1946
   (5.3 only: reset tokens deleted when the e-mail changed, lib/classes/user.php:1947-1950)
   user_updated event        4.5 user/lib.php:252-254       5.3 lib/classes/user.php:1967-1969
```

* The only place the plugin acts on a launch is the hook callback, **before** core writes. It mutates the update object
  it receives; it never writes the user record, registers **no event observer** (there is no `db/events.php`), queues no
  task and runs nothing after the write. There is therefore no later correction step that could race with the launch
  (`sync_ordering_test`, see 1b).
* In 5.3, `user_update_user()` is deprecated (`lib/deprecatedlib.php:752`, MDL-82650) and forwards to
  `\core\user::update_user()`; auth_lti calls the latter directly. The hook class
  `user/classes/hook/before_user_updated.php` is byte-identical in 4.5 and 5.3 (`public readonly stdClass $user`, a mutable
  object, and `$currentuserdata`).
* Teacher page, self-service page and e-mail confirmation write through `local\user_writer` (`\core_user::update_user()`
  where present, `user_update_user()` on 4.5). While it runs, the hook stands aside (`user_writer::is_active()`).

### 1a. Negative cases of the launch sync (IP-02)

| Case | auth_lti sends | Result (policy "claims win", default) | Evidence |
|---|---|---|---|
| Incomplete claims | real value for some fields, placeholder for the missing ones | transmitted field applied by policy; missing field never resets a local value | `claim_scenarios_test::test_incomplete_claims` (all policies), Playwright test 1 |
| Empty / blank claim | `''` or white space (the `??` in auth.php:401-402 only replaces null) | counts as missing: never replaces a non-empty value | `::test_contradictory_claims_do_not_reset_real_values` (empty strings, white space only), `policy_test` |
| Invalid e-mail claim | the address unchanged (`core_user::clean_field(..., 'email')` does not reject invalid addresses) | counts as missing in the sync: never replaces a non-empty address (new in 0.3.0) | `::test_contradictory_claims_do_not_reset_real_values` (invalid e-mail address; red before the fix) |
| Claim equal to the placeholder | e.g. `given_name = sub` | treated as placeholder | same test, last case |
| Claim contradicting a lock | another real name | lock wins under every policy | `::test_lock_wins_against_contradicting_claims`, Behat `sync_protection.feature`, Playwright test 4 |
| Changing claims | Alpha, Beta, none, (teacher lock), Gamma | follows the policy table (section 1c), then the lock | `::test_changing_claims` (3 policies), Playwright test 2 |
| Claims withdrawn | placeholders again | nothing is reset | Playwright test 5, Behat |
| Repeated launch | same as stored / placeholders over protected values | no write to the user row at all (`timemodified` unchanged), one binding, same account | `::test_repeated_launch_writes_nothing`, `::test_repeated_launch_with_same_claims_is_idempotent`, Playwright test 6 |
| Existing LTI account | values from an earlier claim | kept against later launches without claims | `::test_existing_lti_account_keeps_claimed_values` |
| Existing manual account, same e-mail | (new platform user) | auth_lti creates a separate account; the manual account is untouched and not bound | `::test_existing_manual_account_with_same_email_is_not_touched`, Playwright test 7 |
| Existing non-LTI account bound by core ("link existing account") | claims | core does not sync (auth.php:389), the plugin does nothing | `::test_existing_bound_manual_account_is_left_to_core` |

### 1b. No later correction race (IP-02)

* Proven by `sync_ordering_test`: (1) no observer, no ad hoc task, no hook after the write is registered by the plugin;
  (2) during a real `complete_login()`, a probe callback with a lower priority (it runs after the plugin, directly before
  core's write) sees an update object without the locked fields, and a probe on core's `user_updated` event (triggered after
  the write) finds the protected values in the database: the protected value is never written, not even for a moment;
  exactly one user update per launch; (3) negative control without the plugin: the same probes see the placeholder written.
* The hook reads the lock live (not from the row core read before dispatching): a teacher save that completes between
  core's read and the hook decision is respected (`::test_lock_set_after_core_read_is_respected`).
* Honest boundary: the hook takes no lock (a launch never waits). If a teacher save of an **unlocked** field and a launch
  with a **real** claim for that field run truly in parallel and the teacher's write lands between the hook's decision and
  core's write of the same request (a window without I/O), the claim is stored although the teacher's lock now exists. The
  lock stays, the next launch does not change the field, and the provenance shows "not recorded" for the value (the record
  describes the teacher's value), so the state is visible and corrected by saving again. Not executed with two OS processes.

### 1c. Field priority (IP-P01)

1. a **locked** value (set through this plugin; provenance teacher / self / verified) is never replaced by a launch;
2. a **real claim** (not equal to the placeholder of that identity, not blank, for e-mail a valid address) is applied
   according to the admin policy when the field is not locked;
3. a **non-empty local value** is never replaced by a missing claim / placeholder / blank value / invalid address;
4. **placeholders** are only written into empty fields (first provisioning).

| Policy (`claimpriority`) | unlocked local real value + real claim | unlocked local placeholder + real claim |
|---|---|---|
| `claimswin` (default) | claim | claim |
| `localwins` | local | local |
| `fillplaceholders` | local | claim |

### Classes

| Class | Task |
|---|---|
| `hook_callbacks` | hook callback; layer 1 placeholder protection for every writer, layer 2 lock/policy for the auth_lti sync; provenance of written values |
| `local\sync_detector` | fingerprint detection of the auth_lti sync (no call stack), web service and own-write exclusion, parser for the installed core source |
| `local\policy` | pure decision function (IP-P01) and the admin policy constants |
| `local\placeholder` | recognises the three auth_lti placeholders from the user's `auth_lti_linked_login` rows (read only) |
| `local\provenance` | field provenance records (source + value hash) and their evaluation, visibility rule |
| `local\lock_manager` | lock flags in `user_preferences` (`local_ltiidentity_lock_<field>` = teacher / self / verified) |
| `local\user_writer` | the single writer of core user fields; marks own writes so the hook stands aside |
| `local\access` | who may edit whom (course capability + enrolled + `auth=lti` + not guest + not site admin unless actor is one) |
| `local\profile_service` | save names, lock, provenance, audit, event; admin lock release |
| `local\email_verifier` | request/confirm e-mail change, token hash, expiry, attempts, duplicate policy, reset-token invalidation |
| `local\locking` | per-account exclusive sections on the core lock factory (timeout setting) |
| `local\audit` | audit table writer |
| `local\merge_handler` | tool_mergeusers integration (finding F4) |
| `local\preferences` | list of all preferences of the plugin (uninstall) |
| `task\purge_stale_data` | scheduled task: expired e-mail requests, audit retention |
| `check\sync_detection` | status check: hook registered, installed core still sends the expected fingerprint |
| `form\profile_form` | Moodle form for teacher and self-service |
| `output\renderer` | course list table |
| `output\profile_provenance` | provenance rows for the profile page and the self-service page |
| `event\*` | `profile_updated`, `lock_released`, `email_verified`, `sync_protected` |
| `privacy\provider` | Privacy API (metadata, export, delete, user preferences) |

Pages: `index.php` (teacher list), `edit.php` (teacher form), `profile.php` (self-service, with the provenance table),
`confirmemail.php` (confirmation, POST + sesskey), `release.php` (admin, POST + sesskey).
Callbacks in `lib.php`: `local_ltiidentity_extend_navigation_course()`, `local_ltiidentity_extend_navigation_user_settings()`,
`local_ltiidentity_myprofile_navigation()` (profile page), `local_ltiidentity_status_checks()`.

## 2. Gates and evidence

| Gate | Question | Result | Evidence |
|---|---|---|---|
| **IP-G1** | Is there a core extension point that fires before the auth_lti sync writes the user? | **Positive**: `\core_user\hook\before_user_updated` exists and is dispatched at the top of `user_update_user()` (4.5) and `\core\user::update_user()` (5.3, where `auth_plugin_lti::update_user_account()` calls it directly). `$hook->user` is a mutable `stdClass`. | `hook_gate_test::test_hook_exists_and_callback_registered`, `::test_hook_fires_before_write_and_can_veto_the_sync` (a probe with higher priority sees the unchanged DB value and the incoming placeholder; the stored value after the sync is the protected one), `::test_without_callback_the_launch_overwrites` (negative control) |
| IP-G2 | Can the callback tell an auth_lti sync from every other update? | **Limited, documented, and no longer stack based.** Verified in the sources of 4.5 and 5.3: core passes no marker, no flag and no event; the only reliable property of the sync is the shape of its update object - exactly `id`, `firstname`, `lastname`, `email` for an `auth=lti` account (identical in both trees). That fingerprint selects layer 2 (locks and claim priority). Layer 1 (an auth_lti placeholder never replaces a real value) does not depend on it and protects against every caller. **Limit:** a third-party caller that updates an LTI account with exactly these four properties is indistinguishable and is filtered like the sync (web service requests and this plugin's own writes are excluded). If core changes the shape, layer 2 stops matching; the status check `sync_detection` then turns to warning and `sync_detector_test::test_installed_core_matches_fingerprint` fails. Moodle 5.0-5.2 are not installed here: the same test must be run there; not claimed. An upstream marker (for example a flag on the hook) would remove the limit; none exists. | `sync_detector_test` (fingerprint positive/negative, renamed and 30-frame-deep caller still protected, direct edits not blocked, placeholder layer, installed-core fingerprint, status check), `hook_callbacks_test`, `lti_launch_test::test_regular_updates_are_not_blocked`, `::test_membership_sync_is_protected` |
| IP-G5 | Is there a profile-page extension point for the field provenance in 4.5 and 5.3? | **Positive**: `\core_user\output\myprofile\manager::build_tree()` calls every plugin's `<component>_myprofile_navigation()` (`user/classes/output/myprofile/manager.php:72`, identical in both trees). | `provenance_test::test_core_profile_page_shows_provenance_to_the_owner`, Behat `provenance.feature`, Playwright `identity-claims.spec.js` |
| IP-G3 | Are the navigation callbacks still supported in 4.5 and 5.3? | **Positive**: `get_plugins_with_function('extend_navigation_course' / 'extend_navigation_user_settings')` is still used by `settings_navigation` in both trees (source-checked in both) and by the real settings navigation in the tests. The newer hook `extend_user_menu` was not needed. | `navigation_test` |
| IP-G4 | Does duplicate-address handling interact correctly with `$CFG->allowaccountssameemail`? | **Positive**: the effective policy is never weaker than core. | `email_verifier_test::test_duplicate_policy` |

Not a gate but worth knowing: when two LTI identities share an address, core `core_login_process_password_reset()`
sends only the informational "password change info" message for `auth=lti` users and creates no reset record
(`email_verifier_test::test_same_email_two_identities_no_association_no_reset`). The plugin never derives an
account link from name or e-mail (IP-P05, IP-L09).

## 3. Data model

* Names and e-mail: core `user` table only.
* `user_preferences` (core):
  * `local_ltiidentity_lock_firstname|lastname|email` - lock with provenance of the locked value (teacher / self / verified);
  * `local_ltiidentity_src_firstname|lastname|email` - provenance of the current value: `<source>:<sha256 of the value>`,
    source = `lti`, `teacher`, `self`, `verified`, `other` (0.3.0);
  * `local_ltiidentity_emailpending` - JSON: requested address, SHA-256 of the token, expiry, created, attempts, requester;
  * `local_ltiidentity_emailverified` - SHA-256 of the verified address (void as soon as the stored address differs).
* Table `local_ltiidentity_audit`: actor, target user, context, action (`update`, `release`, `emailrequest`, `emailverify`,
  `mergeinto`, `mergecancel`), field **names**, lock state before/after, time. No names, no addresses.
* Uninstall (`db/uninstall.php`): Moodle deletes the table, settings, capabilities and the scheduled task itself, but not
  user preferences; `xmldb_local_ltiidentity_uninstall()` deletes every preference listed in `local\preferences::names()`
  (the eight names above, equal to the privacy metadata, `uninstall_test`).

## 4. Configuration (Site administration -> Plugins -> Local plugins -> LTI Identity)

`claimpriority` (`claimswin`|`localwins`|`fillplaceholders`), `emailduplicate` (`allow`|`warn`|`block`, default
`block`), `emailtokenlifetime` (default 86400 s), `teacheremail` (default on), `locktimeout` (default 10 s, 1-60),
`auditretention` (default 730 days, 0 = keep), `logprotected` (default off).

## 4a. Production notes (FAM-04)

* **Hook cost per launch:** no query for non-LTI accounts and own writes; for an LTI account one query for the bindings
  plus cached preferences, and no write when nothing changes (the common relaunch). A claim that really changes a field
  costs one provenance preference write (plus core's reload flag) per field. Bounded by
  `sync_detector_test::test_hook_cost_is_bounded`.
* **Locks:** e-mail request/confirmation use `email_<userid>`, profile saves `profile_<userid>`; the wait is bounded by
  `locktimeout`; on timeout the user sees "busy" and nothing is consumed. The launch hook takes no lock (section 1b).
* **Logging:** no names, addresses, tokens or platform ids are logged. The optional `sync_protected` event carries field
  names only.
* **Privacy:** export and erasure cover audit rows and all preferences; the pending-request export omits the token hash,
  the provenance export omits the value hash. Expired requests and old audit rows are purged by the scheduled task.
* **Database:** only core DML API; the only own index is on the audit table; tested on PostgreSQL and MariaDB.

## 5. E-mail, recovery and duplicates (IP-04)

* **One-time link:** 64 hex characters from `random_bytes(32)`, only its SHA-256 is stored; expiry (`emailtokenlifetime`);
  consumed in the same transaction that changes the address (anti-replay, a second confirmation finds no request); five
  wrong tokens void the request; the logged-in account must be the owner; POST + sesskey on `confirmemail.php`; link built
  from `$CFG->wwwroot` only (host-header spoofing test). Evidence: `email_verifier_test`, `identity_cases_test`.
* **Pending address and recovery:** the address of the account changes only after confirmation; the pending address lives
  in a user preference. Core's forgot-password path `core_login_process_password_reset()` (`login/lib.php:84`, 4.5 and 5.3)
  looks accounts up by `user.email` or username only, so a pending address finds no account, receives no mail and creates no
  reset token - also for an account that can reset its password (auth switched to manual later). Asking by username reaches
  only the stored address. auth_lti accounts cannot reset passwords at all (`can_reset_password()` is false, core sends only
  the "password change info" message). Evidence: `recovery_test`.
* **Reset tokens after a change:** confirming a new address deletes outstanding `user_password_resets` rows of the account
  (5.3 core does this on any e-mail change, 4.5 does not; the plugin does it on both). `recovery_test`.
* **Duplicates:** `emailduplicate` (allow / warn / block, never weaker than `$CFG->allowaccountssameemail`), re-checked at
  confirmation. A duplicate never links, merges or binds accounts (no identity derivation from name or e-mail; account
  linking belongs to `local_ltiaccountmerge`). Evidence: `recovery_test::test_duplicate_is_never_linked`,
  `email_verifier_test::test_duplicate_policy`, `::test_same_email_two_identities_no_association_no_reset`,
  `claim_scenarios_test::test_existing_manual_account_with_same_email_is_not_touched`.
* Claims from the platform are platform assertions: a real e-mail claim equal to another account's address is written by
  core as for any LTI account (the plugin does not block claims by duplicate policy); it still never links accounts.

## 5b. Field provenance (IP-04)

| Shown | Meaning | Recorded by |
|---|---|---|
| LTI platform (transmitted at a launch) | value written by the auth_lti launch or membership sync | hook callback (`lti`) |
| LTI platform: not transmitted (automatic placeholder) | the value is the auth_lti placeholder | derived from the value |
| A teacher | saved on the teacher page | `profile_service` (`teacher`) |
| The user | saved on the self-service page | `profile_service` (`self`) |
| The user, confirmed by e-mail link | address confirmed through the one-time link | `email_verifier` (`verified`) |
| Changed outside this plugin (for example by an administrator) | written by another writer of an LTI account (profile form, upload, web service) | hook callback (`other`) |
| Not recorded: unchanged since the account was created | no record; the value is from account creation (normally the first LTI launch) | - |
| Not recorded for the current value | a record exists but describes another value (changed by a path that records nothing) | - |

"locked against LTI launches" is appended when the field has a lock. Shown on the profile page (category "Origin of LTI
profile data", course profile and site profile) and on the self-service page. Only sources and lock states are shown, never
values. Upgrade from 0.2.0 creates records from the field locks (`db/upgradelib.php`). tool_mergeusers: provenance
records are not transferred (a record of A describes A's value; B's own records stay valid).

## 5c. Capability matrix (IP-04)

| Capability | Context | Risk | manager | editingteacher | teacher | student | user | guest |
|---|---|---|---|---|---|---|---|---|
| `local/ltiidentity:manage` | course | personal | allow | allow | - | - | - | - |
| `local/ltiidentity:editown` | system | personal | - | - | - | - | allow | - |
| `local/ltiidentity:releaselock` | system | personal | allow | - | - | - | - | - |
| `local/ltiidentity:viewprovenance` | system | personal | allow | - | - | - | - | - |

| Action | Rule (all checked on every request) | Page / API |
|---|---|---|
| list / edit LTI users of a course | `:manage` in the course, target enrolled, `auth=lti`, not deleted/guest, not a site admin unless the actor is one | `index.php`, `edit.php`, `access::can_manage_user()` |
| complete own profile | `:editown` (system), own account is `auth=lti` | `profile.php`, `access::can_edit_own()` |
| confirm an e-mail change | logged in as the account owner, POST + sesskey | `confirmemail.php`, `email_verifier::confirm()` |
| release a lock | `:releaselock` (system), POST + sesskey | `release.php`, `profile_service::release_lock()` |
| see field provenance | the owner; `:viewprovenance` (system); or a teacher allowed to edit the user, on the course profile of that course | profile page, `provenance::can_view()` |

Evidence: `capability_matrix_test` (definitions, archetype defaults for eight roles, action matrix for seven actors,
prohibit/allow overrides honoured), `profile_service_test`, `provenance_test::test_visibility`, Behat (`teacher_profile`,
`self_service`, `provenance`), Playwright (learner cannot open the teacher pages).

## 5d. Account merges with tool_mergeusers (Gate-2 finding F4)

tool_mergeusers excludes `user_preferences` from its generic processing, so before this fix the locks and the
e-mail verification of the merged-away account A stayed on A and the surviving account B was unprotected (a later
LTI launch could overwrite names a teacher had locked on A). The plugin registers a callback for the optional hook
`\tool_mergeusers\hook\after_merged_all_tables` (`db/hooks.php`). It runs inside tool_mergeusers' merge
transaction, so the transfer commits or rolls back with the merge. Rules (spec: no identity derivation, no copying
of profile values, Plugin 3 owns merging):

| Situation | Result on B |
|---|---|
| B has its own lock for a field | kept unchanged |
| A has a lock, B has none, B's value is real | A's lock (with provenance) is set on B; B's value is not changed |
| A has a lock, B's value is empty or an auth_lti placeholder | not transferred (a lock would block a later real claim) |
| A's e-mail lock is "verified" | transferred only if B's current address is the address A verified (same hash); then the verification flag follows as well |
| A has a pending e-mail change | cancelled on A (link invalid), never transferred; B's own pending change untouched |
| A's preferences and audit rows | stay on A (history); audit row `mergeinto` on B, `mergecancel` on A |

Without tool_mergeusers the hook class does not exist; core stores the class name from `db/hooks.php` as a string,
never autoloads it and never dispatches it (verified in `lib/classes/hook/manager.php` of 4.5 and 5.3; the admin
hook overview also tolerates unknown classes). The callback never adds an error message, so it cannot fail a merge on
its own; a database error inside it rolls the merge back, as tool_mergeusers intends. Evidence class: automated tests
(local) with a stand-in of the hook; the real merge is re-run by the lead in Gate 2 (tool_mergeusers is not installed
in the shared trees). Requires a tool_mergeusers version that has this hook (the 2025 hook API); older versions do not
call it and the F4 behaviour remains.

## 6. Known limits / out of scope

* Profile picture sync of auth_lti is not touched; changing `username` is never done.
* The hook protects fields for `auth=lti` users only.
* No merge/link of accounts (belongs to `local_ltiaccountmerge`).
* Group/role/enrolment logic is untouched.
* The sync detection is a fingerprint, not an upstream marker (gate IP-G2); the placeholder layer does not depend on it.
* Concurrency boundary of section 1b.

## 7. Installation, replacement of the rump, upgrade

* Fresh install: `local/ltiidentity`, then the normal Moodle upgrade.
* Upgrade from 0.2.0 (`2026102501`/`2026102502`): step `2026102700` creates provenance records from the field locks.
* The development rump (0.1.0, `2026042200`) is replaced without an upgrade path; an installed rump makes the upgrade stop
  with `upgradefromrump`. Procedure and the list of rump-only files: `docs/MIGRATION.md`.
* The 0.1.0 alpha ZIP under the former component name `local_ltiidentityprofile` is a different component: uninstall it first
  (CHANGELOG 0.2.0).

# Security notes

| Topic | Control | Evidence |
|---|---|---|
| Authorisation, teacher | `require_capability('local/ltiidentity:manage', course context)` on every page load AND `access::can_manage_user()` (enrolled in that course, `auth=lti`, not deleted, not guest, site admins only for admins) | `profile_service_test::test_teacher_scope`, `::test_suspended_and_deleted`, `::test_siteadmin_target_is_protected` |
| Authorisation, self-service | capability `:editown` (system) + own account + `auth=lti` | `profile_service_test::test_self_service_access` |
| Authorisation, lock release | `:releaselock` checked in the page and again in `profile_service::release_lock()` | `::test_release_lock_requires_capability_and_is_audited` |
| Authorisation, provenance | owner; `:viewprovenance` (system); or a teacher who may edit the user (`access::can_manage_user()`) on the course profile of that course; LTI accounts only; sources only, no values | `provenance_test::test_visibility`, `capability_matrix_test`, Behat `provenance.feature` |
| Capability matrix | four capabilities, all `RISK_PERSONAL`; checks follow capabilities (overrides honoured), not role names | `capability_matrix_test` (definitions, archetype defaults, action matrix, prohibit/allow overrides) |
| CSRF | forms through the Form API (sesskey); `confirmemail.php` and `release.php` change state only on POST with `require_sesskey()` (via `single_button`) | code; lock release through the confirm button executed in Behat `sync_protection.feature` (non-JS) |
| Account recovery | a pending address is never in `user.email`, so core's forgot-password lookup (`core_login_process_password_reset()`, login/lib.php:84 in 4.5 and 5.3) cannot reach it; confirming a change deletes outstanding reset tokens (4.5 and 5.3) | `recovery_test` |
| Contradictory claims | blank claims and invalid e-mail claims count as missing in the launch sync and never replace a non-empty value | `claim_scenarios_test::test_contradictory_claims_do_not_reset_real_values`, `policy_test` |
| E-mail takeover | address applied only after confirmation by the logged-in owner (one-time link, replay finds no request); token = 32 random bytes, only SHA-256 stored, `hash_equals`, expiry, 5 attempts, request consumed before the change, 60 s cooldown, new request kills the old one | `email_verifier_test` |
| Host header spoofing | link built through `moodle_url` from `$CFG->wwwroot`, never from `$_SERVER` | `email_verifier_test::test_link_ignores_spoofed_host_headers` |
| Enumeration / PII in messages | duplicate and invalid addresses give generic messages; events and audit contain no names/addresses; no logging of tokens or addresses | `profile_service_test`, form validation |
| Mail injection | address validated by `validate_email()` and `core_user::clean_field`, newline payloads rejected | `email_verifier_test::test_invalid_addresses` |
| Input handling | `required_param`/`optional_param` with PARAM types; names cleaned with `PARAM_TEXT`, max 100 chars | code |
| Concurrency | per-account lock around confirmation/request/save; confirmation is one transaction; second confirmation finds no request | `identity_cases_test::test_concurrent_confirmation`, `::test_failed_write_keeps_token_usable`, `::test_profile_save_busy` |
| Expired / stale data | expired requests rejected and purged by task; audit retention | `identity_cases_test::test_expired_token`, `::test_purge_task` |
| Identity | nothing is derived from name or e-mail; auth_lti's issuer+sub binding is not modified | `email_verifier_test::test_same_email_two_identities_no_association_no_reset` |
| Hook | the callback only removes fields from the update object, never adds or reads secrets; it acts only for `auth=lti` accounts; locks and policy only for updates with the auth_lti sync fingerprint (no stack inspection), placeholders never overwrite real values for any writer | `sync_detector_test`, `hook_callbacks_test` |

Residual risks: the sync detection depends on the shape of the core update object (status check and test warn on change; a third-party caller with exactly the same four properties is filtered like the sync); the confirmation
link is a bearer secret in an e-mail (mitigated by the logged-in-owner requirement); no brute-force protection
beyond the attempt counter and cooldown.

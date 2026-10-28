# Privacy

## Data inventory

| Data | Where | Why | Personal? |
|---|---|---|---|
| first name, last name, e-mail | core `user` table (written through `user_update_user`) | the purpose of the plugin | yes, core data, not duplicated |
| lock flag per field (with the provenance of the locked value) | `user_preferences` (`local_ltiidentity_lock_*`) | protect entered values | pseudonymous flag |
| provenance of the current value per field: source (`lti`, `teacher`, `self`, `verified`, `other`) and SHA-256 of the value it describes (0.3.0) | `user_preferences` (`local_ltiidentity_src_*`) | show where a value came from (IP-04) | source: yes (about the user); hash of a value that is stored in `user` anyway; no value is duplicated |
| pending e-mail change (address, token **hash**, expiry, counters, requesting user id) | `user_preferences` (`local_ltiidentity_emailpending`) | confirmation by link | yes (requested address) |
| hash of the verified address | `user_preferences` (`local_ltiidentity_emailverified`) | show "verified" | hash of an address |
| audit row (actions `update`, `release`, `emailrequest`, `emailverify`, `mergeinto`, `mergecancel`) | `local_ltiidentity_audit` | accountability (who changed which fields of whom) | user ids, field **names**, flags, time; **no names or addresses** |

No tokens or addresses are logged. The confirmation e-mail contains the link only.

## Privacy API

`classes/privacy/provider.php` implements metadata, `get_contexts_for_userid`, `get_users_in_context`, export
(audit entries as data, the preferences as user preferences), deletion for a context, for a user and for a user
list. Deleting a user deletes the audit rows about them and their preferences; audit rows where they only acted
are anonymised (`actorid = 0`). The export of the pending e-mail request contains the requested address and the expiry, never the token hash; the
export of a provenance record contains its source, never the value hash. Tests: `tests/privacy/provider_test.php`
(including `test_export_and_delete_provenance`), `tests/uninstall_test.php`.

## Retention

Preferences disappear with the user (core `delete_user`) or on release of the pending request. The scheduled task
`purge_stale_data` (daily) deletes expired pending requests and audit rows older than the setting
`auditretention` (default 730 days; 0 keeps them). Audit rows contain no names or addresses.
Uninstalling the plugin deletes all its preferences (locks, provenance, e-mail state) for all users (`db/uninstall.php`)
together with the audit table.

## Visibility of the provenance

Sources and lock states (never values) are shown on the profile page to the user, to teachers who may edit the user in
the course of a course profile and to holders of `local/ltiidentity:viewprovenance` (manager by default)
(`provenance_test::test_visibility`, `capability_matrix_test::test_action_matrix`).

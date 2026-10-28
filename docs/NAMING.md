# Naming - alternatives and recommendation

## Decision (owner, 2026-10-25): `local_ltiidentity`

The owner renamed the plugin from `local_ltiidentityprofile` to **`local_ltiidentity`** (install path `local/ltiidentity`), effective with release 0.2.0.
Shorter and family-consistent; "profile" was dropped because the plugin protects the identity data of LTI accounts as a whole (sync protection, locks, e-mail verification), not only a profile page.

* Checked against the 4.5 and 5.3 sources: valid Frankenstyle name (lower-case letters and digits, starts with a letter, no
  underscore in the name part); table `local_ltiidentity_audit` (23 characters). The table-name limit is `xmldb_table::NAME_MAX_LENGTH = 63 - PREFIX_MAX_LENGTH(10) = 53`
  in both trees; the historical 28-character limit no longer applies to Moodle 4.5+ and is quoted below only as history.
* No upgrade path: Moodle cannot upgrade one component into another, so 0.2.0 is a fresh install; a 0.1.0 alpha site uninstalls
  `local_ltiidentityprofile` first (see README, Installation, and CHANGELOG 0.2.0).
* Everything below this section is the earlier analysis (history), written while the name was provisional; its recommendation
  is superseded by this decision.

## History: earlier analysis

The name `local_ltiidentityprofile` is provisional. This note compares alternatives against the Moodle rules
(verified in the 4.5 and 5.3 sources).

## Rules that apply

* **Characters:** `core_component::is_valid_plugin_name()` accepts `^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$` for
  non-`mod` types: lower case letters and digits, must start with a letter; underscores are technically valid but
  make component normalisation ambiguous, so none is used.
* **Length:** `config_plugins.plugin` is `char(100)`, capabilities `<component>:<name>` and event class names have
  generous limits. Practical limits are table and preference names:
  * XMLDB table name <= 53 characters without the site prefix (`xmldb_table::NAME_MAX_LENGTH = 63 - 10`); the own
    table `local_ltiidentityprofile_audit` has 30. (The old limit of 28 characters no longer exists in 4.5/5.3; all
    alternatives below would also stay far below 53.)
  * `user_preferences.name` is `char(255)`; the longest key is `local_ltiidentityprofile_lock_firstname` (39).
* **Type fit:** `local_` is the place for plugins that add hook callbacks, pages and capabilities without being a
  module, block, auth or enrolment method. `auth_` would define a login method (users stay `auth=lti`), `tool_` is
  for site-administrator tools (this plugin serves teachers and users), `report_`/`block_` are display-only.
* **Family consistency:** siblings are `ltisource_scopedidentity`, `local_ltiaccountreconcile`,
  `local_lticrossembed`, `tool_ltidiagnostics`; the common `lti` prefix groups them in the plugin list.

## Alternatives

| # | Name | Chars | Pros | Cons |
|---|---|---|---|---|
| 1 | `local_ltiidentityprofile` | 24 | describes both halves (identity data + profile pages); sorts with the family; unambiguous in the plugin directory | longest candidate; "identity" is also used by the sibling `ltisource_scopedidentity` (different side, different meaning) |
| 2 | `local_ltiprofileguard` | 21 | stresses the core function (protecting entered data), short | understates the UI (teacher/self-service forms, e-mail verification); "guard" suggests security enforcement |
| 3 | `local_ltiuserprofile` | 20 | short, plain | collides conceptually with Moodle's own user profile, poor search precision, does not say that it is about LTI placeholders |
| 4 | `local_lticompleteprofile` | 24 | says what teachers do (complete the profile) | grammatical ambiguity (adjective/verb); says nothing about the sync protection |
| 5 | `local_ltinamekeeper` | 19 | memorable | covers names only, not e-mail; informal |
| 6 | `auth_ltiprofile` | 15 | would sit next to `auth_lti` | **type misfit**: an auth plugin implies a login method and appears in "Manage authentication"; would invite users to switch `auth` |
| 7 | `tool_ltiprofile` | 15 | short | **type misfit**: admin tools live under Site administration; the main users are teachers in a course |

## Recommendation

Keep **`local_ltiidentityprofile`** (1). The type is right (hook callback + course/user pages + capabilities), it
fits the family naming and it is precise enough for search; the extra length costs nothing technically (30-character
table name, 39-character longest preference key). If a shorter name is wanted before the first public release,
`local_lticompleteprofile` is the best alternative; the rename would touch the component name in every file, the
language file names, the capability prefixes, the table name and the preference keys, and requires an upgrade path
for the existing `user_preferences` rows - so it should be decided before the first release, not after.

moodle-local_ltiidentity
===============================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-local_ltiidentity/actions/workflows/moodle-plugin-ci-main.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-local_ltiidentity/actions?query=workflow%3A%22Moodle+Plugin+CI+Main%22+branch%3Amain)

Keeps names and e-mail addresses of LTI 1.3 users complete and correct on the **tool (provider) side**:
when a platform does not send name or e-mail claims, `auth_lti` fills the Moodle user with
placeholders on every launch. This plugin protects locally entered values against that overwrite and
gives teachers and the users themselves a form to enter them - in the normal Moodle `user` record.

Status: **alpha** (release 0.3.0, version `2026102700`). See `docs/ACCEPTANCE.md` for what is proven and what is not.

Branches: `development` is the work branch, `main` the release branch (see "Branch and release policy").


Requirements
------------

Moodle 4.5+ (tested on 4.5 and 5.3), PHP 8.1+. Needs `auth_lti` (part of core) to be useful; it has
no dependency on any other plugin.


Motivation for this plugin
--------------------------

`auth_plugin_lti::update_user_account()` runs on every launch and on every NRPS membership sync. If the
platform sends no `given_name`, `family_name` or `email`, it writes `firstname = sub`,
`lastname = issuer` and `email = enrol_lti_13_<sha1>@example.com`. Anything a teacher typed in is lost
at the next launch. There is no setting in core to prevent that.

This plugin uses the core hook `\core_user\hook\before_user_updated`, which core dispatches synchronously *before*
the write (`user_update_user()` in Moodle 4.5, `\core\user::update_user()` in 5.3 - proven by `tests/hook_gate_test.php`
and `tests/sync_ordering_test.php`), to remove protected fields from the update object of the auth_lti launch / membership
sync. No fork, no observer, no correction after the fact, no second copy of personal data. The exact control flow with
file and line numbers for both versions is in `docs/DOCUMENTATION.md`, section 1.


Installation
------------

Install the plugin like any other plugin to folder

    local/ltiidentity

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

**Replaces the development rump - no upgrade path.** Until 2026-10-27 the `development` branch of this repository held
an early rump under the same component name (release 0.1.0, version `2026042200`) with an incompatible database structure
and a post-login observer. This implementation replaces it; there is no upgrade path from it. If the rump is installed on a
site, the upgrade stops with an explanation: put the rump's code back, uninstall it, then install this release as a new
plugin. When updating a working copy, replace the plugin directory as a whole: files of the rump that survive a copy-over
are listed in `db/removed_files.txt`. Details: [docs/MIGRATION.md](docs/MIGRATION.md).

**Upgrade from 0.2.0** (`2026102501`, `2026102502`) is supported: the upgrade creates the field provenance records from the
existing field locks.

**Renamed in 0.2.0.** The 0.1.0 alpha ZIP was published under the component name `local_ltiidentityprofile` (see
[docs/CHANGELOG.md](docs/CHANGELOG.md)); Moodle treats that as a different plugin: uninstall it first, then install this one.


Branch and release policy
-------------------------

* `development` is the work branch: development, patches, refactoring and test additions happen there only, and every
  acceptance statement in `docs/ACCEPTANCE.md` refers to an exact `development` commit and its CI run.
* `main` is the release branch. It receives changes only through a pull request from `development`, after functional
  acceptance by the owner and a green development CI on the same commit; afterwards the full release CI runs on the exact
  `main` commit. No work is committed to `main` directly.
* Each plugin of the LTI family has its own semantic version; there is no family lockstep.
* Local evidence (developer runs) and the GitHub CI are recorded separately; a skipped or not executed test is never
  counted as passed.


Usage & Settings
----------------

Site administration -> Plugins -> Local plugins -> LTI Identity:

* **Priority of LTI claims** - what happens when a launch transmits a *real* name/e-mail for a field
  that is not locked: *claims win unless locked* (default), *existing local values always win*, or
  *claims only replace placeholders*. Locked fields are never overwritten, and missing claims
  (placeholders) never overwrite an existing value, under any policy.
* **Duplicate e-mail addresses** - *allow*, *warn* or *block* (default) when a requested address is
  used by another account. Moodle's own "Allow accounts with same email" can only make this stricter.
* **Confirmation link lifetime** - validity of the one-time e-mail link (default 24 hours).
* **Teachers may request e-mail changes** - on by default.

Teachers (capability `local/ltiidentity:manage`) open *More -> LTI users* in a course: a list of
the LTI users enrolled there, with markers for placeholder values, locked fields, e-mail status and a
*Complete profile* form. LTI users open *Preferences -> My LTI profile* (capability `:editown`).
Saving a name locks the field (provenance *teacher* or *self*) so later launches keep it.

An e-mail address is **never applied directly**: a one-time link is sent to the new address; the
account only changes after its owner opened the link while logged in and pressed *Confirm*.

Administrators (capability `:releaselock`) can release a lock from the course list; this is audited.

**Where does a value come from?** The profile page of an LTI user shows, per field, whether first name, last name and
e-mail were transmitted by the LTI platform, are the automatic placeholder, were entered by a teacher or by the user,
were confirmed by e-mail link, or were changed elsewhere (for example by an administrator), and whether the field is
locked. Only sources are shown, never values. Visible to the user, to the teachers who may edit the user (course
profile) and to managers (capability `:viewprovenance`).


Capabilities
------------

* **local/ltiidentity:manage** (course, `RISK_PERSONAL`) - complete the profile of LTI users enrolled in the course. Editing teacher and manager.
* **local/ltiidentity:editown** (system, `RISK_PERSONAL`) - complete the own LTI profile. Authenticated user.
* **local/ltiidentity:releaselock** (system, `RISK_PERSONAL`) - release a local lock. Manager.
* **local/ltiidentity:viewprovenance** (system, `RISK_PERSONAL`) - see the origin of the profile fields of any LTI user. Manager.

The full capability and action matrix is in `docs/DOCUMENTATION.md`, section 5c.


Scheduled Tasks
---------------

* `\local_ltiidentity\task\purge_stale_data` (daily, 03:17): deletes expired e-mail confirmation requests and audit rows
  older than the setting "Audit retention" (default 730 days, 0 keeps them).


How this plugin works / Pitfalls
--------------------------------

* Only the core `user` table is used for names and e-mail. Lock flags, provenance records and the pending
  e-mail request live in core `user_preferences`. The only own table is a small audit table without
  any names or addresses. See `docs/PRIVACY.md`.
* Core offers no flag that marks the auth_lti sync. The plugin recognises it by the fingerprint of the update
  object (exactly `id`, `firstname`, `lastname`, `email` for an `auth=lti` account), never by the call stack.
  Placeholder values are additionally kept from overwriting real values for every writer. A status check warns if
  a Moodle upgrade changes the fingerprint; see `docs/DOCUMENTATION.md` (gate IP-G2) for the exact limit.
* **Not done on purpose:** no account linking or merging by name or e-mail (that belongs to
  `local_ltiaccountmerge`), no change of `username`, no handling of the profile picture.
* Pseudonymised `sub` values from a consumer are unaffected: this plugin only protects what a human
  entered.


Family of LTI plugins
---------------------

Independently installable plugins with their own versions (no mutual dependencies; they cooperate only through
Moodle events and read-only services):

| Plugin | Side | Purpose |
|---|---|---|
| `local_ltiidentity` (this) | tool/provider | keep and complete names/e-mail of LTI users |
| `ltisource_ltipseudonym` | platform/consumer | scoped (pseudonymous) user identifiers for launches |
| `local_ltiaccountmerge` | tool/provider | first-login account linking / reconciliation |
| `local_ltixsembed` | consumer + provider | cross-site embedding (cookies / OIDC / sessions) |
| `tool_ltidiagnostics` | both | guided health check and diagnostics |
| `local_lticompletionsync` | both | completion synchronisation between platform and tool |

The existing `tool_ltigroupautoenrol` (deployment-based group assignment) is a separate, already
published plugin and not part of this repository. Each plugin works without the others; if one is
missing, the others keep their function.


Theme support
-------------

This plugin acts mostly behind the scenes and uses standard Moodle output; it is developed and tested
on Moodle Core's Boost theme.


Plugin repositories
-------------------

This plugin is not (yet) published in the Moodle plugins repository. Development version:
https://github.com/ralferlebach/moodle-local_ltiidentity


Bug and problem reports / Feature proposals
-------------------------------------------

Please use the issue tracker of the repository above. Feature proposals are welcome as proposals, not requests.


Moodle release support
----------------------

Supported: Moodle 4.5 (LTS) and 5.3 (`$plugin->supported = [405, 503]`). Other 5.x releases are expected to
work but are not claimed until their tests have been run (see `docs/ACCEPTANCE.md`).


Translating this plugin
-----------------------

English and German language packs are shipped; other languages via AMOS (https://lang.moodle.org).


Right-to-left support
---------------------

Not tested with right-to-left languages.


Maintainers
-----------

Ralf Erlebach, Bergische Universität Wuppertal


Copyright
---------

Ralf Erlebach. Individual copyrights are tracked in PHPDoc comments and Git commits. License: GNU GPL v3 or later.

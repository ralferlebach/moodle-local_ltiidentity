<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['auditretention'] = 'Audit retention (days)';
$string['auditretention_desc'] = 'Audit rows older than this are deleted by the scheduled task. 0 keeps them indefinitely.';
$string['cannotmanageuser'] = 'You are not allowed to edit this profile.';
$string['check_syncdetection'] = 'LTI profile protection: launch sync detection';
$string['check_syncdetection_changed'] = 'The update that auth_lti passes to the user API changed in this Moodle version. Locks and claim priority may not be applied to LTI launches until the plugin is updated. Placeholder values are still prevented from overwriting real values.';
$string['check_syncdetection_nohook'] = 'The before_user_updated hook callback of this plugin is not registered. Purge caches; locked profile values are not protected until it is.';
$string['check_syncdetection_ok'] = 'The LTI launch sync matches the fingerprint this plugin protects against.';
$string['check_syncdetection_unknown'] = 'The source of auth_plugin_lti::update_user_account() could not be read; detection cannot be verified.';
$string['claimpriority'] = 'Priority of LTI claims';
$string['claimpriority_claimswin'] = 'Claims win unless the field is locked (default)';
$string['claimpriority_desc'] = 'Decides what happens when an LTI launch transmits a real name or e-mail for an unlocked field. Locked fields are never overwritten, and missing claims never reset existing values.';
$string['claimpriority_fill'] = 'Claims only replace placeholders';
$string['claimpriority_localwins'] = 'Existing local values always win';
$string['confirmemail'] = 'Confirm e-mail address';
$string['confirmemailquestion'] = 'Do you want to confirm the new e-mail address for this account?';
$string['confirmresult_busy'] = 'Another request for this account is being processed. Please try again in a moment.';
$string['confirmresult_confirmed'] = 'The e-mail address was confirmed.';
$string['confirmresult_duplicate'] = 'The e-mail address is no longer available.';
$string['confirmresult_expired'] = 'The link has expired. Please request a new one.';
$string['confirmresult_invalid'] = 'The link is invalid or was already used.';
$string['confirmresult_wronguser'] = 'Please log in as the account the link was requested for.';
$string['editprofile'] = 'Complete profile';
$string['emailduplicate'] = 'Duplicate e-mail addresses';
$string['emailduplicate_allow'] = 'Allow';
$string['emailduplicate_block'] = 'Block (default)';
$string['emailduplicate_desc'] = 'What to do when a requested address is already used by another account. The core setting "Allow accounts with same email" can only make this stricter: if it is off, duplicates are always blocked.';
$string['emailduplicate_warn'] = 'Allow with a warning';
$string['emailduplicatewarning'] = 'Warning: another account uses the same address. Password reset and account recovery may reach the wrong account.';
$string['emailrequested'] = 'A confirmation link was sent to the new e-mail address.';
$string['emailresult_busy'] = 'Another request for this account is being processed. Please try again in a moment.';
$string['emailresult_duplicate'] = 'The e-mail address is not available.';
$string['emailresult_invalid'] = 'The e-mail address was not accepted.';
$string['emailresult_toosoon'] = 'A confirmation link was requested moments ago. Please wait a minute.';
$string['emailresult_unchanged'] = 'The e-mail address is unchanged.';
$string['emailstatus'] = 'E-mail status';
$string['emailstatus_pending'] = 'Confirmation pending';
$string['emailstatus_unverified'] = 'Not verified';
$string['emailstatus_verified'] = 'Verified';
$string['emailtokenlifetime'] = 'Confirmation link lifetime';
$string['emailtokenlifetime_desc'] = 'How long the one-time link for an e-mail change stays valid.';
$string['emailverify_body'] = 'Please confirm this e-mail address for your account at {$a->sitename} by opening the following link while you are logged in to that account: {$a->link} The link can be used once and expires in about {$a->hours} hours. If you did not expect this message, ignore it; nothing will change.';
$string['emailverify_subject'] = '{$a->sitename}: confirm your e-mail address';
$string['errorbusy'] = 'Another change to this profile is being processed. Please try again in a moment.';
$string['erroremailinvalid'] = 'Please enter a valid e-mail address.';
$string['erroremailnotavailable'] = 'This e-mail address is not available.';
$string['errornameempty'] = 'Please enter a value.';
$string['errornametoolong'] = 'The value is too long (maximum 100 characters).';
$string['errorplaceholdervalue'] = 'This is the automatic placeholder. Please enter a real name.';
$string['event_emailverified'] = 'LTI profile e-mail verified';
$string['event_lockreleased'] = 'LTI profile lock released';
$string['event_profileupdated'] = 'LTI profile fields updated';
$string['event_syncprotected'] = 'LTI sync filtered to protect profile values';
$string['lockedfields'] = 'Locally locked fields';
$string['lockexplanation'] = 'Saved names are locked: later LTI launches do not overwrite them (unless the site policy allows otherwise and the value is not locked).';
$string['lockreleased'] = 'The lock was released.';
$string['locktimeout'] = 'Lock timeout (seconds)';
$string['locktimeout_desc'] = 'How long an e-mail request, confirmation or profile save waits for a concurrent request of the same account (1 to 60). Launches never wait.';
$string['logprotected'] = 'Log protected syncs';
$string['logprotected_desc'] = 'Write an event (field names only, no values) whenever an LTI launch or placeholder write was filtered. Off by default because it creates a log row per protected launch.';
$string['ltiidentity:editown'] = 'Complete own LTI profile';
$string['ltiidentity:manage'] = 'Complete the profile of LTI users in the course';
$string['ltiidentity:releaselock'] = 'Release local profile locks';
$string['ltiidentity:viewprovenance'] = 'View the origin of LTI profile data';
$string['ltiusers'] = 'LTI users';
$string['myltiprofile'] = 'My LTI profile';
$string['nameplaceholder'] = 'Name not provided by the platform ({$a})';
$string['newemail'] = 'New e-mail address (optional)';
$string['newemail_help'] = 'The address is only used after the owner of the account confirmed the link that is sent to it. Until then the current address stays unchanged.';
$string['nousers'] = 'There are no LTI users in this course that you may edit.';
$string['placeholderfields'] = 'Placeholder values';
$string['pluginname'] = 'LTI Identity';
$string['privacy:audit'] = 'Profile changes (audit)';
$string['privacy:metadata:audit'] = 'Audit trail of profile changes made through this plugin. Contains field names and lock states only.';
$string['privacy:metadata:audit:action'] = 'The kind of change.';
$string['privacy:metadata:audit:actorid'] = 'The user who made the change.';
$string['privacy:metadata:audit:contextid'] = 'The context in which the change was made.';
$string['privacy:metadata:audit:fieldnames'] = 'The names of the fields concerned.';
$string['privacy:metadata:audit:newflag'] = 'The lock state after the change.';
$string['privacy:metadata:audit:oldflag'] = 'The lock state before the change.';
$string['privacy:metadata:audit:targetuserid'] = 'The user whose profile was changed.';
$string['privacy:metadata:audit:timecreated'] = 'The time of the change.';
$string['privacy:metadata:preference:emailpending'] = 'A pending e-mail change: the requested address and the hash of the one-time token.';
$string['privacy:metadata:preference:emailverified'] = 'A hash of the e-mail address that was confirmed through the link.';
$string['privacy:metadata:preference:lock'] = 'Provenance of a locally locked profile field (teacher, self or verified).';
$string['privacy:metadata:preference:provenance'] = 'Origin of the current value of a profile field: the source (LTI platform, teacher, user, confirmed by link, other) and a hash of the value it describes.';
$string['privacy:preferences'] = 'LTI profile settings';
$string['privacy:preferences:emailpending'] = 'Pending e-mail change (address and expiry; the token hash is not exported)';
$string['profilesaved'] = 'The profile was saved.';
$string['provenance'] = 'Origin of LTI profile data';
$string['provenance_initial'] = 'Not recorded: unchanged since the account was created (normally by the first LTI launch)';
$string['provenance_lti'] = 'LTI platform (transmitted at a launch)';
$string['provenance_other'] = 'Changed outside this plugin (for example by an administrator)';
$string['provenance_placeholder'] = 'LTI platform: not transmitted (automatic placeholder)';
$string['provenance_self'] = 'The user';
$string['provenance_teacher'] = 'A teacher';
$string['provenance_unknown'] = 'Not recorded for the current value';
$string['provenance_verified'] = 'The user, confirmed by e-mail link';
$string['provenancefield'] = 'Origin: {$a}';
$string['provenancelocked'] = 'locked against LTI launches';
$string['releaselock'] = 'Release lock';
$string['releaselockquestion'] = 'Release the local lock? The next LTI launch may then overwrite this field according to the site policy.';
$string['source_self'] = 'entered by the user';
$string['source_teacher'] = 'entered by a teacher';
$string['source_verified'] = 'confirmed by e-mail link';
$string['task_purge'] = 'Purge expired e-mail requests and old audit rows';
$string['teacheremail'] = 'Teachers may request e-mail changes';
$string['teacheremail_desc'] = 'If enabled, teachers can enter an e-mail address for an LTI user. The address is only applied after the user confirmed it.';
$string['upgradefromrump'] = 'local_ltiidentity cannot upgrade the installed version {$a}: it is the 0.1.0 rump of this component, which has a different and incompatible database structure. There is no upgrade path. Put the 0.1.0 code back, uninstall the plugin (Site administration > Plugins > Plugins overview), then install this release as a new plugin. See docs/MIGRATION.md.';

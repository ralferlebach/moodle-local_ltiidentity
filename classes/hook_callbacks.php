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

namespace local_ltiidentity;

use core_user\hook\before_user_updated;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\merge_handler;
use local_ltiidentity\local\placeholder;
use local_ltiidentity\local\policy;
use local_ltiidentity\local\provenance;
use local_ltiidentity\local\sync_detector;
use local_ltiidentity\local\user_writer;

/**
 * Hook callbacks: pre-sync protection of locally maintained profile fields.
 *
 * The callback runs at the top of user_update_user() (4.5) / \core\user::update_user() (5.3), before anything is
 * written, and mutates the update object. Two layers:
 *
 * 1. Placeholder protection (caller independent): for an account with auth "lti", an incoming value that is the
 *    auth_lti placeholder (firstname = sub, lastname = iss, e-mail = enrol_lti_13_sha1) never replaces a real
 *    stored value, whoever writes it.
 * 2. Claim priority (lock, policy): applied only when the update has the fingerprint of the auth_lti
 *    synchronisation (sync_detector). No call stack is inspected.
 *
 * Updates written by this plugin's own user_writer pass untouched, as does every update of a non-LTI account.
 *
 * The callback is the only place where the plugin acts on a launch: it never writes the user record, registers no event
 * observer and queues no task, so there is no later correction step that could race with the launch (IP-02). Its only
 * side effect is the provenance record of a value that is really going to be written.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Protect locally maintained values against the auth_lti launch sync.
     *
     * Cheap exits come first: a non-LTI account or an own write costs no query.
     *
     * @param before_user_updated $hook The hook; its user object is modified in place.
     */
    public static function before_user_updated(before_user_updated $hook): void {
        $update = $hook->user;
        $current = $hook->currentuserdata;
        if (empty($update->id) || empty($current) || $current->auth !== 'lti' || user_writer::is_active()) {
            return;
        }
        $sync = sync_detector::is_sync($update, $current);
        $removed = self::apply_priority($update, $current, policy::configured(), $sync);
        self::record_provenance($update, $current, $sync);
        if ($removed && get_config('local_ltiidentity', 'logprotected')) {
            \local_ltiidentity\event\sync_protected::create([
                'context' => \context_user::instance((int) $current->id),
                'objectid' => (int) $current->id,
                'relateduserid' => (int) $current->id,
                'other' => ['fields' => $removed, 'sync' => $sync],
            ])->trigger();
        }
    }

    /**
     * Apply the field priority to an update object.
     *
     * @param \stdClass $update The update object about to be written (modified in place).
     * @param \stdClass $current The stored user record.
     * @param string $policy The claim priority policy.
     * @param bool $sync Whether the update is the auth_lti synchronisation (lock and policy apply). When false only
     *                   the placeholder protection applies.
     * @return string[] Names of the fields that were removed from the update.
     */
    public static function apply_priority(\stdClass $update, \stdClass $current, string $policy, bool $sync = true): array {
        $removed = [];
        $bindings = null;
        $userid = (int) $current->id;
        foreach (placeholder::FIELDS as $field) {
            if (!property_exists($update, $field)) {
                continue;
            }
            $old = (string) $current->$field;
            $new = (string) $update->$field;
            if ($new === $old) {
                continue;
            }
            $bindings ??= placeholder::bindings($userid);
            $newisplaceholder = placeholder::matches($bindings, $field, $new);
            if ($sync) {
                // An e-mail claim that is not a valid address counts as missing: auth_lti only runs it through
                // core_user::clean_field(), which does not reject invalid addresses (IP-02 contradictory claims).
                $newmissing = $newisplaceholder || ($field === 'email' && trim($new) !== '' && !validate_email($new));
                $decision = policy::decide(
                    $policy,
                    lock_manager::is_locked($userid, $field),
                    $old,
                    placeholder::matches($bindings, $field, $old),
                    $new,
                    $newmissing
                );
            } else {
                $decision = ($old !== '' && $newisplaceholder) ? policy::KEEP : policy::APPLY;
            }
            if ($decision === policy::KEEP) {
                unset($update->$field);
                $removed[] = $field;
            }
        }
        return $removed;
    }

    /**
     * Record where the values that remain in the update come from (IP-04 field provenance).
     *
     * Runs after apply_priority(), so only values that are really going to be written are recorded: "lti" for the
     * auth_lti sync, "other" for every other writer of an LTI account. The hash is taken of the value as core will store
     * it (core cleans invalid values after the hook). Placeholders are not recorded: they are recognised from the value.
     * Costs nothing when no profile field changes (the common relaunch).
     *
     * @param \stdClass $update The filtered update object.
     * @param \stdClass $current The stored user record.
     * @param bool $sync Whether the update is the auth_lti synchronisation.
     */
    private static function record_provenance(\stdClass $update, \stdClass $current, bool $sync): void {
        $bindings = null;
        foreach (placeholder::FIELDS as $field) {
            if (!property_exists($update, $field) || (string) $update->$field === (string) $current->$field) {
                continue;
            }
            $value = (string) \core_user::clean_field((string) $update->$field, $field);
            if (trim($value) === '') {
                continue;
            }
            $bindings ??= placeholder::bindings((int) $current->id);
            if (placeholder::matches($bindings, $field, $value)) {
                continue;
            }
            provenance::record((int) $current->id, $field, $sync ? provenance::LTI : provenance::OTHER, $value);
        }
    }

    /**
     * tool_mergeusers: carry the profile protection of the merged-away account over (finding F4).
     *
     * Registered for \tool_mergeusers\hook\after_merged_all_tables, which runs inside the merge transaction, so the
     * transfer commits or rolls back together with the merge. When tool_mergeusers is not installed the hook class
     * does not exist, core never dispatches it and this method is never called (proven by merge_handler_test).
     * The parameter is untyped on purpose: the hook class is optional.
     *
     * @param object $hook The after_merged_all_tables hook (public fromid, toid; add_log()).
     */
    public static function after_merged_all_tables(object $hook): void {
        global $USER;
        $actorid = isset($USER->id) ? (int) $USER->id : 0;
        foreach (merge_handler::transfer((int) $hook->fromid, (int) $hook->toid, $actorid) as $line) {
            $hook->add_log($line);
        }
    }
}

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

namespace local_ltiidentity\local;

/**
 * Carries the profile protection of a merged-away account over to the surviving account (tool_mergeusers).
 *
 * tool_mergeusers excludes user_preferences from its generic processing, so without this the locks of the removed
 * account A stay on A and the surviving account B is unprotected. Semantics (decided in 0.2.0, finding F4):
 *
 * - B keeps its own locks; nothing of B is overwritten.
 * - A lock of A moves to B only where B has no lock for that field AND B currently holds a real value there (not
 *   empty, not an auth_lti placeholder). Locking a placeholder would stop a later real claim from filling it.
 * - Values are never copied (spec: use the existing Moodle profile, no identity derivation from name or e-mail).
 * - An e-mail lock with provenance "verified" and the verification flag move only if B's current address is the
 *   very address A verified (same hash); otherwise B's address was never confirmed and stays unverified.
 * - A pending e-mail change of A is cancelled (its link becomes invalid), never transferred. B's own pending
 *   request is untouched.
 * - A's own preferences and audit rows stay on A (history); one audit row per account documents the transfer.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class merge_handler {
    /**
     * Apply the transfer rules.
     *
     * @param int $fromid The merged-away account (A).
     * @param int $toid The surviving account (B).
     * @param int $actorid The user who runs the merge (0 for CLI/tasks).
     * @return string[] Log lines without personal values (field names and outcomes only).
     */
    public static function transfer(int $fromid, int $toid, int $actorid): array {
        global $DB;
        $logs = [];
        if ($fromid <= 0 || $toid <= 0 || $fromid === $toid) {
            return $logs;
        }
        $target = $DB->get_record('user', ['id' => $toid, 'deleted' => 0]);
        if (!$target) {
            return $logs;
        }
        $bindings = placeholder::bindings($toid);
        $verifiedhash = (string) get_user_preferences(email_verifier::VERIFIED, '', $fromid);
        $targetemailhash = email_verifier::hash_email((string) $target->email);
        $moved = [];

        foreach (lock_manager::fields() as $field) {
            $source = lock_manager::provenance($fromid, $field);
            if ($source === '') {
                continue;
            }
            if (lock_manager::is_locked($toid, $field)) {
                $logs[] = "local_ltiidentity: lock '$field' kept from the surviving account";
                continue;
            }
            $value = (string) $target->$field;
            if ($value === '' || placeholder::matches($bindings, $field, $value)) {
                $logs[] = "local_ltiidentity: lock '$field' not transferred (placeholder or empty value)";
                continue;
            }
            if (
                $field === 'email' && $source === lock_manager::SOURCE_VERIFIED
                    && ($verifiedhash === '' || !hash_equals($verifiedhash, $targetemailhash))
            ) {
                $logs[] = "local_ltiidentity: verified e-mail lock not transferred (different address)";
                continue;
            }
            lock_manager::lock($toid, $field, $source);
            $moved[] = $field;
            $logs[] = "local_ltiidentity: lock '$field' ($source) transferred";
        }

        // The verification flag only follows when it describes B's current address.
        if (
            $verifiedhash !== '' && hash_equals($verifiedhash, $targetemailhash)
                && (string) get_user_preferences(email_verifier::VERIFIED, '', $toid) === ''
        ) {
            set_user_preference(email_verifier::VERIFIED, $verifiedhash, $toid);
            $logs[] = 'local_ltiidentity: e-mail verification transferred (same address)';
        }

        if ($moved) {
            audit::record($actorid, $toid, \context_user::instance($toid), audit::ACTION_MERGEINTO, $moved, 'unlocked', 'locked');
        }

        if (email_verifier::get_pending($fromid)) {
            unset_user_preference(email_verifier::PENDING, $fromid);
            $fromcontext = \context_user::instance($fromid, IGNORE_MISSING);
            if ($fromcontext) {
                audit::record($actorid, $fromid, $fromcontext, audit::ACTION_MERGECANCEL, ['email'], 'pending', 'cancelled');
            }
            $logs[] = 'local_ltiidentity: pending e-mail change of the merged-away account cancelled';
        }
        return $logs;
    }
}

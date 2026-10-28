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
 * Helper functions of the upgrade steps of local_ltiidentity.
 *
 * Self-contained on purpose: upgrade code must keep working when the plugin's classes change in later releases, so
 * nothing here uses them.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** First version of this implementation that can be upgraded (0.2.0); anything older is the incompatible rump. */
const LOCAL_LTIIDENTITY_FIRST_SUPPORTED_VERSION = 2026102501;

/**
 * Create the 0.3.0 provenance records from the 0.2.0 field locks.
 *
 * A 0.2.0 lock preference `local_ltiidentity_lock_<field>` holds who entered the locked value (teacher, self,
 * verified). For each lock whose account still holds a real value (not empty, not an auth_lti placeholder) the
 * provenance preference `local_ltiidentity_src_<field>` = `<source>:<sha256 of the value>` is created unless one
 * exists. "verified" is only carried over when the 0.2.0 verification hash equals the current address; otherwise
 * nothing is claimed. Idempotent.
 *
 * @return int Number of provenance records created.
 */
function local_ltiidentity_backfill_provenance(): int {
    global $DB;
    $fields = ['firstname', 'lastname', 'email'];
    $sources = ['teacher', 'self', 'verified'];
    $locknames = array_map(static function (string $field): string {
        return 'local_ltiidentity_lock_' . $field;
    }, $fields);
    [$insql, $params] = $DB->get_in_or_equal($locknames, SQL_PARAMS_NAMED);
    $sql = "SELECT up.id, up.userid, up.name, up.value, u.firstname, u.lastname, u.email
              FROM {user_preferences} up
              JOIN {user} u ON u.id = up.userid AND u.deleted = 0
             WHERE up.name $insql
          ORDER BY up.userid, up.name";
    $created = 0;
    $touched = [];
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $lock) {
        $field = substr($lock->name, strlen('local_ltiidentity_lock_'));
        if (!in_array($field, $fields, true) || !in_array($lock->value, $sources, true)) {
            continue;
        }
        $value = (string) $lock->$field;
        if (trim($value) === '' || local_ltiidentity_upgrade_is_placeholder((int) $lock->userid, $field, $value)) {
            continue;
        }
        $hashvalue = $field === 'email' ? strtolower(trim($value)) : $value;
        if ($lock->value === 'verified') {
            $verified = (string) $DB->get_field(
                'user_preferences',
                'value',
                ['userid' => $lock->userid, 'name' => 'local_ltiidentity_emailverified']
            );
            if ($verified === '' || !hash_equals($verified, hash('sha256', strtolower(trim($value))))) {
                continue;
            }
        }
        $name = 'local_ltiidentity_src_' . $field;
        if ($DB->record_exists('user_preferences', ['userid' => $lock->userid, 'name' => $name])) {
            continue;
        }
        $DB->insert_record('user_preferences', (object) [
            'userid' => $lock->userid,
            'name' => $name,
            'value' => $lock->value . ':' . hash('sha256', $hashvalue),
        ]);
        $touched[(int) $lock->userid] = true;
        $created++;
    }
    $rs->close();
    foreach (array_keys($touched) as $userid) {
        mark_user_preferences_changed($userid);
    }
    return $created;
}

/**
 * Whether a value is the auth_lti placeholder of one of the user's LTI bindings.
 *
 * @param int $userid The user.
 * @param string $field firstname, lastname or email.
 * @param string $value The value.
 * @return bool
 */
function local_ltiidentity_upgrade_is_placeholder(int $userid, string $field, string $value): bool {
    global $DB;
    $bindings = $DB->get_records('auth_lti_linked_login', ['userid' => $userid], '', 'id, issuer, sub');
    foreach ($bindings as $binding) {
        if (
            ($field === 'firstname' && $value === $binding->sub)
                || ($field === 'lastname' && $value === $binding->issuer)
                || ($field === 'email'
                    && strtolower($value) === 'enrol_lti_13_' . sha1($binding->issuer . '_' . $binding->sub) . '@example.com')
        ) {
            return true;
        }
    }
    return false;
}

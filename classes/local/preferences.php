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
 * The user preferences this plugin writes, in one place (used by the uninstall hook).
 *
 * The plugin keeps its per-user state in core user preferences: the field locks ({@see lock_manager}), the field
 * provenance ({@see provenance}) and the e-mail verification state ({@see email_verifier}). Moodle does not remove user
 * preferences when a plugin is uninstalled (`uninstall_plugin()` deletes config, tables, capabilities, tasks, files and
 * events, not preferences), so
 * `db/uninstall.php` calls {@see self::delete_all()}.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preferences {
    /**
     * Names of every user preference the plugin writes.
     *
     * @return string[]
     */
    public static function names(): array {
        $names = [];
        foreach (lock_manager::fields() as $field) {
            $names[] = lock_manager::PREFIX . $field;
        }
        foreach (lock_manager::fields() as $field) {
            $names[] = provenance::PREFIX . $field;
        }
        $names[] = email_verifier::PENDING;
        $names[] = email_verifier::VERIFIED;
        return $names;
    }

    /**
     * Delete every preference of this plugin for all users.
     *
     * Users whose preferences changed are flagged so that a preference copy cached in a running session is reloaded.
     *
     * @return int Number of deleted preference records.
     */
    public static function delete_all(): int {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(self::names(), SQL_PARAMS_NAMED);
        $userids = $DB->get_fieldset_select('user_preferences', 'DISTINCT userid', "name $insql", $params);
        $count = $DB->count_records_select('user_preferences', "name $insql", $params);
        $DB->delete_records_select('user_preferences', "name $insql", $params);
        foreach ($userids as $userid) {
            mark_user_preferences_changed((int) $userid);
        }
        return $count;
    }
}

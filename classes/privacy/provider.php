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

namespace local_ltiidentity\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: audit rows plus the lock, provenance, pending-email and verification preferences.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider, user_preference_provider {
    /** @var string Name prefix of all preferences of this plugin. */
    private const PREFPREFIX = 'local_ltiidentity_';

    /**
     * Describe the stored data.
     *
     * @param collection $collection The collection to extend.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_ltiidentity_audit', [
            'actorid' => 'privacy:metadata:audit:actorid',
            'targetuserid' => 'privacy:metadata:audit:targetuserid',
            'contextid' => 'privacy:metadata:audit:contextid',
            'action' => 'privacy:metadata:audit:action',
            'fieldnames' => 'privacy:metadata:audit:fieldnames',
            'oldflag' => 'privacy:metadata:audit:oldflag',
            'newflag' => 'privacy:metadata:audit:newflag',
            'timecreated' => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');
        $collection->add_user_preference('local_ltiidentity_lock_firstname', 'privacy:metadata:preference:lock');
        $collection->add_user_preference('local_ltiidentity_lock_lastname', 'privacy:metadata:preference:lock');
        $collection->add_user_preference('local_ltiidentity_lock_email', 'privacy:metadata:preference:lock');
        $collection->add_user_preference('local_ltiidentity_src_firstname', 'privacy:metadata:preference:provenance');
        $collection->add_user_preference('local_ltiidentity_src_lastname', 'privacy:metadata:preference:provenance');
        $collection->add_user_preference('local_ltiidentity_src_email', 'privacy:metadata:preference:provenance');
        $collection->add_user_preference('local_ltiidentity_emailpending', 'privacy:metadata:preference:emailpending');
        $collection->add_user_preference('local_ltiidentity_emailverified', 'privacy:metadata:preference:emailverified');
        return $collection;
    }

    /**
     * Contexts that hold data of the user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT contextid FROM {local_ltiidentity_audit} WHERE actorid = :actor OR targetuserid = :target",
            ['actor' => $userid, 'target' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {user_preferences} up ON up.userid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND up.userid = :userid AND "
                . $DB->sql_like('up.name', ':pattern'),
            ['level' => CONTEXT_USER, 'userid' => $userid, 'pattern' => $DB->sql_like_escape(self::PREFPREFIX) . '%']
        );
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist The list to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $userlist->add_from_sql('actorid', "SELECT actorid FROM {local_ltiidentity_audit}
            WHERE contextid = :contextid AND actorid > 0", ['contextid' => $context->id]);
        $userlist->add_from_sql('targetuserid', "SELECT targetuserid FROM {local_ltiidentity_audit}
            WHERE contextid = :contextid", ['contextid' => $context->id]);
        if ($context instanceof \context_user) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Export the audit rows of the user.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $rows = $DB->get_records_select(
                'local_ltiidentity_audit',
                'contextid = :contextid AND (actorid = :actor OR targetuserid = :target)',
                ['contextid' => $context->id, 'actor' => $userid, 'target' => $userid],
                'id ASC'
            );
            if (!$rows) {
                continue;
            }
            $data = [];
            foreach ($rows as $row) {
                $data[] = (object) [
                    'actor_is_you' => \core_privacy\local\request\transform::yesno($row->actorid == $userid),
                    'target_is_you' => \core_privacy\local\request\transform::yesno($row->targetuserid == $userid),
                    'action' => $row->action,
                    'fields' => $row->fieldnames,
                    'before' => $row->oldflag,
                    'after' => $row->newflag,
                    'time' => \core_privacy\local\request\transform::datetime($row->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:audit', 'local_ltiidentity')],
                (object) ['entries' => $data]
            );
        }
    }

    /**
     * Export the preferences of the user.
     *
     * @param int $userid The user.
     */
    public static function export_user_preferences(int $userid) {
        global $DB;
        $prefs = $DB->get_records_select(
            'user_preferences',
            'userid = :userid AND ' . $DB->sql_like('name', ':pattern'),
            ['userid' => $userid, 'pattern' => $DB->sql_like_escape(self::PREFPREFIX) . '%']
        );
        foreach ($prefs as $pref) {
            $name = substr($pref->name, strlen('local_ltiidentity_'));
            $value = $pref->value;
            $description = get_string('privacy:preferences', 'local_ltiidentity') . ': ' . $name;
            if ($pref->name === \local_ltiidentity\local\email_verifier::PENDING) {
                // Never export the token hash; the requested address and the expiry are the user's data.
                $data = json_decode((string) $pref->value);
                $value = is_object($data) ? json_encode(['email' => $data->e ?? '', 'expires' => $data->x ?? 0]) : '';
                $description = get_string('privacy:preferences:emailpending', 'local_ltiidentity');
            } else if (str_starts_with($pref->name, \local_ltiidentity\local\provenance::PREFIX)) {
                // The source is the user's data; the hash only binds the record to the stored value.
                $value = strstr((string) $pref->value, ':', true) ?: '';
                $description = get_string('provenance', 'local_ltiidentity') . ': ' .
                    substr($pref->name, strlen(\local_ltiidentity\local\provenance::PREFIX));
            }
            writer::export_user_preference('local_ltiidentity', $pref->name, $value, $description);
        }
    }

    /**
     * Delete all audit rows of a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $DB->delete_records('local_ltiidentity_audit', ['contextid' => $context->id]);
    }

    /**
     * Delete the data of one user in the approved contexts.
     *
     * Rows about the user are deleted, rows where the user only acted are anonymised.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('local_ltiidentity_audit', ['contextid' => $context->id, 'targetuserid' => $userid]);
            $DB->set_field(
                'local_ltiidentity_audit',
                'actorid',
                0,
                ['contextid' => $context->id, 'actorid' => $userid]
            );
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $userid) {
                self::delete_preferences($userid);
            }
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'local_ltiidentity_audit',
            "contextid = :contextid AND targetuserid $insql",
            ['contextid' => $context->id] + $inparams
        );
        $DB->set_field_select(
            'local_ltiidentity_audit',
            'actorid',
            0,
            "contextid = :contextid AND actorid $insql",
            ['contextid' => $context->id] + $inparams
        );
        if ($context instanceof \context_user && in_array($context->instanceid, $userlist->get_userids())) {
            self::delete_preferences((int) $context->instanceid);
        }
    }

    /**
     * Delete the preferences of this plugin for a user.
     *
     * @param int $userid The user.
     */
    private static function delete_preferences(int $userid): void {
        global $DB;
        $DB->delete_records_select(
            'user_preferences',
            'userid = :userid AND ' . $DB->sql_like('name', ':pattern'),
            ['userid' => $userid, 'pattern' => $DB->sql_like_escape(self::PREFPREFIX) . '%']
        );
    }
}

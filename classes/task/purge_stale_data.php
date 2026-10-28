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

namespace local_ltiidentity\task;

use local_ltiidentity\local\audit;
use local_ltiidentity\local\email_verifier;

/**
 * Removes expired e-mail change requests and audit rows older than the configured retention.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_stale_data extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_purge', 'local_ltiidentity');
    }

    /**
     * Run the task.
     */
    public function execute() {
        $expired = self::purge_expired_requests(time());
        $old = self::purge_audit(time());
        mtrace("local_ltiidentity: removed $expired expired e-mail requests and $old audit rows.");
    }

    /**
     * Delete pending e-mail change preferences whose token expired (or that cannot be decoded).
     *
     * @param int $now The current time.
     * @return int Number of deleted preferences.
     */
    public static function purge_expired_requests(int $now): int {
        global $DB;
        $count = 0;
        $ids = [];
        $rs = $DB->get_recordset('user_preferences', ['name' => email_verifier::PENDING], '', 'id, userid, value');
        foreach ($rs as $pref) {
            $data = json_decode((string) $pref->value);
            if (!is_object($data) || !isset($data->x) || (int) $data->x < $now) {
                $ids[] = $pref->id;
            }
        }
        $rs->close();
        if (!empty($ids)) {
            foreach (array_chunk($ids, 500) as $chunk) {
                [$insql, $params] = $DB->get_in_or_equal($chunk);
                $DB->delete_records_select('user_preferences', "id $insql", $params);
                $count += count($chunk);
            }
        }
        return $count;
    }

    /**
     * Delete audit rows older than the retention setting (0 keeps everything).
     *
     * @param int $now The current time.
     * @return int Number of deleted rows.
     */
    public static function purge_audit(int $now): int {
        global $DB;
        $days = (int) get_config('local_ltiidentity', 'auditretention');
        if ($days <= 0) {
            return 0;
        }
        $select = 'timecreated < :cutoff';
        $params = ['cutoff' => $now - $days * DAYSECS];
        $count = $DB->count_records_select(audit::TABLE, $select, $params);
        $DB->delete_records_select(audit::TABLE, $select, $params);
        return $count;
    }
}

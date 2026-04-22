<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Audit helpers for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Basic audit service skeleton.
 */
class audit_service {
    /**
     * Records an audit event.
     *
     * @param int $userid Affected user id.
     * @param string $action Action identifier.
     * @param int|null $actorid Actor id.
     * @param array $details Arbitrary audit details.
     * @return void
     */
    public function record(int $userid, string $action, ?int $actorid = null, array $details = []): void {
        global $DB;

        $record = (object)[
            'userid' => $userid,
            'actorid' => $actorid,
            'action' => $action,
            'detailsjson' => json_encode($details),
            'timecreated' => time(),
        ];

        $DB->insert_record('local_ltiidentity_audit', $record);
    }
}

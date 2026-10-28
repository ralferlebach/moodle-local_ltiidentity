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
 * Small audit trail: who changed which fields of whom. Never stores names or addresses.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit {
    /** @var string Table name. */
    public const TABLE = 'local_ltiidentity_audit';
    /** @var string Action: profile fields saved and locked. */
    public const ACTION_UPDATE = 'update';
    /** @var string Action: lock released by an administrator. */
    public const ACTION_RELEASE = 'release';
    /** @var string Action: e-mail change requested. */
    public const ACTION_EMAILREQUEST = 'emailrequest';
    /** @var string Action: e-mail change confirmed. */
    public const ACTION_EMAILVERIFY = 'emailverify';
    /** @var string Action: locks taken over from a merged-away account (tool_mergeusers). */
    public const ACTION_MERGEINTO = 'mergeinto';
    /** @var string Action: pending e-mail change cancelled because the account was merged away. */
    public const ACTION_MERGECANCEL = 'mergecancel';

    /**
     * Write one audit row.
     *
     * @param int $actorid The acting user.
     * @param int $targetuserid The affected user.
     * @param \context $context The context of the action.
     * @param string $action One of the ACTION_ constants.
     * @param string[] $fields Names of the fields concerned.
     * @param string $oldflag Lock or policy state before.
     * @param string $newflag Lock or policy state after.
     * @return int The id of the row.
     */
    public static function record(
        int $actorid,
        int $targetuserid,
        \context $context,
        string $action,
        array $fields,
        string $oldflag,
        string $newflag
    ): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'actorid' => $actorid,
            'targetuserid' => $targetuserid,
            'contextid' => $context->id,
            'action' => $action,
            'fieldnames' => substr(implode(',', $fields), 0, 255),
            'oldflag' => substr($oldflag, 0, 30),
            'newflag' => substr($newflag, 0, 30),
            'timecreated' => time(),
        ]);
    }
}

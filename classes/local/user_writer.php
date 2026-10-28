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
 * The single place that writes core user fields.
 *
 * Moodle 5.3 deprecated user_update_user() in favour of \core\user::update_user(); Moodle 4.5 only has the
 * function. Both dispatch the same before_user_updated hook. While a write of this class runs, the hook
 * callback of this plugin stands aside (is_active()), so teacher and self-service edits are never filtered.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_writer {
    /** @var int Nesting depth of running writes of this class. */
    private static int $depth = 0;

    /**
     * Whether a write of this plugin is running (the hook callback must not filter it).
     *
     * @return bool
     */
    public static function is_active(): bool {
        return self::$depth > 0;
    }

    /**
     * Update fields of a user record through the core API.
     *
     * @param \stdClass $update Object with id and the fields to change.
     * @param bool $updatepassword Whether a password in the object should be applied (never used here).
     */
    public static function update(\stdClass $update, bool $updatepassword = false): void {
        global $CFG;
        self::$depth++;
        try {
            if (method_exists(\core_user::class, 'update_user')) {
                \core_user::update_user($update, $updatepassword);
            } else {
                require_once($CFG->dirroot . '/user/lib.php');
                user_update_user($update, $updatepassword);
            }
        } finally {
            self::$depth--;
        }
    }
}

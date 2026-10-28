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

namespace local_ltiidentity\tests\fixtures;

/**
 * Test double for a future core that moved the LTI sync to another class and method name and calls the user API
 * from deep inside a longer call chain. The update object has the same shape auth_plugin_lti sends today.
 *
 * Negative control for IP-01: protection by call-stack name would fail with this caller.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renamed_core_caller {
    /**
     * Perform a sync-shaped update through a deep call chain with unrelated names.
     *
     * @param \stdClass $user The stored user.
     * @param array $update Extra or replacement properties; defaults to the auth_lti shape with placeholders.
     * @param int $depth Number of nested frames.
     */
    public static function refresh_identity(\stdClass $user, array $update, int $depth = 30): void {
        if ($depth > 0) {
            self::refresh_identity($user, $update, $depth - 1);
            return;
        }
        $object = (object) $update;
        if (method_exists(\core_user::class, 'update_user')) {
            \core_user::update_user($object);
        } else {
            global $CFG;
            require_once($CFG->dirroot . '/user/lib.php');
            user_update_user($object);
        }
    }
}

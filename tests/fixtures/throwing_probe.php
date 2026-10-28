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
 * Test double: a before_user_updated callback that makes the write fail for one marker address.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class throwing_probe {
    /**
     * Throw when the update carries the marker address.
     *
     * @param \core_user\hook\before_user_updated $hook The hook.
     */
    public static function callback(\core_user\hook\before_user_updated $hook): void {
        if (isset($hook->user->email) && $hook->user->email === 'fail.write@example.org') {
            throw new \moodle_exception('invaliduserid');
        }
    }
}

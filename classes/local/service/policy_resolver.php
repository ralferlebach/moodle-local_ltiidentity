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
 * Policy resolution helpers for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves effective plugin policies.
 */
class policy_resolver {
    /**
     * Checks whether the self-service page should be visible to the given user.
     *
     * @param stdClass $user User record.
     * @return bool
     */
    public static function can_show_self_service(stdClass $user): bool {
        if (($user->auth ?? '') !== 'lti') {
            return false;
        }

        return (bool)get_config('local_ltiidentity', 'allowselfservice');
    }
}

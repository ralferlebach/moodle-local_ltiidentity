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
 * Post-login reconciliation service.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Reconciles locally managed identity values after a login.
 */
class reconciliation_service {
    /**
     * Reconciles a user after login.
     *
     * @param int $userid User id.
     * @return void
     */
    public static function reconcile_user_after_login(int $userid): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || $user->auth !== 'lti') {
            return;
        }

        // Placeholder for the planned post-sync reconciliation workflow.
    }
}

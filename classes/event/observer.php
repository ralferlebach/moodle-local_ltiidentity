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
 * Event observer callbacks for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\event;

use core\event\user_loggedin;
use local_ltiidentity\local\service\reconciliation_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer bridge class.
 */
class observer {
    /**
     * Handles the user_loggedin event.
     *
     * @param user_loggedin $event Login event.
     * @return void
     */
    public static function user_loggedin(user_loggedin $event): void {
        reconciliation_service::reconcile_user_after_login((int)$event->userid);
    }
}

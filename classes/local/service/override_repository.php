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
 * Repository wrapper for local LTI identity overrides.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Basic repository for override records.
 */
class override_repository {
    /**
     * Returns the override record for a user.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public function get_by_userid(int $userid): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_ltiidentity_override', ['userid' => $userid]);
        return $record ?: null;
    }
}

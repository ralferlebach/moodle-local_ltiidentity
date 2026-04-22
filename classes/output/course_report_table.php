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
 * Placeholder table for course-level identity overview.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Placeholder class for a future course overview table.
 */
class course_report_table {
    /**
     * Returns the planned table columns.
     *
     * @return string[]
     */
    public function get_columns(): array {
        return [
            'userid',
            'firstname',
            'lastname',
            'email',
            'policy',
        ];
    }
}

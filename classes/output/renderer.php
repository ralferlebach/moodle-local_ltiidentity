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
 * Renderer for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\output;

use plugin_renderer_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Basic output renderer.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the site-level management placeholder.
     *
     * @return string
     */
    public function render_management_placeholder(): string {
        return $this->notification(get_string('managementplaceholder', 'local_ltiidentity'), 'info');
    }

    /**
     * Renders the course-level placeholder.
     *
     * @param stdClass $course Course record.
     * @return string
     */
    public function render_course_placeholder(stdClass $course): string {
        $message = get_string('courseplaceholder', 'local_ltiidentity') . ' ' . format_string($course->shortname);
        return $this->notification($message, 'info');
    }
}

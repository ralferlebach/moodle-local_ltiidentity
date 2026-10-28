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

/**
 * Hook callbacks of local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_user\hook\before_user_updated::class,
        'callback' => [\local_ltiidentity\hook_callbacks::class, 'before_user_updated'],
        // Late priority: other plugins may adjust the update first, the protection has the last word.
        'priority' => -100,
    ],
    [
        // Optional integration: only dispatched when tool_mergeusers is installed. Core stores the class name as a
        // string and never autoloads it, so the entry is inert without that plugin (merge_handler_test).
        'hook' => \tool_mergeusers\hook\after_merged_all_tables::class,
        'callback' => [\local_ltiidentity\hook_callbacks::class, 'after_merged_all_tables'],
        'priority' => 0,
    ],
];

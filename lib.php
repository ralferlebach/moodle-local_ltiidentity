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
 * Callback bridges for the local_ltiidentity plugin.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_ltiidentity\local\service\policy_resolver;

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the user settings navigation with the LTI identity page.
 *
 * @param navigation_node $navigation User settings navigation.
 * @param stdClass $user Current user object.
 * @param context_user $usercontext User context.
 * @param stdClass|null $course Current course when available.
 * @param context|null $coursecontext Current course context when available.
 * @return void
 */
function local_ltiidentity_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    ?stdClass $course = null,
    ?context $coursecontext = null
): void {
    if (!policy_resolver::can_show_self_service($user)) {
        return;
    }

    $url = new moodle_url('/local/ltiidentity/preferences.php', ['id' => $user->id]);
    $navigation->add(
        get_string('managemyidentity', 'local_ltiidentity'),
        $url,
        navigation_node::TYPE_SETTING,
    );
}

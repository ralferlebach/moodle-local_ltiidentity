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
 * Library callbacks of local_ltiidentity (navigation, profile page, status check).
 *
 * The navigation callbacks are still supported by Moodle 4.5 and 5.3 (verified in settings_navigation); the profile
 * callback is called by \core_user\output\myprofile\manager::build_tree() in both.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the LTI profile page to the course navigation for users who may manage it.
 *
 * @param navigation_node $parentnode The course node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 */
function local_ltiidentity_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    if ($course->id == SITEID || !has_capability('local/ltiidentity:manage', $context)) {
        return;
    }
    $parentnode->add(
        get_string('ltiusers', 'local_ltiidentity'),
        new moodle_url('/local/ltiidentity/index.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_ltiidentity_course',
        new pix_icon('i/users', '')
    );
}

/**
 * Add the self-service page to the user's preferences for LTI accounts.
 *
 * @param navigation_node $parentnode The user settings node.
 * @param stdClass $user The user whose settings are shown.
 * @param context_user $context The user context.
 * @param stdClass $course The current course.
 * @param context_course $coursecontext The course context.
 */
function local_ltiidentity_extend_navigation_user_settings(
    navigation_node $parentnode,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;
    if (!isloggedin() || isguestuser() || (int) $user->id !== (int) $USER->id) {
        return;
    }
    if (!\local_ltiidentity\local\access::can_edit_own((int) $USER->id)) {
        return;
    }
    $parentnode->add(
        get_string('myltiprofile', 'local_ltiidentity'),
        new moodle_url('/local/ltiidentity/profile.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_ltiidentity_own'
    );
}

/**
 * Show the field provenance of an LTI account on its profile page (IP-04).
 *
 * Called by \core_user\output\myprofile\manager::build_tree() in Moodle 4.5 and 5.3 (user/profile.php, user/view.php).
 *
 * @param \core_user\output\myprofile\tree $tree The profile tree.
 * @param stdClass $user The profile owner.
 * @param bool $iscurrentuser Whether the viewer is the owner.
 * @param stdClass|null $course The course of a course profile.
 * @return bool
 */
function local_ltiidentity_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;
    if (!isloggedin() || isguestuser() || empty($user->id)) {
        return false;
    }
    return \local_ltiidentity\output\profile_provenance::add_to_tree($tree, $user, (int) $USER->id, $course ?: null);
}

/**
 * Status checks of this plugin (shown under Site administration > Reports > Status).
 *
 * @return \core\check\check[]
 */
function local_ltiidentity_status_checks(): array {
    return [new \local_ltiidentity\check\sync_detection()];
}

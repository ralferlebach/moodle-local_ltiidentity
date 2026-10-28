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

namespace local_ltiidentity\local;

/**
 * Authorisation rules: who may edit whose profile.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Whether the actor may complete the profile of the target user in this course.
     *
     * The target must be an LTI user (auth = lti), not deleted, not a guest, enrolled in the course
     * (any enrolment status) and not a site administrator unless the actor is one.
     *
     * @param \context_course $context The course context.
     * @param int $targetid The user to be edited.
     * @param int|null $actorid The acting user, defaults to the current user.
     * @return bool
     */
    public static function can_manage_user(\context_course $context, int $targetid, ?int $actorid = null): bool {
        global $USER;
        $actorid = $actorid ?? (int) $USER->id;
        if ($context->instanceid == SITEID) {
            return false;
        }
        if (!has_capability('local/ltiidentity:manage', $context, $actorid)) {
            return false;
        }
        $target = self::get_lti_user($targetid);
        if (!$target) {
            return false;
        }
        if (is_siteadmin($target) && !is_siteadmin($actorid)) {
            return false;
        }
        return is_enrolled($context, $target, '', false);
    }

    /**
     * Whether the user may complete their own profile.
     *
     * @param int $userid The user.
     * @return bool
     */
    public static function can_edit_own(int $userid): bool {
        if ($userid <= 0 || !self::get_lti_user($userid)) {
            return false;
        }
        return has_capability('local/ltiidentity:editown', \context_system::instance(), $userid);
    }

    /**
     * Whether the actor may release locks.
     *
     * @param int|null $actorid The acting user, defaults to the current user.
     * @return bool
     */
    public static function can_release(?int $actorid = null): bool {
        return has_capability('local/ltiidentity:releaselock', \context_system::instance(), $actorid);
    }

    /**
     * Fetch a user record if it is a live LTI account.
     *
     * @param int $userid The user id.
     * @return \stdClass|null
     */
    public static function get_lti_user(int $userid): ?\stdClass {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || $user->auth !== 'lti' || isguestuser($user)) {
            return null;
        }
        return $user;
    }
}

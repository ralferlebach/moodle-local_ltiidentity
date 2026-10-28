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

namespace local_ltiidentity;

use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Tests for the navigation entries: the callbacks exist in this Moodle version, are invoked by core
 * and respect capabilities.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Core discovers the callbacks through get_plugins_with_function().
     *
     * @coversNothing
     */
    public function test_core_discovers_callbacks(): void {
        $course = get_plugins_with_function('extend_navigation_course', 'lib.php', false);
        $this->assertContains('local_ltiidentity_extend_navigation_course', $course['local'] ?? []);
        $user = get_plugins_with_function('extend_navigation_user_settings', 'lib.php', false);
        $this->assertContains('local_ltiidentity_extend_navigation_user_settings', $user['local'] ?? []);
    }

    /**
     * Teachers get the course entry, students do not.
     *
     * @covers ::local_ltiidentity_extend_navigation_course
     */
    public function test_course_entry_for_teacher_only(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);

        foreach ([[$teacher, true], [$student, false]] as [$user, $expected]) {
            $this->setUser($user);
            $PAGE = new \moodle_page();
            $PAGE->set_course($course);
            $PAGE->set_url('/course/view.php', ['id' => $course->id]);
            $node = new \navigation_node(['text' => 'Course', 'type' => \navigation_node::TYPE_COURSE]);
            local_ltiidentity_extend_navigation_course($node, $course, $context);
            $child = $node->get('local_ltiidentity_course');
            if ($expected) {
                $this->assertNotFalse($child);
                $this->assertStringContainsString(
                    '/local/ltiidentity/index.php?id=' . $course->id,
                    $child->action->out(false)
                );
            } else {
                $this->assertFalse($child);
            }
        }
    }

    /**
     * The real settings navigation of a course page contains the entry for a teacher.
     *
     * @covers ::local_ltiidentity_extend_navigation_course
     */
    public function test_settings_navigation_contains_course_entry(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_context(\context_course::instance($course->id));
        $found = $PAGE->settingsnav->find('local_ltiidentity_course', null);
        $this->assertNotFalse($found, 'Core invoked the extend_navigation_course callback');
    }

    /**
     * LTI users get the self-service entry in their own preferences, others do not.
     *
     * @covers ::local_ltiidentity_extend_navigation_user_settings
     */
    public function test_user_settings_entry_for_lti_user_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $lti = launch_helper::launch('nav-1');
        $manual = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($course->id);

        foreach ([[$lti, true], [$manual, false]] as [$user, $expected]) {
            $this->setUser($user);
            $node = new \navigation_node(['text' => 'Prefs', 'type' => \navigation_node::TYPE_CONTAINER]);
            local_ltiidentity_extend_navigation_user_settings(
                $node,
                $user,
                \context_user::instance($user->id),
                $course,
                $coursecontext
            );
            $child = $node->get('local_ltiidentity_own');
            $this->assertSame($expected, $child !== false);
        }
    }

    /**
     * The real preferences navigation contains the self-service entry for an LTI user.
     *
     * @covers ::local_ltiidentity_extend_navigation_user_settings
     */
    public function test_settings_navigation_contains_user_entry(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $lti = launch_helper::launch('nav-2');
        $this->setUser($lti);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/user/preferences.php');
        $PAGE->set_context(\context_user::instance($USER->id));
        $this->assertNotFalse($PAGE->settingsnav->find('local_ltiidentity_own', null));
    }
}

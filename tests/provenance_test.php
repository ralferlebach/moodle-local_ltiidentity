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

use core_user\output\myprofile\tree;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\local\provenance;
use local_ltiidentity\output\profile_provenance;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * IP-04 field provenance: recorded by every writer, bound to the value, shown on the profile page to authorised viewers.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\provenance
 * @covers     \local_ltiidentity\output\profile_provenance
 * @covers     \local_ltiidentity\hook_callbacks
 * @covers     ::local_ltiidentity_myprofile_navigation
 */
final class provenance_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Write through the core user API as an administrator form or upload would (not this plugin's writer).
     *
     * @param \stdClass $update The update object.
     */
    private function core_update(\stdClass $update): void {
        global $CFG;
        if (method_exists(\core_user::class, 'update_user')) {
            \core_user::update_user($update, false);
        } else {
            require_once($CFG->dirroot . '/user/lib.php');
            user_update_user($update, false);
        }
    }

    /**
     * Fresh user record.
     *
     * @param int $userid The user.
     * @return \stdClass
     */
    private function user(int $userid): \stdClass {
        global $DB;
        return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    /**
     * Course with a teacher in which the learner is enrolled.
     *
     * @param \stdClass $learner The LTI user.
     * @return array [course, teacher]
     */
    private function course_with_teacher(\stdClass $learner): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        return [$course, $teacher];
    }

    /**
     * Placeholder after a launch without claims, "lti" after a claim, "other" after an administrator edit.
     */
    public function test_launch_and_other_writers(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('pv-1');
        $this->assertSame(
            ['firstname' => provenance::PLACEHOLDER, 'lastname' => provenance::PLACEHOLDER, 'email' => provenance::PLACEHOLDER],
            provenance::all_of($learner)
        );

        $learner = launch_helper::launch('pv-1', ['given_name' => 'Pia', 'email' => 'pia.pv1@example.org']);
        $all = provenance::all_of($learner);
        $this->assertSame(provenance::LTI, $all['firstname']);
        $this->assertSame(provenance::PLACEHOLDER, $all['lastname']);
        $this->assertSame(provenance::LTI, $all['email']);

        $this->core_update((object) ['id' => $learner->id, 'firstname' => 'Admin Edit']);
        $this->assertSame(provenance::OTHER, provenance::of($this->user($learner->id), 'firstname'));
        $this->assertSame(provenance::LTI, provenance::of($this->user($learner->id), 'email'));
    }

    /**
     * Teacher, self-service and the confirmed e-mail link record their source.
     */
    public function test_plugin_writers_record_their_source(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('pv-2');
        [$course, $teacher] = $this->course_with_teacher($learner);
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => 'Tina', 'lastname' => 'Teacher'],
            lock_manager::SOURCE_TEACHER
        );
        $this->assertSame(provenance::TEACHER, provenance::of($this->user($learner->id), 'firstname'));

        profile_service::save(
            $learner->id,
            $learner->id,
            \context_user::instance($learner->id),
            (object) ['firstname' => 'Selma', 'lastname' => 'Teacher'],
            lock_manager::SOURCE_SELF
        );
        $user = $this->user($learner->id);
        $this->assertSame(provenance::SELFSERVICE, provenance::of($user, 'firstname'));
        $this->assertSame(provenance::SELFSERVICE, provenance::of($user, 'lastname'), 'A confirmed unchanged value too');

        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $this->assertSame(email_verifier::REQUESTED, email_verifier::request($learner->id, $user, 'selma.pv2@example.org'));
        $this->assertSame(provenance::PLACEHOLDER, provenance::of($this->user($learner->id), 'email'), 'Pending is not applied');
        preg_match('/token=([0-9a-f]{64})/', quoted_printable_decode($sink->get_messages()[0]->body), $m);
        $sink->close();
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($learner->id, $m[1], $learner->id));
        $this->assertSame(provenance::VERIFIED, provenance::of($this->user($learner->id), 'email'));
    }

    /**
     * A record never describes another value; only a hash is stored; unknown records are ignored.
     */
    public function test_record_is_bound_to_the_value(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('pv-3', ['given_name' => 'Rosa']);
        $this->assertSame(provenance::INITIAL, provenance::of($learner, 'firstname'), 'Set at account creation');

        provenance::record($learner->id, 'firstname', provenance::LTI, 'Rosa');
        $raw = get_user_preferences(provenance::PREFIX . 'firstname', '', $learner->id);
        $this->assertStringNotContainsString('Rosa', $raw);
        $this->assertSame('lti:' . hash('sha256', 'Rosa'), $raw);
        $this->assertSame(provenance::LTI, provenance::of($this->user($learner->id), 'firstname'));

        // Changed without a record (direct database write): the record is stale and not shown as the provenance.
        $DB->set_field('user', 'firstname', 'Rose', ['id' => $learner->id]);
        $this->assertSame(provenance::UNKNOWN, provenance::of($this->user($learner->id), 'firstname'));

        set_user_preference(provenance::PREFIX . 'firstname', 'forged:' . hash('sha256', 'Rose'), $learner->id);
        $this->assertSame(provenance::INITIAL, provenance::of($this->user($learner->id), 'firstname'));

        // E-mail is compared case-insensitively.
        provenance::record($learner->id, 'email', provenance::OTHER, 'Mixed.Case@Example.org');
        $DB->set_field('user', 'email', 'mixed.case@example.org', ['id' => $learner->id]);
        $this->assertSame(provenance::OTHER, provenance::of($this->user($learner->id), 'email'));
    }

    /**
     * Invalid input is refused.
     */
    public function test_invalid_record(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\coding_exception::class);
        provenance::record($user->id, 'username', provenance::LTI, 'x');
    }

    /**
     * Visibility matrix: owner, teacher of a shared course (course profile only), other teacher, other learner,
     * manager (viewprovenance), and nobody for a non-LTI account.
     */
    public function test_visibility(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('pv-4');
        [$course, $teacher] = $this->course_with_teacher($learner);
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $peer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_system::instance()->id);
        $manual = $this->getDataGenerator()->create_and_enrol($course, 'student', ['auth' => 'manual']);

        $this->assertTrue(provenance::can_view($learner, $learner->id));
        $this->assertTrue(provenance::can_view($learner, $teacher->id, $course));
        $this->assertFalse(provenance::can_view($learner, $teacher->id), 'Site profile: the course role does not apply');
        $this->assertFalse(provenance::can_view($learner, $otherteacher->id, $othercourse));
        $this->assertFalse(provenance::can_view($learner, $peer->id, $course));
        $this->assertTrue(provenance::can_view($learner, $manager->id));
        $this->assertTrue(provenance::can_view($learner, get_admin()->id));
        $this->assertFalse(provenance::can_view($manual, $manual->id), 'Only LTI accounts');
        $this->assertFalse(provenance::can_view($manual, $manager->id));
        $this->assertFalse(provenance::can_view($learner, 0));
    }

    /**
     * The profile tree gets the category with one node per field and the e-mail status, without values.
     */
    public function test_profile_tree(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('pv-5', ['given_name' => 'Tree']);
        [$course, $teacher] = $this->course_with_teacher($learner);
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => 'Tess', 'lastname' => 'Tree'],
            lock_manager::SOURCE_TEACHER
        );
        $learner = $this->user($learner->id);

        $tree = new tree();
        $this->assertTrue(profile_provenance::add_to_tree($tree, $learner, $teacher->id, $course));
        $this->assertArrayHasKey(profile_provenance::CATEGORY, $tree->categories);
        $nodes = $tree->nodes;
        $first = $nodes['local_ltiidentity_src_firstname'];
        $this->assertStringContainsString(get_string('provenance_teacher', 'local_ltiidentity'), $first->content);
        $this->assertStringContainsString(get_string('provenancelocked', 'local_ltiidentity'), $first->content);
        $this->assertStringNotContainsString('Tess', $first->content);
        $email = $nodes['local_ltiidentity_src_email'];
        $this->assertStringContainsString(get_string('provenance_placeholder', 'local_ltiidentity'), $email->content);
        $this->assertArrayHasKey('local_ltiidentity_emailstatus', $nodes);

        $tree = new tree();
        $peer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertFalse(profile_provenance::add_to_tree($tree, $learner, $peer->id, $course));
        $this->assertSame([], $tree->categories);
    }

    /**
     * Core discovers the callback and builds the real profile tree with it, for the owner only.
     */
    public function test_core_profile_page_shows_provenance_to_the_owner(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');
        $this->assertArrayHasKey(
            'ltiidentity',
            get_plugins_with_function('myprofile_navigation', 'lib.php')['local'] ?? []
        );
        $learner = launch_helper::launch('pv-6');
        $other = $this->getDataGenerator()->create_user();

        $PAGE->set_url(new \moodle_url('/user/profile.php', ['id' => $learner->id]));
        $this->setUser($learner);
        $tree = \core_user\output\myprofile\manager::build_tree($this->user($learner->id), true);
        $this->assertArrayHasKey(profile_provenance::CATEGORY, $tree->categories);

        $this->setUser($other);
        $tree = \core_user\output\myprofile\manager::build_tree($this->user($learner->id), false);
        $this->assertArrayNotHasKey(profile_provenance::CATEGORY, $tree->categories);
    }
}

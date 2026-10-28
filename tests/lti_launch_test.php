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

use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\policy;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Integration tests with the real auth_plugin_lti::complete_login() (acceptance criteria 1 and 2).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\hook_callbacks
 */
final class lti_launch_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Create a course with a teacher and enrol the LTI user as student.
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
     * Teacher completes the profile with a real name.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $teacher The teacher.
     * @param \stdClass $learner The LTI user.
     * @param string $first First name.
     * @param string $last Last name.
     */
    private function teacher_sets_name(
        \stdClass $course,
        \stdClass $teacher,
        \stdClass $learner,
        string $first,
        string $last
    ): void {
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => $first, 'lastname' => $last],
            lock_manager::SOURCE_TEACHER
        );
    }

    /**
     * AK 1: same user, two launches without name/email claims: the entered values are kept.
     */
    public function test_two_launches_without_claims_keep_teacher_values(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-1');
        [$course, $teacher] = $this->course_with_teacher($learner);
        $this->teacher_sets_name($course, $teacher, $learner, 'Grace', 'Hopper');

        $after1 = launch_helper::launch('stu-1');
        $after2 = launch_helper::launch('stu-1');
        foreach ([$after1, $after2] as $user) {
            $this->assertSame($learner->id, $user->id);
            $this->assertSame('Grace', $user->firstname);
            $this->assertSame('Hopper', $user->lastname);
        }
        $stored = $DB->get_record('user', ['id' => $learner->id]);
        $this->assertSame('Grace', $stored->firstname);
        $this->assertSame('Hopper', $stored->lastname);
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['userid' => $learner->id]));
    }

    /**
     * Without any lock a local real value still survives a launch that only sends placeholders (priority 3 over 4).
     */
    public function test_unlocked_local_value_survives_placeholder_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-2');
        $DB->set_field('user', 'lastname', 'Local Surname', ['id' => $learner->id]);
        $after = launch_helper::launch('stu-2');
        $this->assertSame('Local Surname', $after->lastname);
        $this->assertSame('stu-2', $after->firstname, 'Placeholder over placeholder is unchanged');
    }

    /**
     * AK 2: launch with real claims against a lock, for every policy.
     *
     * @dataProvider policy_provider
     * @param string $policy The configured policy.
     * @param bool $lock Whether the field is locked.
     * @param string $expectedfirst Expected first name after the launch.
     */
    public function test_launch_with_claims_versus_lock(string $policy, bool $lock, string $expectedfirst): void {
        global $DB;
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        $learner = launch_helper::launch('stu-3');
        [$course, $teacher] = $this->course_with_teacher($learner);
        if ($lock) {
            $this->teacher_sets_name($course, $teacher, $learner, 'Local', 'Person');
        } else {
            $DB->set_field('user', 'firstname', 'Local', ['id' => $learner->id]);
            $DB->set_field('user', 'lastname', 'Person', ['id' => $learner->id]);
        }
        $after = launch_helper::launch('stu-3', ['given_name' => 'Claim', 'family_name' => 'Name']);
        $this->assertSame($expectedfirst, $after->firstname);
        $this->assertSame($expectedfirst === 'Local' ? 'Person' : 'Name', $after->lastname);
    }

    /**
     * Cases for test_launch_with_claims_versus_lock.
     *
     * @return array
     */
    public static function policy_provider(): array {
        return [
            'claims win, locked' => [policy::CLAIMS_WIN, true, 'Local'],
            'claims win, unlocked' => [policy::CLAIMS_WIN, false, 'Claim'],
            'local wins, locked' => [policy::LOCAL_WINS, true, 'Local'],
            'local wins, unlocked' => [policy::LOCAL_WINS, false, 'Local'],
            'fill placeholders, locked' => [policy::FILL_PLACEHOLDERS, true, 'Local'],
            'fill placeholders, unlocked real value' => [policy::FILL_PLACEHOLDERS, false, 'Local'],
        ];
    }

    /**
     * Claims fill placeholders under the "fill placeholders" policy and under the default.
     *
     * @dataProvider fill_provider
     * @param string $policy The configured policy.
     * @param string $expectedfirst Expected first name.
     */
    public function test_claims_replace_placeholders(string $policy, string $expectedfirst): void {
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        launch_helper::launch('stu-4');
        $after = launch_helper::launch('stu-4', ['given_name' => 'Claim', 'family_name' => 'Name',
            'email' => 'claim@example.org']);
        $this->assertSame($expectedfirst, $after->firstname);
        if ($expectedfirst === 'Claim') {
            $this->assertSame('claim@example.org', $after->email);
        } else {
            $this->assertSame(local\placeholder::placeholder_email(launch_helper::ISSUER, 'stu-4'), $after->email);
        }
    }

    /**
     * Cases for test_claims_replace_placeholders.
     *
     * @return array
     */
    public static function fill_provider(): array {
        return [
            'claims win' => [policy::CLAIMS_WIN, 'Claim'],
            'fill placeholders' => [policy::FILL_PLACEHOLDERS, 'Claim'],
            'local wins keeps the placeholder' => [policy::LOCAL_WINS, 'stu-4'],
        ];
    }

    /**
     * An admin release makes the claim win again (IP-P06).
     */
    public function test_release_lock_lets_claims_through(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-5');
        [$course, $teacher] = $this->course_with_teacher($learner);
        $this->teacher_sets_name($course, $teacher, $learner, 'Local', 'Person');
        $claims = ['given_name' => 'Claim', 'family_name' => 'Name'];
        $this->assertSame('Local', launch_helper::launch('stu-5', $claims)->firstname);

        $this->setAdminUser();
        profile_service::release_lock(get_admin()->id, $learner->id, 'firstname');
        $after = launch_helper::launch('stu-5', $claims);
        $this->assertSame('Claim', $after->firstname);
        $this->assertSame('Person', $after->lastname, 'Only the released field is open to claims');
    }

    /**
     * A verified, locked e-mail is not replaced by the launch claim; a placeholder never replaces a real e-mail.
     */
    public function test_email_lock_and_placeholder_email(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-6');
        $DB->set_field('user', 'email', 'real@example.org', ['id' => $learner->id]);
        $this->assertSame('real@example.org', launch_helper::launch('stu-6')->email);

        lock_manager::lock($learner->id, 'email', lock_manager::SOURCE_VERIFIED);
        $this->assertSame(
            'real@example.org',
            launch_helper::launch('stu-6', ['email' => 'platform@example.org'])->email
        );
    }

    /**
     * The membership (NRPS) sync path goes through the same update_user_account() and is protected too.
     */
    public function test_membership_sync_is_protected(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-7');
        [$course, $teacher] = $this->course_with_teacher($learner);
        $this->teacher_sets_name($course, $teacher, $learner, 'Local', 'Person');

        $auth = get_auth_plugin('lti');
        $user = $auth->find_or_create_user_from_membership(['user_id' => 'stu-7', 'roles' => []], launch_helper::ISSUER);
        $this->assertSame($learner->id, $user->id);
        $this->assertSame('Local', $user->firstname);
        $this->assertSame('Person', $user->lastname);
    }

    /**
     * Normal profile edits by admins and by the user are never blocked, even for LTI accounts.
     */
    public function test_regular_updates_are_not_blocked(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $learner = launch_helper::launch('stu-8');
        [$course, $teacher] = $this->course_with_teacher($learner);
        $this->teacher_sets_name($course, $teacher, $learner, 'Local', 'Person');

        $this->setAdminUser();
        local\user_writer::update((object) ['id' => $learner->id, 'firstname' => 'ByAdmin', 'email' => 'admin@example.org']);
        $stored = $DB->get_record('user', ['id' => $learner->id]);
        $this->assertSame('ByAdmin', $stored->firstname);
        $this->assertSame('admin@example.org', $stored->email);

        // Even a value that equals the auth_lti placeholder is written when it is not the auth_lti sync that asks.
        local\user_writer::update((object) ['id' => $learner->id, 'firstname' => 'stu-8']);
        $this->assertSame('stu-8', $DB->get_field('user', 'firstname', ['id' => $learner->id]));
    }

    /**
     * Non-LTI accounts are never touched by the hook, even if auth_lti code is on the stack.
     */
    public function test_non_lti_user_is_ignored(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual', 'firstname' => 'Manual']);
        local\user_writer::update((object) ['id' => $user->id, 'firstname' => 'Changed']);
        $this->assertSame('Changed', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }
}

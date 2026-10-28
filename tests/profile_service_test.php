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

use local_ltiidentity\local\access;
use local_ltiidentity\local\audit;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Tests for authorisation, saving, locking, audit and events (IP-P03, IP-P06).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\access
 * @covers     \local_ltiidentity\local\profile_service
 * @covers     \local_ltiidentity\local\audit
 */
final class profile_service_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Teachers may edit only enrolled LTI users of their own course.
     */
    public function test_teacher_scope(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $ltistudent = launch_helper::launch('s-1');
        $gen->enrol_user($ltistudent->id, $course->id, 'student');
        $ltiother = launch_helper::launch('s-2');
        $gen->enrol_user($ltiother->id, $other->id, 'student');
        $ltinotenrolled = launch_helper::launch('s-3');
        $context = \context_course::instance($course->id);

        $this->assertTrue(access::can_manage_user($context, $ltistudent->id, $teacher->id));
        $this->assertFalse(access::can_manage_user($context, $ltiother->id, $teacher->id), 'LTI user of another course');
        $this->assertFalse(access::can_manage_user($context, $ltinotenrolled->id, $teacher->id), 'Not enrolled');
        $this->assertFalse(access::can_manage_user($context, $student->id, $teacher->id), 'Non-LTI user');
        $this->assertFalse(
            access::can_manage_user(\context_course::instance($other->id), $ltiother->id, $teacher->id),
            'Teacher of another course'
        );
        $this->assertFalse(access::can_manage_user($context, $ltistudent->id, $student->id), 'Students cannot manage');
        $this->assertFalse(access::can_manage_user($context, 999999, $teacher->id), 'Unknown user');
        $this->assertFalse(access::can_manage_user(\context_course::instance(SITEID), $ltistudent->id, $teacher->id));
    }

    /**
     * Suspended enrolments can still be repaired, deleted accounts cannot.
     */
    public function test_suspended_and_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $suspended = launch_helper::launch('s-4');
        $gen->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $context = \context_course::instance($course->id);
        $this->assertTrue(access::can_manage_user($context, $suspended->id, $teacher->id));
        $DB->set_field('user', 'deleted', 1, ['id' => $suspended->id]);
        $this->assertFalse(access::can_manage_user($context, $suspended->id, $teacher->id));
    }

    /**
     * An LTI site administrator is out of reach for teachers.
     */
    public function test_siteadmin_target_is_protected(): void {
        global $CFG;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $admin = launch_helper::launch('s-5');
        $gen->enrol_user($admin->id, $course->id, 'student');
        set_config('siteadmins', $CFG->siteadmins . ',' . $admin->id);
        $context = \context_course::instance($course->id);
        $this->assertFalse(access::can_manage_user($context, $admin->id, $teacher->id));
        $this->assertTrue(access::can_manage_user($context, $admin->id, get_admin()->id));
    }

    /**
     * Self-service is limited to the own LTI account.
     */
    public function test_self_service_access(): void {
        $this->resetAfterTest();
        $lti = launch_helper::launch('s-6');
        $manual = $this->getDataGenerator()->create_user();
        $this->assertTrue(access::can_edit_own($lti->id));
        $this->assertFalse(access::can_edit_own($manual->id), 'Only LTI accounts');
        $this->assertFalse(access::can_edit_own(0));
        $this->assertFalse(access::can_edit_own((int) guest_user()->id));
    }

    /**
     * Saving names updates the core user record, locks the fields, writes audit and event.
     */
    public function test_save_updates_core_user_locks_audits_and_triggers_event(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $learner = launch_helper::launch('s-7');
        $gen->enrol_user($learner->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $sink = $this->redirectEvents();
        $result = profile_service::save(
            $teacher->id,
            $learner->id,
            $context,
            (object) ['firstname' => ' Katherine ', 'lastname' => 'Johnson'],
            lock_manager::SOURCE_TEACHER
        );
        $events = array_filter($sink->get_events(), static function ($e): bool {
            return $e instanceof \local_ltiidentity\event\profile_updated;
        });
        $sink->close();

        $this->assertSame(['firstname', 'lastname'], $result->saved);
        $stored = \core_user::get_user($learner->id);
        $this->assertSame('Katherine', $stored->firstname);
        $this->assertSame('Johnson', $stored->lastname);
        $this->assertSame('teacher', lock_manager::provenance($learner->id, 'firstname'));
        $this->assertFalse(lock_manager::is_locked($learner->id, 'email'));

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($teacher->id, $event->userid);
        $this->assertEquals($learner->id, $event->relateduserid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertStringNotContainsString('Katherine', $event->get_description());

        $rows = $DB->get_records(audit::TABLE);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals($teacher->id, $row->actorid);
        $this->assertEquals($learner->id, $row->targetuserid);
        $this->assertSame('update', $row->action);
        $this->assertSame('firstname,lastname', $row->fieldnames);
        $this->assertSame('unlocked', $row->oldflag);
        $this->assertSame('locked', $row->newflag);
        $this->assert_no_personal_values_in_audit(['Katherine', 'Johnson']);
    }

    /**
     * The audit table never contains names or addresses.
     *
     * @param string[] $needles Values that must not appear.
     */
    private function assert_no_personal_values_in_audit(array $needles): void {
        global $DB;
        foreach ($DB->get_records(audit::TABLE) as $row) {
            $flat = implode('|', (array) $row);
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $flat);
            }
            $this->assertStringNotContainsString('@', $flat);
        }
    }

    /**
     * Placeholders, empty and over-long values are refused.
     *
     * @dataProvider invalid_name_provider
     * @param string $value The value.
     * @param string $errorkey Expected lang key.
     */
    public function test_invalid_names_rejected(string $value, string $errorkey): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('s-8');
        $this->assertSame($errorkey, profile_service::validate_name($learner->id, 'firstname', $value));
        if ($errorkey !== '') {
            $this->expectException(\invalid_parameter_exception::class);
            profile_service::save(
                $learner->id,
                $learner->id,
                \context_user::instance($learner->id),
                (object) ['firstname' => $value, 'lastname' => 'Valid'],
                lock_manager::SOURCE_SELF
            );
        }
    }

    /**
     * Cases for test_invalid_names_rejected.
     *
     * @return array
     */
    public static function invalid_name_provider(): array {
        return [
            'empty' => ['', 'errornameempty'],
            'blank' => ['   ', 'errornameempty'],
            'too long' => [str_repeat('x', 101), 'errornametoolong'],
            'placeholder (sub)' => ['s-8', 'errorplaceholdervalue'],
            'fine' => ['Ada', ''],
        ];
    }

    /**
     * Self-service sets the lock with provenance "self".
     */
    public function test_self_service_save(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('s-9');
        profile_service::save(
            $learner->id,
            $learner->id,
            \context_user::instance($learner->id),
            (object) ['firstname' => 'Self', 'lastname' => 'Made'],
            lock_manager::SOURCE_SELF
        );
        $this->assertSame('self', lock_manager::provenance($learner->id, 'lastname'));
        $this->assertSame('Self', \core_user::get_user($learner->id)->firstname);
    }

    /**
     * Saving a non-LTI user is refused.
     */
    public function test_save_refuses_non_lti_user(): void {
        $this->resetAfterTest();
        $manual = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        profile_service::save(
            $manual->id,
            $manual->id,
            \context_user::instance($manual->id),
            (object) ['firstname' => 'A', 'lastname' => 'B'],
            lock_manager::SOURCE_SELF
        );
    }

    /**
     * Administrator release: capability needed, audit and event written, lock gone.
     */
    public function test_release_lock_requires_capability_and_is_audited(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $learner = launch_helper::launch('s-10');
        $gen->enrol_user($learner->id, $course->id, 'student');
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => 'Local', 'lastname' => 'Person'],
            lock_manager::SOURCE_TEACHER
        );

        try {
            profile_service::release_lock($teacher->id, $learner->id, 'firstname');
            $this->fail('A teacher must not release locks');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(lock_manager::is_locked($learner->id, 'firstname'));
        }

        $sink = $this->redirectEvents();
        profile_service::release_lock(get_admin()->id, $learner->id, 'firstname');
        $released = array_filter($sink->get_events(), static function ($e): bool {
            return $e instanceof \local_ltiidentity\event\lock_released;
        });
        $sink->close();
        $this->assertCount(1, $released);
        $this->assertFalse(lock_manager::is_locked($learner->id, 'firstname'));
        $this->assertTrue(lock_manager::is_locked($learner->id, 'lastname'));
        $row = $DB->get_record(audit::TABLE, ['action' => 'release']);
        $this->assertSame('firstname', $row->fieldnames);
        $this->assertSame('locked', $row->oldflag);
        $this->assertSame('unlocked', $row->newflag);

        $this->expectException(\invalid_parameter_exception::class);
        profile_service::release_lock(get_admin()->id, $learner->id, 'firstname');
    }

    /**
     * Core and third-party readers see the stored values (AK: core profile reads the real user data).
     */
    public function test_core_readers_see_the_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $learner = launch_helper::launch('s-11');
        $gen->enrol_user($learner->id, $course->id, 'student');
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => 'Mary', 'lastname' => 'Jackson'],
            lock_manager::SOURCE_TEACHER
        );

        $this->assertSame('Mary Jackson', fullname(\core_user::get_user($learner->id)));
        $enrolled = get_enrolled_users(\context_course::instance($course->id), '', 0, 'u.id, u.firstname, u.lastname');
        $this->assertSame('Mary', $enrolled[$learner->id]->firstname);
        $this->assertSame('Mary Jackson', fullname(get_complete_user_data('id', $learner->id)));
    }
}

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
use local_ltiidentity\local\provenance;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * IP-04 capability matrix (docs/DOCUMENTATION.md, section "Capability matrix"): defaults per archetype, effective rights
 * per role in a course, and the action checks built on them (they follow the capabilities, not role names).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\access
 * @covers     \local_ltiidentity\local\provenance::can_view
 */
final class capability_matrix_test extends \advanced_testcase {
    /** @var string[] The capabilities of the plugin. */
    private const CAPS = [
        'local/ltiidentity:manage',
        'local/ltiidentity:editown',
        'local/ltiidentity:releaselock',
        'local/ltiidentity:viewprovenance',
    ];

    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Every capability is defined with the documented context level, type and risk.
     */
    public function test_definitions(): void {
        $expected = [
            'local/ltiidentity:manage' => [CONTEXT_COURSE, 'write'],
            'local/ltiidentity:editown' => [CONTEXT_SYSTEM, 'write'],
            'local/ltiidentity:releaselock' => [CONTEXT_SYSTEM, 'write'],
            'local/ltiidentity:viewprovenance' => [CONTEXT_SYSTEM, 'read'],
        ];
        foreach ($expected as $name => [$level, $type]) {
            $cap = get_capability_info($name);
            $this->assertNotEmpty($cap, $name);
            $this->assertEquals($level, $cap->contextlevel, $name);
            $this->assertSame($type, $cap->captype, $name);
            $this->assertTrue(($cap->riskbitmask & RISK_PERSONAL) === RISK_PERSONAL, "$name carries RISK_PERSONAL");
        }
        $this->assertCount(count(self::CAPS), array_filter(get_all_capabilities(), static function (array $cap): bool {
            return str_starts_with($cap['name'], 'local/ltiidentity:');
        }));
    }

    /**
     * Archetype defaults (role_capabilities in the system context after installation).
     *
     * @dataProvider defaults_provider
     * @param string $role Role shortname.
     * @param string[] $allowed Capabilities allowed by default.
     */
    public function test_archetype_defaults(string $role, array $allowed): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST);
        foreach (self::CAPS as $cap) {
            $permission = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $roleid, 'capability' => $cap, 'contextid' => \context_system::instance()->id,
            ]);
            $this->assertSame(
                in_array($cap, $allowed, true),
                (int) $permission === CAP_ALLOW,
                "$role / $cap"
            );
        }
    }

    /**
     * Expected defaults per role.
     *
     * @return array
     */
    public static function defaults_provider(): array {
        return [
            'manager' => ['manager', ['local/ltiidentity:manage', 'local/ltiidentity:releaselock',
                'local/ltiidentity:viewprovenance']],
            'editingteacher' => ['editingteacher', ['local/ltiidentity:manage']],
            'teacher (non-editing)' => ['teacher', []],
            'student' => ['student', []],
            'authenticated user' => ['user', ['local/ltiidentity:editown']],
            'guest' => ['guest', []],
            'course creator' => ['coursecreator', []],
            'frontpage' => ['frontpage', []],
        ];
    }

    /**
     * Action matrix: who may do what, for an LTI learner enrolled in a course.
     */
    public function test_action_matrix(): void {
        $this->resetAfterTest();
        $learner = launch_helper::launch('cap-1');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $editing = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $nonediting = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_system::instance()->id);
        $outsider = $this->getDataGenerator()->create_and_enrol($this->getDataGenerator()->create_course(), 'editingteacher');
        $admin = get_admin();

        // Actor => [edit learner in the course, release locks, view provenance on the course profile, on the site profile].
        $matrix = [
            'editing teacher' => [$editing, true, false, true, false],
            'non-editing teacher' => [$nonediting, false, false, false, false],
            'student' => [$student, false, false, false, false],
            'manager (not enrolled)' => [$manager, true, true, true, true],
            'teacher of another course' => [$outsider, false, false, false, false],
            'site administrator' => [$admin, true, true, true, true],
            'the learner' => [$learner, false, false, true, true],
        ];
        foreach ($matrix as $label => [$actor, $edit, $release, $viewcourse, $viewsite]) {
            $this->assertSame($edit, access::can_manage_user($context, $learner->id, $actor->id), "$label: edit");
            $this->assertSame($release, access::can_release($actor->id), "$label: release");
            $this->assertSame($viewcourse, provenance::can_view($learner, $actor->id, $course), "$label: view in course");
            $this->assertSame($viewsite, provenance::can_view($learner, $actor->id), "$label: view on site profile");
        }

        // Self-service: LTI accounts only; never for guests or manual accounts.
        $this->assertTrue(access::can_edit_own($learner->id));
        $this->assertFalse(access::can_edit_own($student->id), 'Manual account');
        $this->assertFalse(access::can_edit_own(guest_user()->id));
        $this->assertFalse(access::can_edit_own(0));
    }

    /**
     * The checks follow the capabilities: a prohibit removes the right, a grant adds it, whatever the role is called.
     */
    public function test_overrides_are_honoured(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('cap-2');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $editing = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $nonediting = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->assertTrue(access::can_manage_user($context, $learner->id, $editing->id));
        $editingrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('local/ltiidentity:manage', CAP_PROHIBIT, $editingrole, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(access::can_manage_user($context, $learner->id, $editing->id), 'Prohibit in the course');

        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/ltiidentity:manage', CAP_ALLOW, $teacherrole, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(access::can_manage_user($context, $learner->id, $nonediting->id), 'Grant in the course');

        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('local/ltiidentity:editown', CAP_PROHIBIT, $userrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(access::can_edit_own($learner->id), 'Self-service switched off by prohibit');
    }
}

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
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Unit tests for the hook callback: caller detection and update object mutation.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * update_user_account() called directly (as the NRPS and launch code do) is detected, and the update object is mutated.
     */
    public function test_direct_update_user_account_is_protected(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('cb-1');
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Pinned', ['id' => $user->id]);
        $fresh = $DB->get_record('user', ['id' => $user->id]);

        get_auth_plugin('lti')->update_user_account(
            $fresh,
            ['sub' => 'cb-1', 'given_name' => 'Other'],
            launch_helper::ISSUER
        );
        $this->assertSame('Pinned', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * Fields are removed from the update object, nothing else is touched.
     */
    public function test_apply_priority_mutates_update_object(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('cb-2');
        lock_manager::lock($user->id, 'lastname', lock_manager::SOURCE_SELF);
        $update = (object) ['id' => $user->id, 'firstname' => 'Claim', 'lastname' => 'Claim', 'email' => 'c@example.org',
            'city' => 'Somewhere'];
        $removed = hook_callbacks::apply_priority($update, $user, policy::CLAIMS_WIN);
        $this->assertSame(['lastname'], $removed);
        $this->assertFalse(property_exists($update, 'lastname'));
        $this->assertSame('Claim', $update->firstname);
        $this->assertSame('c@example.org', $update->email);
        $this->assertSame('Somewhere', $update->city);
    }

    /**
     * An update without the three fields is left alone.
     */
    public function test_update_without_profile_fields_untouched(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('cb-3');
        $update = (object) ['id' => $user->id, 'city' => 'Somewhere'];
        $this->assertSame([], hook_callbacks::apply_priority($update, $user, policy::LOCAL_WINS));
        $this->assertSame('Somewhere', $update->city);
    }

    /**
     * Idempotence: repeated syncs give the same result and do not accumulate side effects.
     */
    public function test_repeated_syncs_are_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('cb-4');
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Pinned', ['id' => $user->id]);
        $before = $DB->count_records('user');
        for ($i = 0; $i < 5; $i++) {
            $after = launch_helper::launch('cb-4');
            $this->assertSame('Pinned', $after->firstname);
        }
        $this->assertSame($before, $DB->count_records('user'));
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['sub' => 'cb-4']));
    }
}

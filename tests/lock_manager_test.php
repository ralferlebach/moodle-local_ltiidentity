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

/**
 * Tests for provenance and lock flags in user preferences (IP-P02).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\lock_manager
 */
final class lock_manager_test extends \advanced_testcase {
    /**
     * Lock, read, release; flags live in core user preferences only.
     */
    public function test_lock_cycle_uses_core_preferences(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(lock_manager::is_locked($user->id, 'firstname'));

        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        lock_manager::lock($user->id, 'email', lock_manager::SOURCE_VERIFIED);
        $this->assertTrue(lock_manager::is_locked($user->id, 'firstname'));
        $this->assertSame('teacher', lock_manager::provenance($user->id, 'firstname'));
        $this->assertSame(['firstname', 'email'], lock_manager::locked_fields($user->id));
        $this->assertSame('teacher', $DB->get_field(
            'user_preferences',
            'value',
            ['userid' => $user->id, 'name' => 'local_ltiidentity_lock_firstname']
        ));

        lock_manager::release($user->id, 'firstname');
        $this->assertFalse(lock_manager::is_locked($user->id, 'firstname'));
        $this->assertSame(['email'], lock_manager::locked_fields($user->id));
    }

    /**
     * Only the three fields and known sources are accepted.
     */
    public function test_invalid_input_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        try {
            lock_manager::lock($user->id, 'username', lock_manager::SOURCE_SELF);
            $this->fail('username must not be lockable');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Unknown lockable field', $e->getMessage());
        }
        try {
            lock_manager::lock($user->id, 'firstname', 'nobody');
            $this->fail('unknown source must be rejected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Unknown lock source', $e->getMessage());
        }
        $this->assertFalse(lock_manager::is_locked($user->id, 'username'));
        $this->assertSame('', lock_manager::provenance($user->id, 'username'));
    }
}

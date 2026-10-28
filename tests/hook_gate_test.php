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

use local_ltiidentity\tests\fixtures\before_write_probe;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Evidence for gate IP-G1: a core hook exists that fires BEFORE the auth_lti field sync is written.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\hook_callbacks
 */
final class hook_gate_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once(__DIR__ . '/fixtures/before_write_probe.php');
        parent::setUpBeforeClass();
    }

    /**
     * The hook class exists with the expected shape and our callback is registered for it.
     */
    public function test_hook_exists_and_callback_registered(): void {
        $this->assertTrue(class_exists(\core_user\hook\before_user_updated::class));
        $constructor = new \ReflectionMethod(\core_user\hook\before_user_updated::class, '__construct');
        $names = array_map(static function (\ReflectionParameter $p): string {
            return $p->getName();
        }, $constructor->getParameters());
        $this->assertSame(['user', 'currentuserdata'], $names);

        $callbacks = \core\di::get(\core\hook\manager::class)
            ->get_callbacks_for_hook(\core_user\hook\before_user_updated::class);
        $ours = array_filter($callbacks, static function (array $definition): bool {
            return $definition['component'] === 'local_ltiidentity';
        });
        $this->assertCount(1, $ours);
        $this->assertSame(
            'local_ltiidentity\hook_callbacks::before_user_updated',
            reset($ours)['callback']
        );
    }

    /**
     * The hook runs before the write: the database still holds the old value when it fires, and
     * a change made by the callback is what ends up stored.
     */
    public function test_hook_fires_before_write_and_can_veto_the_sync(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $user = launch_helper::launch('gate-1');
        $DB->set_field('user', 'firstname', 'Teacher Entered', ['id' => $user->id]);

        before_write_probe::$seen = [];
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance([
            'local_ltiidentity' => $CFG->dirroot . '/local/ltiidentity/db/hooks.php',
            'local_ltiidentity_probe' => __DIR__ . '/fixtures/probe_hooks.php',
        ]));
        try {
            // Second launch without claims: auth_lti wants to write firstname = sub.
            launch_helper::launch('gate-1');
        } finally {
            \core\di::reset_container();
        }

        $this->assertNotEmpty(before_write_probe::$seen);
        $seen = end(before_write_probe::$seen);
        $this->assertSame('Teacher Entered', $seen['indb'], 'The database was not yet written when the hook fired');
        $this->assertSame('gate-1', $seen['incoming'], 'The hook sees the placeholder auth_lti is about to write');
        // Our callback ran after the probe and removed the placeholder from the update before the write.
        $this->assertSame('Teacher Entered', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * Negative control: without the callback auth_lti resets the value, proving that the protection
     * comes from the hook and that the launch really tries to overwrite.
     */
    public function test_without_callback_the_launch_overwrites(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('gate-2');
        $DB->set_field('user', 'firstname', 'Teacher Entered', ['id' => $user->id]);

        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance([]));
        try {
            launch_helper::launch('gate-2');
        } finally {
            \core\di::reset_container();
        }
        $this->assertSame('gate-2', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }
}

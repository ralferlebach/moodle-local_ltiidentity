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

use local_ltiidentity\local\audit;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\merge_handler;
use local_ltiidentity\tests\fixtures\launch_helper;
use local_ltiidentity\tests\fixtures\merge_hook_standin;

/**
 * Finding F4: profile protection follows a tool_mergeusers merge (A merged into B).
 *
 * tool_mergeusers is not installed in the test trees. The callback is exercised with a stand-in of its hook and, when
 * the real hook class exists, with the real hook object as well.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\merge_handler
 * @covers     \local_ltiidentity\hook_callbacks::after_merged_all_tables
 */
final class merge_handler_test extends \advanced_testcase {
    /** @var string Hook class of tool_mergeusers (optional plugin). */
    private const HOOK = 'tool_mergeusers\\hook\\after_merged_all_tables';

    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once(__DIR__ . '/fixtures/merge_hook_standin.php');
        parent::setUpBeforeClass();
    }

    /**
     * Two LTI accounts with real names: A (to remove) and B (to keep).
     *
     * @return \stdClass[] [A, B]
     */
    private function pair(): array {
        $a = launch_helper::launch('mg-a-' . random_string(6), [
            'given_name' => 'Anna', 'family_name' => 'Alpha', 'email' => 'anna.' . random_string(4) . '@example.org',
        ]);
        $b = launch_helper::launch('mg-b-' . random_string(6), [
            'given_name' => 'Berta', 'family_name' => 'Beta', 'email' => 'berta.' . random_string(4) . '@example.org',
        ]);
        return [$a, $b];
    }

    /**
     * Run the hook callback like tool_mergeusers would (stand-in object).
     *
     * @param int $fromid A.
     * @param int $toid B.
     * @return merge_hook_standin
     */
    private function merge(int $fromid, int $toid): merge_hook_standin {
        $hook = new merge_hook_standin($toid, $fromid);
        hook_callbacks::after_merged_all_tables($hook);
        $this->assertSame([], $hook->errors, 'The callback never fails a merge');
        return $hook;
    }

    /**
     * Registration is inert without tool_mergeusers: no debugging, callback callable, stored under the class name.
     */
    public function test_registration_without_tool_mergeusers(): void {
        global $CFG;
        $manager = \core\hook\manager::phpunit_get_instance([
            'local_ltiidentity' => $CFG->dirroot . '/local/ltiidentity/db/hooks.php',
        ]);
        $this->assertDebuggingNotCalled();
        $callbacks = $manager->get_callbacks_for_hook(self::HOOK);
        $this->assertCount(1, $callbacks);
        $this->assertSame('local_ltiidentity\\hook_callbacks::after_merged_all_tables', $callbacks[0]['callback']);
        $this->assertTrue(is_callable($callbacks[0]['callback']));
        // The real site manager (all plugins) loads the same definition without debugging, and other hooks still work.
        $real = \core\di::get(\core\hook\manager::class)->get_callbacks_for_hook(self::HOOK);
        $this->assertNotEmpty($real);
        $this->resetAfterTest();
        launch_helper::launch('mg-reg');
        $this->assertDebuggingNotCalled();
    }

    /**
     * A's locks move to B where B has none and holds a real value; B's own lock is kept; values are not copied.
     */
    public function test_locks_follow_where_b_has_none(): void {
        global $DB;
        $this->resetAfterTest();
        [$a, $b] = $this->pair();
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        lock_manager::lock($a->id, 'lastname', lock_manager::SOURCE_SELF);
        lock_manager::lock($b->id, 'lastname', lock_manager::SOURCE_TEACHER);

        $hook = $this->merge($a->id, $b->id);

        $this->assertSame(lock_manager::SOURCE_TEACHER, lock_manager::provenance($b->id, 'firstname'), 'Transferred');
        $this->assertSame(lock_manager::SOURCE_TEACHER, lock_manager::provenance($b->id, 'lastname'), 'B kept its own');
        $stored = $DB->get_record('user', ['id' => $b->id]);
        $this->assertSame(['Berta', 'Beta'], [$stored->firstname, $stored->lastname], 'No value copied');
        $this->assertTrue(lock_manager::is_locked($a->id, 'firstname'), "A's history stays");
        $row = $DB->get_record(audit::TABLE, ['action' => audit::ACTION_MERGEINTO]);
        $this->assertEquals($b->id, $row->targetuserid);
        $this->assertSame('firstname', $row->fieldnames);
        foreach ($hook->logs as $line) {
            $this->assertStringNotContainsString('Berta', $line);
            $this->assertStringNotContainsString('Anna', $line);
            $this->assertStringNotContainsString('@', $line);
        }
    }

    /**
     * After the transfer a later launch of B with other claims keeps B's now locked value.
     */
    public function test_transferred_lock_protects_b_against_claims(): void {
        global $DB;
        $this->resetAfterTest();
        $a = launch_helper::launch('mg-a1', ['given_name' => 'Anna', 'family_name' => 'Alpha']);
        $b = launch_helper::launch('mg-b1', ['given_name' => 'Berta', 'family_name' => 'Beta']);
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $this->merge($a->id, $b->id);
        $after = launch_helper::launch('mg-b1', ['given_name' => 'Claimed', 'family_name' => 'Beta']);
        $this->assertSame($b->id, $after->id);
        $this->assertSame('Berta', $DB->get_field('user', 'firstname', ['id' => $b->id]));
    }

    /**
     * NEGATIVE CONTROL: without the transfer (0.2.0 before the fix) the same launch overwrites B's name.
     */
    public function test_without_transfer_claims_overwrite_b(): void {
        global $DB;
        $this->resetAfterTest();
        $a = launch_helper::launch('mg-a2', ['given_name' => 'Anna', 'family_name' => 'Alpha']);
        $b = launch_helper::launch('mg-b2', ['given_name' => 'Berta', 'family_name' => 'Beta']);
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        launch_helper::launch('mg-b2', ['given_name' => 'Claimed', 'family_name' => 'Beta']);
        $this->assertSame('Claimed', $DB->get_field('user', 'firstname', ['id' => $b->id]));
    }

    /**
     * A lock is not put on a placeholder of B: a later real claim must still be able to fill it.
     */
    public function test_no_lock_on_placeholder(): void {
        $this->resetAfterTest();
        $a = launch_helper::launch('mg-a3', ['given_name' => 'Anna', 'family_name' => 'Alpha']);
        $b = launch_helper::launch('mg-b3');
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        lock_manager::lock($a->id, 'email', lock_manager::SOURCE_TEACHER);
        $hook = $this->merge($a->id, $b->id);
        $this->assertFalse(lock_manager::is_locked($b->id, 'firstname'));
        $this->assertFalse(lock_manager::is_locked($b->id, 'email'));
        $this->assertCount(2, array_filter($hook->logs, static fn($l) => str_contains($l, 'placeholder')));
        $after = launch_helper::launch('mg-b3', ['given_name' => 'Real', 'family_name' => 'Name']);
        $this->assertSame('Real', $after->firstname);
    }

    /**
     * A verified e-mail lock follows only if B holds the very address A verified; then the verification follows too.
     */
    public function test_verified_email_only_for_same_address(): void {
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        [$a, $b] = $this->pair();
        lock_manager::lock($a->id, 'email', lock_manager::SOURCE_VERIFIED);
        set_user_preference(email_verifier::VERIFIED, email_verifier::hash_email($a->email), $a->id);
        $this->merge($a->id, $b->id);
        $this->assertFalse(lock_manager::is_locked($b->id, 'email'), 'Different address: no verified lock');
        $this->assertFalse(email_verifier::is_verified(\core_user::get_user($b->id)));

        // Same address on both accounts (allowed by policy): lock and verification follow.
        [$c, $d] = $this->pair();
        \local_ltiidentity\local\user_writer::update((object) ['id' => $d->id, 'email' => $c->email]);
        lock_manager::lock($c->id, 'email', lock_manager::SOURCE_VERIFIED);
        set_user_preference(email_verifier::VERIFIED, email_verifier::hash_email($c->email), $c->id);
        $this->merge($c->id, $d->id);
        $this->assertSame(lock_manager::SOURCE_VERIFIED, lock_manager::provenance($d->id, 'email'));
        $this->assertTrue(email_verifier::is_verified(\core_user::get_user($d->id)));
    }

    /**
     * A's pending e-mail change is cancelled (link invalid afterwards), B's own pending request stays.
     */
    public function test_pending_of_a_cancelled_b_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        [$a, $b] = $this->pair();
        $pending = [
            'e' => 'x@example.org', 'h' => hash('sha256', 'tok'), 'x' => time() + 600, 'c' => time() - 100, 'a' => 0, 'r' => 1,
        ];
        set_user_preference(email_verifier::PENDING, json_encode($pending), $a->id);
        set_user_preference(email_verifier::PENDING, json_encode($pending), $b->id);
        $this->merge($a->id, $b->id);
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $a->id, 'name' => email_verifier::PENDING]));
        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $b->id, 'name' => email_verifier::PENDING]));
        $this->assertTrue($DB->record_exists(audit::TABLE, ['action' => audit::ACTION_MERGECANCEL, 'targetuserid' => $a->id]));
        $this->setUser($a);
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($a->id, 'tok', $a->id));
    }

    /**
     * Repeating the callback changes nothing more; degenerate input is ignored.
     */
    public function test_idempotent_and_guards(): void {
        global $DB;
        $this->resetAfterTest();
        [$a, $b] = $this->pair();
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $this->merge($a->id, $b->id);
        $count = $DB->count_records(audit::TABLE);
        $second = $this->merge($a->id, $b->id);
        $this->assertSame($count, $DB->count_records(audit::TABLE));
        $this->assertStringContainsString('kept from the surviving account', implode("\n", $second->logs));
        $this->assertSame([], merge_handler::transfer($a->id, $a->id, 0));
        $this->assertSame([], merge_handler::transfer(0, $b->id, 0));
        $this->assertSame([], merge_handler::transfer($a->id, 999999, 0));
    }

    /**
     * When the real tool_mergeusers hook class is available, the callback works with the real object too; otherwise the
     * test documents that the class is absent (the stand-in path above is the evidence).
     */
    public function test_real_hook_object_when_available(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->pair();
        lock_manager::lock($a->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $class = self::HOOK;
        if (class_exists($class)) {
            $logs = [];
            $errors = [];
            $hook = new $class($b->id, $a->id, $logs, $errors);
            hook_callbacks::after_merged_all_tables($hook);
            $this->assertSame([], $errors);
            $this->assertNotEmpty($logs);
        } else {
            $this->assertFalse(class_exists($class, false));
            $hook = new merge_hook_standin($b->id, $a->id);
            hook_callbacks::after_merged_all_tables($hook);
            $this->assertNotEmpty($hook->logs);
        }
        $this->assertTrue(lock_manager::is_locked($b->id, 'firstname'));
    }
}

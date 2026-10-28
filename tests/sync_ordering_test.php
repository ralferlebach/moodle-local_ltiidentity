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
use local_ltiidentity\local\provenance;
use local_ltiidentity\tests\fixtures\launch_helper;
use local_ltiidentity\tests\fixtures\sync_ordering_probe;

/**
 * IP-02: the protection happens before core writes, and there is no later correction step that could race.
 *
 * The proof has three parts: (1) the plugin registers no event observer, no task and no hook that runs after the write;
 * (2) during a real launch the update object that reaches core's write no longer contains the protected fields, and
 * the stored values right after the write (observed through core's user_updated event) are the protected ones - a
 * protected value is never written, not even for a moment; (3) negative control: without the plugin the same probes
 * see the overwrite.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\hook_callbacks
 */
final class sync_ordering_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once(__DIR__ . '/fixtures/sync_ordering_probe.php');
        parent::setUpBeforeClass();
    }

    /**
     * Run a launch with the ordering probes installed.
     *
     * @param bool $withplugin Whether the plugin's hook callbacks are registered.
     * @param string $sub The platform user id.
     * @param array $claims The claims.
     * @return \stdClass The stored user afterwards.
     */
    private function probed_launch(bool $withplugin, string $sub, array $claims = []): \stdClass {
        global $CFG;
        $components = ['local_ltiidentity_orderingprobe' => __DIR__ . '/fixtures/ordering_probe_hooks.php'];
        if ($withplugin) {
            $components['local_ltiidentity'] = $CFG->dirroot . '/local/ltiidentity/db/hooks.php';
        }
        sync_ordering_probe::reset();
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance($components));
        \core\event\manager::phpunit_replace_observers([[
            'eventname' => '\core\event\user_updated',
            'callback' => [sync_ordering_probe::class, 'after_write'],
        ]]);
        try {
            return launch_helper::launch($sub, $claims);
        } finally {
            \core\di::reset_container();
            // The first reset keeps replaced observers (core's reload flag), the second drops them so the next event
            // reloads the real observers.
            \core\event\manager::phpunit_reset();
            \core\event\manager::phpunit_reset();
        }
    }

    /**
     * The plugin has no step that runs after the write: no observers, no tasks, only the pre-write hook.
     */
    public function test_no_post_write_step_is_registered(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->assertFileDoesNotExist($CFG->dirroot . '/local/ltiidentity/db/events.php');
        foreach (\core\event\manager::get_all_observers() as $eventname => $observers) {
            foreach ($observers as $observer) {
                $this->assertFalse(
                    $observer->plugintype === 'local' && $observer->plugin === 'ltiidentity',
                    "Observer for $eventname registered by the plugin"
                );
            }
        }
        $callbacks = [];
        include($CFG->dirroot . '/local/ltiidentity/db/hooks.php');
        $hooks = array_column($callbacks, 'hook');
        sort($hooks);
        $this->assertSame([
            \core_user\hook\before_user_updated::class,
            'tool_mergeusers\hook\after_merged_all_tables',
        ], $hooks);

        $learner = launch_helper::launch('ord-0');
        lock_manager::lock($learner->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Kept', ['id' => $learner->id]);
        launch_helper::launch('ord-0', ['given_name' => 'Other']);
        $this->assertSame('Kept', $DB->get_field('user', 'firstname', ['id' => $learner->id]));
        $this->assertSame(0, $DB->count_records_select(
            'task_adhoc',
            'component = :c OR ' . $DB->sql_like('classname', ':cl'),
            ['c' => 'local_ltiidentity', 'cl' => '%local_ltiidentity%']
        ), 'A launch queues no deferred work of the plugin');
    }

    /**
     * Protected values never reach the write; right after the write the database holds the protected values.
     */
    public function test_protected_values_are_never_written(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('ord-1');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => 'Ada', 'lastname' => 'Lovelace'],
            lock_manager::SOURCE_TEACHER
        );

        // Contradicting name claims (locked) and a real e-mail claim (unlocked, claims win): a mixed update.
        $after = $this->probed_launch(true, 'ord-1', ['given_name' => 'Claim', 'family_name' => 'Name',
            'email' => 'claimed.ord1@example.org']);

        $this->assertCount(1, sync_ordering_probe::$final, 'Exactly one user update per launch, no second write');
        $final = sync_ordering_probe::$final[0];
        $this->assertArrayNotHasKey('firstname', $final, 'The locked first name never reaches the write');
        $this->assertArrayNotHasKey('lastname', $final);
        $this->assertSame('claimed.ord1@example.org', $final['email'], 'The unlocked real claim is written');

        $this->assertCount(1, sync_ordering_probe::$afterwrite);
        $atwrite = sync_ordering_probe::$afterwrite[0];
        $this->assertSame('Ada', $atwrite['firstname'], 'Right after core wrote, the protected value is in place');
        $this->assertSame('Lovelace', $atwrite['lastname']);
        $this->assertSame('claimed.ord1@example.org', $atwrite['email']);

        $this->assertSame('Ada', $after->firstname);
        $this->assertSame(provenance::LTI, provenance::of($after, 'email'));
        $this->assertSame(provenance::TEACHER, provenance::of($after, 'firstname'));
    }

    /**
     * Negative control: without the plugin the same probes see the placeholder written and stored.
     */
    public function test_without_plugin_the_probes_see_the_overwrite(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('ord-2');
        $DB->set_field('user', 'firstname', 'Ada', ['id' => $learner->id]);
        lock_manager::lock($learner->id, 'firstname', lock_manager::SOURCE_TEACHER);

        $this->probed_launch(false, 'ord-2');
        $this->assertCount(1, sync_ordering_probe::$final);
        $this->assertSame('ord-2', sync_ordering_probe::$final[0]['firstname']);
        $this->assertSame('ord-2', sync_ordering_probe::$afterwrite[0]['firstname']);
    }

    /**
     * The lock is read live inside the hook, not from the row core read before dispatching: a teacher save that lands
     * between core's read and the hook decision is respected.
     */
    public function test_lock_set_after_core_read_is_respected(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('claimpriority', policy::CLAIMS_WIN, 'local_ltiidentity');
        $learner = launch_helper::launch('ord-3');
        $current = $DB->get_record('user', ['id' => $learner->id]);
        // Core has read $current; now the teacher's save completes, then the hook runs.
        lock_manager::lock($learner->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Fresh', ['id' => $learner->id]);
        $update = (object) ['id' => $learner->id, 'firstname' => 'Claim', 'lastname' => 'Name',
            'email' => 'c.ord3@example.org'];
        hook_callbacks::before_user_updated(new \core_user\hook\before_user_updated($update, $current));
        $this->assertFalse(property_exists($update, 'firstname'));
    }
}

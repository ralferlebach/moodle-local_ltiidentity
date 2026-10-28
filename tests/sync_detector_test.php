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
use local_ltiidentity\local\sync_detector;
use local_ltiidentity\tests\fixtures\launch_helper;
use local_ltiidentity\tests\fixtures\renamed_core_caller;

/**
 * IP-01: detection of the auth_lti sync without call stack inspection, with negative controls.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\sync_detector
 * @covers     \local_ltiidentity\hook_callbacks
 */
final class sync_detector_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once(__DIR__ . '/fixtures/renamed_core_caller.php');
        parent::setUpBeforeClass();
    }

    /**
     * Fingerprint recognised, in any property order.
     */
    public function test_fingerprint_positive(): void {
        $current = (object) ['id' => 5, 'auth' => 'lti'];
        $one = (object) ['id' => 5, 'firstname' => 'a', 'lastname' => 'b', 'email' => 'c'];
        $two = (object) ['email' => 'c', 'lastname' => 'b', 'id' => 5, 'firstname' => 'a'];
        $this->assertTrue(sync_detector::is_sync($one, $current));
        $this->assertTrue(sync_detector::is_sync($two, $current));
    }

    /**
     * Anything else is not the sync: other shapes, other auth, web service.
     */
    public function test_fingerprint_negative_controls(): void {
        $current = (object) ['id' => 5, 'auth' => 'lti'];
        $four = ['id' => 5, 'firstname' => 'a', 'lastname' => 'b', 'email' => 'c'];
        // Interactive form: more properties.
        $this->assertFalse(sync_detector::is_sync((object) ($four + ['city' => 'x']), $current));
        // Single field edits.
        $this->assertFalse(sync_detector::is_sync((object) ['id' => 5, 'firstname' => 'a'], $current));
        $this->assertFalse(sync_detector::is_sync((object) ['id' => 5, 'firstname' => 'a', 'lastname' => 'b'], $current));
        // Not an LTI account.
        $this->assertFalse(sync_detector::is_sync((object) $four, (object) ['id' => 5, 'auth' => 'manual']));
    }

    /**
     * The installed core still sends exactly the fingerprint (the "core change test" of IP-01).
     *
     * This is the executable form of the evidence for the Moodle version the test runs on. It runs on every
     * tree of the local matrix; on 5.0-5.2 it must be run by CI / the maintainer (no such tree is installed here).
     */
    public function test_installed_core_matches_fingerprint(): void {
        $this->assertSame(sync_detector::FINGERPRINT, sync_detector::installed_core_fingerprint());
    }

    /**
     * The source parser notices a changed core method.
     */
    public function test_fingerprint_from_source_detects_change(): void {
        $base = "'id' => \$user->id,\n 'firstname' => \$a,\n 'lastname' => \$b,\n 'email' => \$c";
        $old = "\$update = [\n $base\n ];";
        $new = "\$update = [\n $base,\n 'picture' => 1\n ];";
        $this->assertSame(sync_detector::FINGERPRINT, sync_detector::fingerprint_from_source($old));
        $this->assertNotSame(sync_detector::FINGERPRINT, sync_detector::fingerprint_from_source($new));
        $this->assertNull(sync_detector::fingerprint_from_source('no array here'));
    }

    /**
     * The status check reports OK for the installed core and an error when the callback is missing.
     */
    public function test_status_check(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/ltiidentity/lib.php');
        $this->resetAfterTest();
        $check = new \local_ltiidentity\check\sync_detection();
        $this->assertSame(\core\check\result::OK, $check->get_result()->get_status());
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance([]));
        try {
            $this->assertSame(\core\check\result::ERROR, $check->get_result()->get_status());
        } finally {
            \core\di::reset_container();
        }
        $this->assertCount(1, local_ltiidentity_status_checks());
    }

    /**
     * NEGATIVE CONTROL (red with the old debug_backtrace detection): a sync-shaped update from a caller with
     * another name and a call chain deeper than the old stack limit is still protected.
     */
    public function test_renamed_and_deep_core_caller_is_protected(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('sd-1');
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Pinned', ['id' => $user->id]);
        renamed_core_caller::refresh_identity($user, [
            'id' => $user->id, 'firstname' => 'Claimed', 'lastname' => $user->lastname, 'email' => $user->email,
        ]);
        $this->assertSame('Pinned', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * Direct profile changes are not blocked: admin-style edits of a locked field go through, whatever the shape
     * except the exact sync fingerprint.
     */
    public function test_direct_changes_are_not_blocked(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('sd-2');
        foreach (['firstname', 'lastname', 'email'] as $field) {
            lock_manager::lock($user->id, $field, lock_manager::SOURCE_TEACHER);
        }
        // Single-field edit.
        renamed_core_caller::refresh_identity($user, ['id' => $user->id, 'firstname' => 'Edited'], 0);
        $this->assertSame('Edited', $DB->get_field('user', 'firstname', ['id' => $user->id]));
        // Full form style edit with additional properties.
        renamed_core_caller::refresh_identity($user, [
            'id' => $user->id, 'firstname' => 'Form', 'lastname' => 'Edit', 'email' => 'form.edit@example.org', 'city' => 'Bonn',
        ], 0);
        $stored = $DB->get_record('user', ['id' => $user->id]);
        $this->assertSame(['Form', 'Edit', 'form.edit@example.org', 'Bonn'], [
            $stored->firstname, $stored->lastname, $stored->email, $stored->city,
        ]);
        // The same shape as the sync is not distinguishable: documented limit, it IS filtered.
        renamed_core_caller::refresh_identity($user, [
            'id' => $user->id, 'firstname' => 'Lost', 'lastname' => 'Lost', 'email' => 'lost@example.org',
        ], 0);
        $this->assertSame('Form', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * Layer 1 does not depend on the fingerprint: a placeholder never replaces a real value, whoever writes it.
     */
    public function test_placeholder_never_overwrites_real_value_for_any_caller(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('sd-3');
        $DB->set_field('user', 'lastname', 'Real Surname', ['id' => $user->id]);
        renamed_core_caller::refresh_identity($user, [
            'id' => $user->id, 'lastname' => launch_helper::ISSUER, 'city' => 'Bonn',
        ], 0);
        $stored = $DB->get_record('user', ['id' => $user->id]);
        $this->assertSame('Real Surname', $stored->lastname);
        $this->assertSame('Bonn', $stored->city);
    }

    /**
     * Non-LTI accounts are never touched, even with the sync shape and placeholder-like values.
     */
    public function test_non_lti_account_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        renamed_core_caller::refresh_identity($user, [
            'id' => $user->id, 'firstname' => 'N1', 'lastname' => 'N2', 'email' => 'n@example.org',
        ], 0);
        $this->assertSame('N1', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * The protected-sync event carries field names only, and only when enabled.
     */
    public function test_protected_sync_event(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('sd-4');
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Secret Pinned', ['id' => $user->id]);

        $sink = $this->redirectEvents();
        launch_helper::launch('sd-4');
        $none = array_filter($sink->get_events(), static function ($e): bool {
            return $e instanceof \local_ltiidentity\event\sync_protected;
        });
        $this->assertCount(0, $none, 'Off by default');
        $sink->close();

        set_config('logprotected', 1, 'local_ltiidentity');
        $sink = $this->redirectEvents();
        launch_helper::launch('sd-4');
        $events = array_filter($sink->get_events(), static function ($e): bool {
            return $e instanceof \local_ltiidentity\event\sync_protected;
        });
        $sink->close();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame(['firstname'], $event->other['fields']);
        $this->assertTrue($event->other['sync']);
        $this->assertStringNotContainsString('Secret Pinned', $event->get_description());
        $this->assertStringNotContainsString('sd-4', json_encode($event->get_data()));
    }

    /**
     * Performance: the hook costs no query for other accounts, a bounded number and no write for a protected LTI sync,
     * and one provenance write per applied field when a claim really changes a value.
     */
    public function test_hook_cost_is_bounded(): void {
        global $DB;
        $this->resetAfterTest();
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $lti = launch_helper::launch('sd-5');
        lock_manager::lock($lti->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $current = $DB->get_record('user', ['id' => $lti->id]);

        $hookmanual = new \core_user\hook\before_user_updated(
            (object) ['id' => $manual->id, 'firstname' => 'x', 'lastname' => 'y', 'email' => 'z@example.org'],
            $manual
        );
        $before = $DB->perf_get_reads();
        hook_callbacks::before_user_updated($hookmanual);
        $this->assertSame(0, $DB->perf_get_reads() - $before, 'Non-LTI account: no query');

        // Protected sync (the common relaunch): the locked value and two placeholders are filtered, nothing is written.
        $placeholderupdate = (object) [
            'id' => $lti->id,
            'firstname' => 'x',
            'lastname' => launch_helper::ISSUER,
            'email' => local\placeholder::placeholder_email(launch_helper::ISSUER, 'sd-5'),
        ];
        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        hook_callbacks::before_user_updated(new \core_user\hook\before_user_updated($placeholderupdate, $current));
        $reads = $DB->perf_get_reads() - $reads;
        // One query for the bindings; the configuration and lock preferences come from caches. No per-field queries.
        $this->assertLessThanOrEqual(3, $reads, "Protected LTI sync: $reads queries");
        $this->assertSame(0, $DB->perf_get_writes() - $writes, 'Protected LTI sync: no write');

        // A sync that applies two real claims records their provenance: at most one read and one write per field.
        $hooklti = new \core_user\hook\before_user_updated(
            (object) ['id' => $lti->id, 'firstname' => 'x', 'lastname' => 'y', 'email' => 'z@example.org'],
            $current
        );
        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        hook_callbacks::before_user_updated($hooklti);
        $reads = $DB->perf_get_reads() - $reads;
        $writes = $DB->perf_get_writes() - $writes;
        // Per applied field: core's set_user_preference() reads the row and the reload flag, writes the row and the
        // reload flag of other sessions (mark_user_preferences_changed). Bounded by the number of fields, never the user row.
        $this->assertLessThanOrEqual(3 + 2 * 3, $reads, "LTI sync applying two claims: $reads queries");
        $this->assertGreaterThan(0, $writes);
        $this->assertLessThanOrEqual(2 * 3, $writes, "Only provenance records and reload flags are written ($writes)");
        $this->assertSame(['firstname' => 'sd-5', 'lastname' => launch_helper::ISSUER], [
            'firstname' => $DB->get_field('user', 'firstname', ['id' => $lti->id]),
            'lastname' => $DB->get_field('user', 'lastname', ['id' => $lti->id]),
        ], 'The hook itself never writes the user row');
    }
}

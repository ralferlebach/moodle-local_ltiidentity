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

use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\provenance;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Upgrade paths: 0.2.0 (2026102501/02) -> 0.3.0 (2026102700) with the provenance backfill; the development rump
 * (0.1.0, 2026042200) is refused with an explanation.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_ltiidentity_upgrade
 * @covers     ::local_ltiidentity_backfill_provenance
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Load the upgrade code and the fixtures.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/ltiidentity/db/upgrade.php');
        require_once($CFG->dirroot . '/local/ltiidentity/db/upgradelib.php');
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Run the upgrade as Moodle would, with the installed version set back to $from.
     *
     * @param int $from The version upgraded from.
     * @return bool
     */
    private function upgrade(int $from): bool {
        set_config('version', $from, 'local_ltiidentity');
        ob_start();
        try {
            return xmldb_local_ltiidentity_upgrade($from);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * The plugin's preferences of a user without the provenance (as a 0.2.0 site has them).
     *
     * @param int $userid The user.
     */
    private function become_020(int $userid): void {
        foreach (lock_manager::fields() as $field) {
            unset_user_preference(provenance::PREFIX . $field, $userid);
        }
    }

    /**
     * The rump of the development branch is refused before anything is changed.
     *
     * @dataProvider rump_provider
     * @param int $oldversion The installed version.
     */
    public function test_rump_is_refused(int $oldversion): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('up-0');
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        $DB->set_field('user', 'firstname', 'Kept', ['id' => $user->id]);
        $this->become_020($user->id);
        try {
            $this->upgrade($oldversion);
            $this->fail('The upgrade from the rump must stop');
        } catch (\moodle_exception $e) {
            $this->assertSame('upgradefromrump', $e->errorcode);
            $this->assertStringContainsString((string) $oldversion, $e->getMessage());
            $this->assertStringContainsString('MIGRATION', $e->getMessage());
        }
        $this->assertEquals($oldversion, get_config('local_ltiidentity', 'version'), 'No savepoint was reached');
        $this->assertNull(provenance::get_record($user->id, 'firstname'), 'Nothing was migrated');
        set_config('version', 2026102700, 'local_ltiidentity');
    }

    /**
     * Versions of the rump (and anything older than the first build of this implementation).
     *
     * @return array
     */
    public static function rump_provider(): array {
        return [
            'rump 0.1.0' => [2026042200],
            'just before 0.2.0' => [2026102500],
            'zero' => [0],
        ];
    }

    /**
     * 0.2.0 -> 0.3.0: locks become provenance records for real values; placeholders, unverified addresses and users
     * without locks get none; the step is idempotent and reaches its savepoint.
     *
     * @dataProvider from_provider
     * @param int $from The 0.2.0 build.
     */
    public function test_upgrade_from_020_backfills_provenance(int $from): void {
        global $DB;
        $this->resetAfterTest();
        // Teacher-locked real names.
        $teacher = launch_helper::launch('up-1');
        $DB->set_field('user', 'firstname', 'Ada', ['id' => $teacher->id]);
        $DB->set_field('user', 'lastname', 'Lovelace', ['id' => $teacher->id]);
        lock_manager::lock($teacher->id, 'firstname', lock_manager::SOURCE_TEACHER);
        lock_manager::lock($teacher->id, 'lastname', lock_manager::SOURCE_TEACHER);
        // A self lock on a field that still holds the placeholder: no provenance is invented.
        lock_manager::lock($teacher->id, 'email', lock_manager::SOURCE_SELF);
        // Verified address: carried over only when the verification hash matches the current address.
        $verified = launch_helper::launch('up-2');
        $DB->set_field('user', 'email', 'verified.up2@example.org', ['id' => $verified->id]);
        lock_manager::lock($verified->id, 'email', lock_manager::SOURCE_VERIFIED);
        set_user_preference(email_verifier::VERIFIED, email_verifier::hash_email('verified.up2@example.org'), $verified->id);
        $stale = launch_helper::launch('up-3');
        $DB->set_field('user', 'email', 'changed.up3@example.org', ['id' => $stale->id]);
        lock_manager::lock($stale->id, 'email', lock_manager::SOURCE_VERIFIED);
        set_user_preference(email_verifier::VERIFIED, email_verifier::hash_email('old.up3@example.org'), $stale->id);
        // No lock at all.
        $plain = launch_helper::launch('up-4', ['given_name' => 'Plain']);
        foreach ([$teacher, $verified, $stale, $plain] as $user) {
            $this->become_020($user->id);
        }

        $this->assertTrue($this->upgrade($from));
        $this->assertEquals(2026102700, get_config('local_ltiidentity', 'version'));

        $fresh = static function (int $id) use ($DB): \stdClass {
            return $DB->get_record('user', ['id' => $id]);
        };
        $this->assertSame(provenance::TEACHER, provenance::of($fresh($teacher->id), 'firstname'));
        $this->assertSame(provenance::TEACHER, provenance::of($fresh($teacher->id), 'lastname'));
        $this->assertNull(provenance::get_record($teacher->id, 'email'), 'Placeholder value: nothing recorded');
        $this->assertSame(provenance::VERIFIED, provenance::of($fresh($verified->id), 'email'));
        $this->assertNull(provenance::get_record($stale->id, 'email'), 'Verification of another address: nothing');
        $this->assertNull(provenance::get_record($plain->id, 'firstname'));
        $this->assertSame(provenance::INITIAL, provenance::of($fresh($plain->id), 'firstname'));

        // Idempotent: a second run creates nothing and changes nothing.
        $before = $DB->get_records_select_menu(
            'user_preferences',
            $DB->sql_like('name', ':p'),
            ['p' => 'local\_ltiidentity\_src\_%'],
            'id',
            'id, value'
        );
        $this->assertTrue($this->upgrade($from));
        $this->assertSame(0, local_ltiidentity_backfill_provenance());
        $this->assertSame($before, $DB->get_records_select_menu(
            'user_preferences',
            $DB->sql_like('name', ':p'),
            ['p' => 'local\_ltiidentity\_src\_%'],
            'id',
            'id, value'
        ));
    }

    /**
     * The delivered 0.2.0 builds.
     *
     * @return array
     */
    public static function from_provider(): array {
        return [
            '0.2.0 build 2026102501' => [2026102501],
            '0.2.0 build 2026102502' => [2026102502],
        ];
    }

    /**
     * Existing provenance records are never overwritten by the backfill.
     */
    public function test_backfill_keeps_existing_records(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('up-5');
        $DB->set_field('user', 'firstname', 'Own', ['id' => $user->id]);
        lock_manager::lock($user->id, 'firstname', lock_manager::SOURCE_TEACHER);
        provenance::record($user->id, 'firstname', provenance::SELFSERVICE, 'Own');
        $this->assertSame(0, local_ltiidentity_backfill_provenance());
        $this->assertSame(provenance::SELFSERVICE, provenance::get_record($user->id, 'firstname')[0]);
    }

    /**
     * From the current version nothing runs.
     */
    public function test_upgrade_from_current_is_noop(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_ltiidentity_upgrade(2026102700));
        $this->assertEquals(2026102700, get_config('local_ltiidentity', 'version'));
    }
}

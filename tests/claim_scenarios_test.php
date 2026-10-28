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
use local_ltiidentity\local\placeholder;
use local_ltiidentity\local\policy;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\local\provenance;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * IP-02 negative cases with the real auth_plugin_lti::complete_login(): incomplete, contradictory and changing claims,
 * repeated launches and existing accounts.
 *
 * Every launch goes through the unmodified core chain complete_login() -> update_user_account() -> user_update_user()
 * (4.5) / \core\user::update_user() (5.3) -> before_user_updated hook (docs/DOCUMENTATION.md, section 1a).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\hook_callbacks
 * @covers     \local_ltiidentity\local\policy
 * @covers     \local_ltiidentity\local\provenance
 */
final class claim_scenarios_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Stored profile values of a user.
     *
     * @param int $userid The user.
     * @return \stdClass
     */
    private function stored(int $userid): \stdClass {
        global $DB;
        return $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, timemodified', MUST_EXIST);
    }

    /**
     * A teacher of a new course saves names for the LTI user (locks them).
     *
     * @param \stdClass $learner The LTI user.
     * @param string $first First name.
     * @param string $last Last name.
     */
    private function teacher_saves(\stdClass $learner, string $first, string $last): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        profile_service::save(
            $teacher->id,
            $learner->id,
            \context_course::instance($course->id),
            (object) ['firstname' => $first, 'lastname' => $last],
            lock_manager::SOURCE_TEACHER
        );
    }

    /**
     * The three policies.
     *
     * @return array
     */
    public static function policies(): array {
        return [
            'claims win' => [policy::CLAIMS_WIN],
            'local wins' => [policy::LOCAL_WINS],
            'fill placeholders' => [policy::FILL_PLACEHOLDERS],
        ];
    }

    /**
     * Incomplete claims: a transmitted claim is applied by policy, a missing one never resets a local value.
     *
     * @dataProvider policies
     * @param string $policy The configured policy.
     */
    public function test_incomplete_claims(string $policy): void {
        global $DB;
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        $learner = launch_helper::launch('inc-1');
        // Unlocked real local values for lastname and email (for example entered by an administrator earlier).
        $DB->set_field('user', 'lastname', 'Localname', ['id' => $learner->id]);
        $DB->set_field('user', 'email', 'local.inc1@example.org', ['id' => $learner->id]);

        // Only given_name is transmitted; family_name and email are missing.
        $after = launch_helper::launch('inc-1', ['given_name' => 'Ann']);

        $this->assertSame($policy === policy::LOCAL_WINS ? 'inc-1' : 'Ann', $after->firstname);
        $this->assertSame('Localname', $after->lastname, 'A missing family_name never resets a local value');
        $this->assertSame('local.inc1@example.org', $after->email, 'A missing email never resets a local address');
        $expected = $policy === policy::LOCAL_WINS ? provenance::PLACEHOLDER : provenance::LTI;
        $this->assertSame($expected, provenance::of($after, 'firstname'));
    }

    /**
     * Contradictory claims against unlocked real values under the strongest policy for claims (claims win).
     *
     * @dataProvider contradictory_provider
     * @param array $claims The transmitted claims.
     */
    public function test_contradictory_claims_do_not_reset_real_values(array $claims): void {
        global $DB;
        $this->resetAfterTest();
        set_config('claimpriority', policy::CLAIMS_WIN, 'local_ltiidentity');
        $learner = launch_helper::launch('con-1');
        $DB->set_field('user', 'firstname', 'Grace', ['id' => $learner->id]);
        $DB->set_field('user', 'lastname', 'Hopper', ['id' => $learner->id]);
        $DB->set_field('user', 'email', 'grace.con1@example.org', ['id' => $learner->id]);

        $after = launch_helper::launch('con-1', $claims);
        $this->assertSame('Grace', $after->firstname);
        $this->assertSame('Hopper', $after->lastname);
        $this->assertSame('grace.con1@example.org', $after->email);
    }

    /**
     * Claims that contradict themselves or the account: empty, white space, an invalid address, the placeholder.
     *
     * @return array
     */
    public static function contradictory_provider(): array {
        return [
            'empty strings' => [['given_name' => '', 'family_name' => '', 'email' => '']],
            'white space only' => [['given_name' => '   ', 'family_name' => "\t", 'email' => ' ']],
            'invalid e-mail address' => [['email' => 'not-an-address']],
            // Data providers run before setUpBeforeClass(), so the issuer of launch_helper is spelled out here.
            'claims equal the auth_lti placeholder' => [[
                'given_name' => 'con-1',
                'family_name' => 'https://platform.example.org',
                'email' => placeholder::placeholder_email('https://platform.example.org', 'con-1'),
            ]],
        ];
    }

    /**
     * A locked value wins against every contradicting real claim, under every policy, and keeps its provenance.
     *
     * @dataProvider policies
     * @param string $policy The configured policy.
     */
    public function test_lock_wins_against_contradicting_claims(string $policy): void {
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        $learner = launch_helper::launch('con-2');
        $this->teacher_saves($learner, 'Ada', 'Lovelace');
        $after = launch_helper::launch('con-2', ['given_name' => 'Augusta', 'family_name' => 'King']);
        $this->assertSame('Ada', $after->firstname);
        $this->assertSame('Lovelace', $after->lastname);
        $this->assertSame(provenance::TEACHER, provenance::of($after, 'firstname'));
    }

    /**
     * Changing claims over several launches follow the policy; a later lock freezes the value.
     *
     * @dataProvider changing_provider
     * @param string $policy The configured policy.
     * @param string[] $expected Expected first names after each launch.
     */
    public function test_changing_claims(string $policy, array $expected): void {
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        $learner = launch_helper::launch('chg-1');
        $seen = [];
        $seen[] = launch_helper::launch('chg-1', ['given_name' => 'Alpha'])->firstname;
        $seen[] = launch_helper::launch('chg-1', ['given_name' => 'Beta'])->firstname;
        $seen[] = launch_helper::launch('chg-1')->firstname;
        $this->teacher_saves($learner, 'Teach', 'Er');
        $seen[] = launch_helper::launch('chg-1', ['given_name' => 'Gamma'])->firstname;
        $seen[] = launch_helper::launch('chg-1')->firstname;
        $this->assertSame($expected, $seen);
    }

    /**
     * Cases for test_changing_claims: Alpha, Beta, no claim, (teacher locks "Teach"), Gamma, no claim.
     *
     * @return array
     */
    public static function changing_provider(): array {
        return [
            'claims win' => [policy::CLAIMS_WIN, ['Alpha', 'Beta', 'Beta', 'Teach', 'Teach']],
            'fill placeholders' => [policy::FILL_PLACEHOLDERS, ['Alpha', 'Alpha', 'Alpha', 'Teach', 'Teach']],
            'local wins' => [policy::LOCAL_WINS, ['chg-1', 'chg-1', 'chg-1', 'Teach', 'Teach']],
        ];
    }

    /**
     * Repeated launches without claims: same account, one binding, the user row is not written at all.
     */
    public function test_repeated_launch_writes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('rep-1');
        $this->teacher_saves($learner, 'Rita', 'Repeat');
        $DB->set_field('user', 'timemodified', 1000, ['id' => $learner->id]);
        $pluginprefs = function () use ($DB, $learner): array {
            return $DB->get_records_select_menu(
                'user_preferences',
                'userid = :id AND ' . $DB->sql_like('name', ':p'),
                ['id' => $learner->id, 'p' => 'local\\_ltiidentity\\_%'],
                'name',
                'name, value'
            );
        };
        $prefs = $pluginprefs();

        for ($i = 0; $i < 5; $i++) {
            $after = launch_helper::launch('rep-1');
            $this->assertSame($learner->id, $after->id);
        }
        $stored = $this->stored($learner->id);
        $this->assertSame('Rita', $stored->firstname);
        $this->assertSame('Repeat', $stored->lastname);
        $this->assertEquals(1000, $stored->timemodified, 'No write to the user row: every field was filtered');
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['userid' => $learner->id]));
        $this->assertCount(4, $prefs, 'Two locks and two provenance records');
        $this->assertSame($prefs, $pluginprefs(), 'Repeated launches do not change locks or provenance');
    }

    /**
     * Repeated launches with the same real claims: auth_lti finds no difference, nothing is dispatched or written.
     */
    public function test_repeated_launch_with_same_claims_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $claims = ['given_name' => 'Same', 'family_name' => 'Claims', 'email' => 'same.claims@example.org'];
        $learner = launch_helper::launch('rep-2', $claims);
        $this->assertSame('Same', $learner->firstname);
        $DB->set_field('user', 'timemodified', 2000, ['id' => $learner->id]);
        for ($i = 0; $i < 3; $i++) {
            launch_helper::launch('rep-2', $claims);
        }
        $this->assertEquals(2000, $this->stored($learner->id)->timemodified);
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['userid' => $learner->id]));
    }

    /**
     * Existing LTI account whose values came from an earlier claim: later launches without claims keep them.
     */
    public function test_existing_lti_account_keeps_claimed_values(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = launch_helper::launch('ex-1');
        $after = launch_helper::launch('ex-1', ['given_name' => 'Claimed', 'family_name' => 'Earlier',
            'email' => 'claimed.ex1@example.org']);
        $this->assertSame(provenance::LTI, provenance::of($after, 'firstname'));
        $again = launch_helper::launch('ex-1');
        $this->assertSame('Claimed', $again->firstname);
        $this->assertSame('Earlier', $again->lastname);
        $this->assertSame('claimed.ex1@example.org', $again->email);
        $this->assertSame([], lock_manager::locked_fields($learner->id), 'Nothing was locked');
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['userid' => $learner->id]));
    }

    /**
     * An existing manual account with the claimed address is neither linked nor changed; the launch gets its own account.
     */
    public function test_existing_manual_account_with_same_email_is_not_touched(): void {
        global $DB;
        $this->resetAfterTest();
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual', 'firstname' => 'Mary',
            'lastname' => 'Manual', 'email' => 'shared.ex2@example.org']);
        $before = $DB->get_record('user', ['id' => $manual->id]);

        $learner = launch_helper::launch('ex-2', ['given_name' => 'Lia', 'family_name' => 'Launch',
            'email' => 'shared.ex2@example.org']);
        launch_helper::launch('ex-2', ['given_name' => 'Lia2']);

        $this->assertNotEquals($manual->id, $learner->id);
        $this->assertSame('lti', $learner->auth);
        $this->assertEquals($before, $DB->get_record('user', ['id' => $manual->id]), 'The manual account is unchanged');
        $this->assertFalse($DB->record_exists('auth_lti_linked_login', ['userid' => $manual->id]));
        $this->assertSame(0, $DB->count_records_select(
            'user_preferences',
            'userid = :id AND ' . $DB->sql_like('name', ':p'),
            ['id' => $manual->id, 'p' => 'local\_ltiidentity\_%']
        ));
    }

    /**
     * An existing non-LTI account bound to the platform identity (provisioning mode "link existing account"): core
     * does not sync its profile and the plugin does nothing either.
     */
    public function test_existing_bound_manual_account_is_left_to_core(): void {
        global $DB;
        $this->resetAfterTest();
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual', 'firstname' => 'Bound',
            'lastname' => 'Manual', 'email' => 'bound.ex3@example.org']);
        $auth = get_auth_plugin('lti');
        $auth->create_user_binding(launch_helper::ISSUER, 'ex-3', $manual->id);
        $fields = 'id, auth, username, firstname, lastname, email';
        $before = $DB->get_record('user', ['id' => $manual->id], $fields);

        $after = launch_helper::launch('ex-3', ['given_name' => 'Other', 'family_name' => 'Claim']);
        $this->assertEquals($manual->id, $after->id, 'The binding of core is used as is');
        // The login itself updates the login times; the profile fields stay as they were.
        $this->assertEquals($before, $DB->get_record('user', ['id' => $manual->id], $fields));
        $this->assertSame([], lock_manager::locked_fields($manual->id));
        $this->assertNull(provenance::get_record($manual->id, 'firstname'));
    }
}

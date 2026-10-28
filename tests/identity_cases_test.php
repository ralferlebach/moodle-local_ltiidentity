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
use local_ltiidentity\local\locking;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\task\purge_stale_data;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * IP-02: identity, e-mail and responsibility cases (duplicates, concurrency, expiry, repeated sync, presentation).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\email_verifier
 * @covers     \local_ltiidentity\local\locking
 * @covers     \local_ltiidentity\task\purge_stale_data
 */
final class identity_cases_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once(__DIR__ . '/fixtures/throwing_probe.php');
        parent::setUpBeforeClass();
    }

    /**
     * Use file locks: the default PostgreSQL/MySQL session locks are re-entrant within one connection, so a second
     * lock holder inside the same test process is only visible with flock-style locks.
     */
    private function use_file_locks(): void {
        global $CFG;
        $CFG->lock_factory = '\\core\\lock\\file_lock_factory';
    }

    /**
     * Request a change and return the token taken from the delivered message and the raw message.
     *
     * @param \stdClass $user The LTI user.
     * @param string $email The new address.
     * @return array [token, message body]
     */
    private function request_and_capture(\stdClass $user, string $email): array {
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $result = email_verifier::request($user->id, $user, $email);
        $this->assertContains($result, [email_verifier::REQUESTED, email_verifier::REQUESTED_DUPLICATE]);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
        $this->assertSame(1, preg_match('/token=([0-9a-f]{64})/', $body, $m));
        return [$m[1], $body];
    }

    /**
     * Same e-mail claim for two first-created LTI accounts and an existing local account: three accounts, no merge,
     * no association, no change of the local account.
     */
    public function test_same_email_as_local_account_creates_separate_lti_account(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        $local = $this->getDataGenerator()->create_user(['email' => 'shared@example.org', 'auth' => 'manual']);
        $one = launch_helper::launch('ic-1', ['given_name' => 'A', 'family_name' => 'B', 'email' => 'shared@example.org']);
        $two = launch_helper::launch('ic-2', ['given_name' => 'C', 'family_name' => 'D', 'email' => 'shared@example.org']);
        $this->assertCount(3, array_unique([$local->id, $one->id, $two->id]));
        $this->assertSame('lti', $one->auth);
        $this->assertSame('manual', $DB->get_field('user', 'auth', ['id' => $local->id]));
        $this->assertSame(2, $DB->count_records('auth_lti_linked_login'));
        $this->assertSame(0, $DB->count_records('auth_lti_linked_login', ['userid' => $local->id]));
    }

    /**
     * Requesting an address that an LTI account of a launch already holds: block (default and core setting off).
     */
    public function test_request_for_address_held_by_lti_account(): void {
        $this->resetAfterTest();
        $one = launch_helper::launch('ic-3', ['given_name' => 'A', 'family_name' => 'B', 'email' => 'held@example.org']);
        $two = launch_helper::launch('ic-4');
        set_config('allowaccountssameemail', 0);
        $this->assertSame(email_verifier::DUPLICATE, email_verifier::request($two->id, $two, 'HELD@example.org'));
        set_config('allowaccountssameemail', 1);
        set_config('emailduplicate', 'block', 'local_ltiidentity');
        $this->assertSame(email_verifier::DUPLICATE, email_verifier::request($two->id, $two, 'held@example.org'));
        $this->assertNull(email_verifier::get_pending($two->id));
        $this->assertSame('held@example.org', \core_user::get_user($one->id)->email);
    }

    /**
     * An unconfirmed address is never verified: not after the request, not after an LTI claim, not after a
     * release of the lock followed by a claim that carries the same address.
     */
    public function test_unconfirmed_value_is_never_verified(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-5');
        [$token] = $this->request_and_capture($user, 'wanted@example.org');
        $this->assertFalse(email_verifier::is_verified(\core_user::get_user($user->id)));
        // A claim with the same address arrives by launch: it is stored by core, but the address was not confirmed
        // through the link, so it is not marked verified.
        $after = launch_helper::launch('ic-5', ['given_name' => 'X', 'family_name' => 'Y', 'email' => 'wanted@example.org']);
        $this->assertSame('wanted@example.org', $after->email);
        $this->assertFalse(email_verifier::is_verified($after));
        // Confirmation afterwards verifies it.
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertTrue(email_verifier::is_verified(\core_user::get_user($user->id)));
    }

    /**
     * Repeated syncs keep a verified address and its flag, with and without further claims, for every policy.
     *
     * @dataProvider policy_provider
     * @param string $policy The claim priority policy.
     */
    public function test_repeated_sync_keeps_verified_email(string $policy): void {
        global $DB;
        $this->resetAfterTest();
        set_config('claimpriority', $policy, 'local_ltiidentity');
        $user = launch_helper::launch('ic-6');
        [$token] = $this->request_and_capture($user, 'verified@example.org');
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        for ($i = 0; $i < 4; $i++) {
            $after = launch_helper::launch('ic-6', $i % 2 ? ['email' => 'other.claim@example.org'] : []);
            $this->assertSame('verified@example.org', $after->email);
            $this->assertTrue(email_verifier::is_verified($after));
        }
        $this->assertSame(0, $DB->count_records(audit::TABLE, ['action' => 'release']), 'A sync writes no audit rows');
    }

    /**
     * Data provider: all claim priority policies.
     *
     * @return array
     */
    public static function policy_provider(): array {
        return ['claimswin' => ['claimswin'], 'localwins' => ['localwins'], 'fill' => ['fillplaceholders']];
    }

    /**
     * Expired token: rejected, request removed, address unchanged; a new request works afterwards.
     */
    public function test_expired_token(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-7');
        $old = $user->email;
        [$token] = $this->request_and_capture($user, 'late@example.org');
        $pending = email_verifier::get_pending($user->id);
        $pending->x = time() - 1;
        set_user_preference(email_verifier::PENDING, json_encode($pending), $user->id);
        $this->assertSame(email_verifier::EXPIRED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertNull(email_verifier::get_pending($user->id));
        $this->assertSame($old, $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $token, $user->id));
    }

    /**
     * Concurrent confirmation: while another process holds the account lock the confirmation is refused as busy
     * and the token stays untouched; afterwards exactly one confirmation succeeds and the replay fails.
     */
    public function test_concurrent_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->use_file_locks();
        set_config('locktimeout', 1, 'local_ltiidentity');
        $user = launch_helper::launch('ic-8');
        [$token] = $this->request_and_capture($user, 'race@example.org');

        $factory = \core\lock\lock_config::get_lock_factory('local_ltiidentity');
        $held = $factory->get_lock('email_' . $user->id, 5);
        $this->assertNotFalse($held);
        $this->assertSame(email_verifier::BUSY, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertSame(email_verifier::BUSY, email_verifier::request($user->id, $user, 'second@example.org'));
        $held->release();
        $this->assertNotNull(email_verifier::get_pending($user->id), 'Busy does not consume the token');

        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertSame('race@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertSame(1, $DB->count_records(audit::TABLE, ['action' => 'emailverify']));
    }

    /**
     * A profile save waits for the account lock and reports busy instead of interleaving.
     */
    public function test_profile_save_busy(): void {
        $this->resetAfterTest();
        $this->use_file_locks();
        set_config('locktimeout', 1, 'local_ltiidentity');
        $user = launch_helper::launch('ic-9');
        $factory = \core\lock\lock_config::get_lock_factory('local_ltiidentity');
        $held = $factory->get_lock('profile_' . $user->id, 5);
        try {
            $this->expectException(\moodle_exception::class);
            profile_service::save(
                $user->id,
                $user->id,
                \context_user::instance($user->id),
                (object) ['firstname' => 'Ada', 'lastname' => 'Lovelace'],
                lock_manager::SOURCE_SELF
            );
        } finally {
            $held->release();
        }
    }

    /**
     * The lock helper: result passes through, lock is released after an exception, timeout bounds.
     */
    public function test_locking_helper(): void {
        $this->resetAfterTest();
        $this->assertSame(10, locking::timeout());
        set_config('locktimeout', 0, 'local_ltiidentity');
        $this->assertSame(10, locking::timeout());
        set_config('locktimeout', 61, 'local_ltiidentity');
        $this->assertSame(10, locking::timeout());
        set_config('locktimeout', 2, 'local_ltiidentity');
        $this->assertSame(2, locking::timeout());
        $this->assertSame('ok', locking::run('res', static fn() => 'ok', $acquired));
        $this->assertTrue($acquired);
        try {
            locking::run('res', static function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame('again', locking::run('res', static fn() => 'again', $acquired), 'Lock was released');
    }

    /**
     * A failing write rolls everything back: the token stays valid, nothing is half applied, a retry succeeds.
     */
    public function test_failed_write_keeps_token_usable(): void {
        global $DB, $CFG;
        // A rollback inside the per-test reset transaction would poison it; the code under test rolls back for real.
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-10');
        $old = $user->email;
        // The marker address passes validation, the throwing callback makes the core write fail.
        [$token] = $this->request_and_capture($user, 'fail.write@example.org');
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance([
            'local_ltiidentity' => $CFG->dirroot . '/local/ltiidentity/db/hooks.php',
            'local_ltiidentity_throw' => __DIR__ . '/fixtures/throwing_hooks.php',
        ]));
        try {
            try {
                email_verifier::confirm($user->id, $token, $user->id);
                $this->fail('The write must fail');
            } catch (\moodle_exception $e) {
                $this->assertSame('invaliduserid', $e->errorcode);
            }
        } finally {
            \core\di::reset_container();
        }
        $this->assertSame($old, $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertNotNull(email_verifier::get_pending($user->id), 'Token not consumed by the failed attempt');
        $this->assertFalse(email_verifier::is_verified(\core_user::get_user($user->id)));
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
    }

    /**
     * An address that became forbidden between request and confirmation is refused.
     */
    public function test_address_forbidden_after_request(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-11');
        [$token] = $this->request_and_capture($user, 'someone@blocked.example.net');
        set_config('denyemailaddresses', 'blocked.example.net');
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertNull(email_verifier::get_pending($user->id));
    }

    /**
     * No plain token or address is persisted: preference holds the hash, audit rows hold no values, the mail goes
     * only to the new address and the link is built from wwwroot.
     */
    public function test_no_clear_data_persisted_or_logged(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-12');
        $context = \context_user::instance($user->id);
        profile_service::save($user->id, $user->id, $context, (object) [
            'firstname' => 'Margaret', 'lastname' => 'Hamilton', 'email' => 'm.hamilton@example.org',
        ], lock_manager::SOURCE_SELF);
        // The cooldown does not apply to the first request; read the token from a second request after the cooldown.
        $pending = email_verifier::get_pending($user->id);
        $this->assertNotNull($pending);
        $raw = (string) $DB->get_field('user_preferences', 'value', [
            'userid' => $user->id, 'name' => email_verifier::PENDING,
        ]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pending->h);
        $this->assertStringNotContainsString('token', $raw);
        foreach ($DB->get_records(audit::TABLE) as $row) {
            $serialised = json_encode($row);
            $this->assertStringNotContainsString('Margaret', $serialised);
            $this->assertStringNotContainsString('hamilton', strtolower($serialised));
            $this->assertStringNotContainsString('ic-12', $serialised);
        }
        $link = email_verifier::build_link($user->id, str_repeat('a', 64));
        $this->assertStringStartsWith($CFG->wwwroot . '/local/ltiidentity/confirmemail.php', $link);
    }

    /**
     * Placeholders are not shown as names: the form helper blanks them and the list uses a neutral label.
     */
    public function test_presentation_hides_placeholders(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('ic-13');
        $this->assertTrue(local\placeholder::is_placeholder($user->id, 'firstname', $user->firstname));
        $this->assertSame(
            ['firstname', 'lastname', 'email'],
            local\placeholder::placeholder_fields($user)
        );
        $this->assertSame('ic-13', local\placeholder::display_id($user->id));
        $label = get_string('nameplaceholder', 'local_ltiidentity', 'ic-13');
        $this->assertStringContainsString('ic-13', $label);
        $this->assertStringNotContainsString(launch_helper::ISSUER, $label);
    }

    /**
     * The scheduled task removes expired and undecodable requests, keeps valid ones, and applies the retention.
     */
    public function test_purge_task(): void {
        global $DB;
        $this->resetAfterTest();
        $valid = launch_helper::launch('ic-14');
        $expired = launch_helper::launch('ic-15');
        $broken = launch_helper::launch('ic-16');
        $base = ['e' => 'a@example.org', 'h' => str_repeat('0', 64), 'c' => time(), 'a' => 0, 'r' => 1];
        set_user_preference(email_verifier::PENDING, json_encode($base + ['x' => time() + 3600]), $valid->id);
        set_user_preference(email_verifier::PENDING, json_encode($base + ['x' => time() - 3600]), $expired->id);
        set_user_preference(email_verifier::PENDING, 'not json', $broken->id);
        $this->assertSame(2, purge_stale_data::purge_expired_requests(time()));
        $this->assertNotNull(email_verifier::get_pending($valid->id));
        $this->assertSame(0, $DB->count_records('user_preferences', ['name' => email_verifier::PENDING, 'userid' => $expired->id]));

        $context = \context_system::instance();
        $oldid = audit::record(1, $valid->id, $context, audit::ACTION_UPDATE, ['firstname'], 'unlocked', 'locked');
        $DB->set_field(audit::TABLE, 'timecreated', time() - 800 * DAYSECS, ['id' => $oldid]);
        audit::record(1, $valid->id, $context, audit::ACTION_UPDATE, ['firstname'], 'unlocked', 'locked');
        set_config('auditretention', 0, 'local_ltiidentity');
        $this->assertSame(0, purge_stale_data::purge_audit(time()));
        set_config('auditretention', 730, 'local_ltiidentity');
        $this->assertSame(1, purge_stale_data::purge_audit(time()));
        $this->assertSame(1, $DB->count_records(audit::TABLE));
        $this->expectOutputRegex('/removed 0 expired/');
        (new purge_stale_data())->execute();
    }
}

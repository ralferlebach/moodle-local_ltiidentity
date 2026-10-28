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
use local_ltiidentity\local\profile_service;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Tests for the e-mail verification flow and the duplicate policy (IP-P04, IP-P05, IP-L05, IP-L06).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\email_verifier
 */
final class email_verifier_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Request a change and return the token taken from the delivered message.
     *
     * @param \stdClass $user The LTI user.
     * @param string $email The new address.
     * @return string The plain token.
     */
    private function request_and_capture(\stdClass $user, string $email): string {
        unset_config('noemailever');
        $mailsink = $this->redirectEmails();
        $result = email_verifier::request($user->id, $user, $email);
        $this->assertContains($result, [email_verifier::REQUESTED, email_verifier::REQUESTED_DUPLICATE]);
        $messages = $mailsink->get_messages();
        $mailsink->close();
        $this->assertCount(1, $messages);
        $this->assertSame($email, $messages[0]->to);
        $body = quoted_printable_decode($messages[0]->body);
        $this->assertSame(1, preg_match('/token=([0-9a-f]{64})/', $body, $m), 'Token in the link');
        return $m[1];
    }

    /**
     * Full happy path: the address changes only after the confirmation.
     */
    public function test_request_and_confirm(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-1');
        $oldemail = $user->email;
        $token = $this->request_and_capture($user, 'new.address@example.org');

        $this->assertSame($oldemail, $DB->get_field('user', 'email', ['id' => $user->id]), 'Unverified: unchanged');
        $this->assertFalse(email_verifier::is_verified(\core_user::get_user($user->id)));
        $this->assertNotNull(email_verifier::get_pending($user->id));

        $sink = $this->redirectEvents();
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        $events = array_filter($sink->get_events(), static function ($e): bool {
            return $e instanceof \local_ltiidentity\event\email_verified;
        });
        $sink->close();
        $this->assertCount(1, $events);

        $stored = \core_user::get_user($user->id);
        $this->assertSame('new.address@example.org', $stored->email);
        $this->assertTrue(email_verifier::is_verified($stored));
        $this->assertSame('verified', lock_manager::provenance($user->id, 'email'));
        $this->assertNull(email_verifier::get_pending($user->id));
        $this->assertSame(1, $DB->count_records(audit::TABLE, ['action' => 'emailverify']));
    }

    /**
     * The stored token is a hash, never the token itself or anything usable.
     */
    public function test_token_is_stored_hashed(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-2');
        $token = $this->request_and_capture($user, 'hash@example.org');
        $raw = $DB->get_field(
            'user_preferences',
            'value',
            ['userid' => $user->id, 'name' => email_verifier::PENDING],
            MUST_EXIST
        );
        $this->assertStringNotContainsString($token, $raw);
        $this->assertStringContainsString(hash('sha256', $token), $raw);
    }

    /**
     * Anti-replay: the link works once.
     */
    public function test_token_is_one_time(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('e-3');
        $token = $this->request_and_capture($user, 'once@example.org');
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $token, $user->id));
    }

    /**
     * Expired tokens are refused and removed.
     */
    public function test_token_expires(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-4');
        $token = $this->request_and_capture($user, 'late@example.org');
        $pending = email_verifier::get_pending($user->id);
        $pending->x = time() - 1;
        set_user_preference(email_verifier::PENDING, json_encode($pending), $user->id);

        $this->assertSame(email_verifier::EXPIRED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertNull(email_verifier::get_pending($user->id));
        $this->assertNotSame('late@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
    }

    /**
     * The configured lifetime is applied.
     */
    public function test_configured_lifetime(): void {
        $this->resetAfterTest();
        set_config('emailtokenlifetime', 7200, 'local_ltiidentity');
        $user = launch_helper::launch('e-5');
        $this->request_and_capture($user, 'life@example.org');
        $pending = email_verifier::get_pending($user->id);
        $this->assertEqualsWithDelta(time() + 7200, $pending->x, 5);
    }

    /**
     * Wrong tokens never change anything; repeated guessing kills the request.
     */
    public function test_wrong_token_and_attempt_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-6');
        $token = $this->request_and_capture($user, 'guess@example.org');
        for ($i = 1; $i < email_verifier::MAXATTEMPTS; $i++) {
            $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, str_repeat('0', 64), $user->id));
            $this->assertNotNull(email_verifier::get_pending($user->id));
        }
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, str_repeat('0', 64), $user->id));
        $this->assertNull(email_verifier::get_pending($user->id), 'Request invalidated after too many attempts');
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertNotSame('guess@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
    }

    /**
     * Only the logged-in owner of the account can confirm (CSRF/session fixation guard on service level).
     */
    public function test_other_user_cannot_confirm(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-7');
        $attacker = launch_helper::launch('e-7-attacker');
        $token = $this->request_and_capture($user, 'mine@example.org');
        $this->assertSame(email_verifier::WRONGUSER, email_verifier::confirm($user->id, $token, $attacker->id));
        $this->assertSame(email_verifier::WRONGUSER, email_verifier::confirm($user->id, $token, 0));
        $this->assertNotNull(email_verifier::get_pending($user->id), 'A rejected attempt does not consume the request');
        $this->assertNotSame('mine@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
    }

    /**
     * A new request replaces the old one: the old token is dead.
     */
    public function test_new_request_invalidates_old_token(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('e-8');
        $old = $this->request_and_capture($user, 'first@example.org');
        $pending = email_verifier::get_pending($user->id);
        $pending->c = time() - email_verifier::COOLDOWN - 1;
        set_user_preference(email_verifier::PENDING, json_encode($pending), $user->id);
        $new = $this->request_and_capture($user, 'second@example.org');
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, $old, $user->id));
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $new, $user->id));
        $this->assertSame('second@example.org', \core_user::get_user($user->id)->email);
    }

    /**
     * Requests are rate limited.
     */
    public function test_cooldown(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('e-9');
        $this->request_and_capture($user, 'rate@example.org');
        $this->assertSame(email_verifier::TOOSOON, email_verifier::request($user->id, $user, 'rate2@example.org'));
    }

    /**
     * Host spoofing: the link is built from $CFG->wwwroot, never from request headers.
     */
    public function test_link_ignores_spoofed_host_headers(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-10');
        $_SERVER['HTTP_HOST'] = 'evil.example.net';
        $_SERVER['SERVER_NAME'] = 'evil.example.net';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'evil.example.net';
        $_SERVER['HTTP_REFERER'] = 'https://evil.example.net/';
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        email_verifier::request($user->id, $user, 'spoof@example.org');
        $messages = $sink->get_messages();
        $sink->close();
        $body = quoted_printable_decode($messages[0]->body);
        $this->assertStringNotContainsString('evil.example.net', $body);
        $this->assertStringContainsString($CFG->wwwroot . '/local/ltiidentity/confirmemail.php?userid=', $body);
        $link = email_verifier::build_link(5, 'abc');
        $this->assertStringStartsWith($CFG->wwwroot . '/local/ltiidentity/confirmemail.php?', $link);
    }

    /**
     * Invalid or unwanted addresses never reach the mail queue.
     *
     * @dataProvider invalid_email_provider
     * @param string $email The address.
     */
    public function test_invalid_addresses(string $email): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('e-11');
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $this->assertSame(email_verifier::INVALID, email_verifier::request($user->id, $user, $email));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
        $this->assertNull(email_verifier::get_pending($user->id));
    }

    /**
     * Cases for test_invalid_addresses.
     *
     * @return array
     */
    public static function invalid_email_provider(): array {
        return [
            'empty' => [''],
            'no at' => ['nope'],
            'header injection' => ["a@example.org\nBcc: x@example.org"],
            'auth_lti placeholder' => ['enrol_lti_13_' . str_repeat('a', 40) . '@example.com'],
        ];
    }

    /**
     * The same address is reported as unchanged.
     */
    public function test_unchanged_address(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('e-12');
        $DB->set_field('user', 'email', 'current@example.org', ['id' => $user->id]);
        $user = \core_user::get_user($user->id);
        $this->assertSame(email_verifier::UNCHANGED, email_verifier::request($user->id, $user, 'Current@Example.org'));
    }

    /**
     * Duplicate policy times the core setting.
     *
     * @dataProvider duplicate_provider
     * @param string $policy Plugin setting.
     * @param int $core Value of allowaccountssameemail.
     * @param string $expectedrequest Expected request result.
     * @param string $expectedeffective Expected effective policy.
     */
    public function test_duplicate_policy(string $policy, int $core, string $expectedrequest, string $expectedeffective): void {
        $this->resetAfterTest();
        set_config('allowaccountssameemail', $core);
        set_config('emailduplicate', $policy, 'local_ltiidentity');
        $this->getDataGenerator()->create_user(['email' => 'shared@example.org']);
        $user = launch_helper::launch('e-13');
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $result = email_verifier::request($user->id, $user, 'Shared@Example.org');
        $sent = count($sink->get_messages());
        $sink->close();
        $this->assertSame($expectedeffective, email_verifier::effective_duplicate_policy());
        $this->assertSame($expectedrequest, $result);
        $this->assertSame($expectedrequest === email_verifier::DUPLICATE ? 0 : 1, $sent);
    }

    /**
     * Cases for test_duplicate_policy.
     *
     * @return array
     */
    public static function duplicate_provider(): array {
        return [
            'allow, core allows' => ['allow', 1, email_verifier::REQUESTED_DUPLICATE, 'allow'],
            'warn, core allows' => ['warn', 1, email_verifier::REQUESTED_DUPLICATE, 'warn'],
            'block, core allows' => ['block', 1, email_verifier::DUPLICATE, 'block'],
            'allow, core forbids' => ['allow', 0, email_verifier::DUPLICATE, 'block'],
            'warn, core forbids' => ['warn', 0, email_verifier::DUPLICATE, 'block'],
        ];
    }

    /**
     * A duplicate that appears between request and confirmation is re-checked.
     */
    public function test_duplicate_rechecked_at_confirmation(): void {
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        set_config('emailduplicate', 'block', 'local_ltiidentity');
        $user = launch_helper::launch('e-14');
        $token = $this->request_and_capture($user, 'race@example.org');
        $this->getDataGenerator()->create_user(['email' => 'race@example.org']);
        $this->assertSame(email_verifier::DUPLICATE, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertNotSame('race@example.org', \core_user::get_user($user->id)->email);
    }

    /**
     * An address changed by other means is no longer reported as verified.
     */
    public function test_verification_is_bound_to_the_address(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $user = launch_helper::launch('e-15');
        $token = $this->request_and_capture($user, 'bound@example.org');
        email_verifier::confirm($user->id, $token, $user->id);
        $this->assertTrue(email_verifier::is_verified(\core_user::get_user($user->id)));
        local\user_writer::update((object) ['id' => $user->id, 'email' => 'other@example.org']);
        $this->assertFalse(email_verifier::is_verified(\core_user::get_user($user->id)));
    }

    /**
     * Saving the form with an e-mail goes through the verifier and leaves the address untouched.
     */
    public function test_service_save_requests_but_does_not_apply_email(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('e-16');
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $result = profile_service::save(
            $user->id,
            $user->id,
            \context_user::instance($user->id),
            (object) ['firstname' => 'Self', 'lastname' => 'Made', 'email' => 'form@example.org'],
            lock_manager::SOURCE_SELF
        );
        $this->assertSame(1, count($sink->get_messages()));
        $sink->close();
        $this->assertSame(email_verifier::REQUESTED, $result->email);
        $this->assertNotSame('form@example.org', \core_user::get_user($user->id)->email);
        $this->assertFalse(lock_manager::is_locked($user->id, 'email'));
    }

    /**
     * Two LTI identities with the same e-mail stay two accounts: no association, no password reset (IP-P05, IP-L09).
     */
    public function test_same_email_two_identities_no_association_no_reset(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/login/lib.php');
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        $claims = ['given_name' => 'Same', 'family_name' => 'Person', 'email' => 'same.person@example.org'];
        $one = launch_helper::launch('dup-1', $claims);
        $two = launch_helper::launch('dup-2', $claims);
        $again = launch_helper::launch('dup-1', $claims);

        $this->assertNotEquals($one->id, $two->id);
        $this->assertEquals($one->id, $again->id);
        $this->assertSame($one->email, $two->email);
        $this->assertSame(2, $DB->count_records('auth_lti_linked_login'));
        $this->assertEquals($one->id, $DB->get_field('auth_lti_linked_login', 'userid', ['sub' => 'dup-1']));
        $this->assertEquals($two->id, $DB->get_field('auth_lti_linked_login', 'userid', ['sub' => 'dup-2']));

        // Password reset by e-mail must not create a reset token for LTI accounts. Core only sends the
        // informational "password change info" message, which contains no reset link.
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        core_login_process_password_reset('', 'same.person@example.org');
        foreach ($sink->get_messages() as $message) {
            $this->assertStringNotContainsString('token=', quoted_printable_decode($message->body));
        }
        $sink->close();
        $this->assertSame(0, $DB->count_records('user_password_resets'));
    }
}

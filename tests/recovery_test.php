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
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * IP-04 account recovery: a pending (unconfirmed) address is never usable for core's forgot-password path, a confirmed
 * change invalidates reset links sent to the old address, and duplicates never link accounts.
 *
 * Core path under test (identical lookup in 4.5 and 5.3): login/lib.php core_login_process_password_reset() finds the
 * account by user.email (or username) only; the pending address of this plugin lives in a user preference.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\email_verifier
 */
final class recovery_test extends \advanced_testcase {
    /**
     * Load the fixtures and core's login library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once($CFG->dirroot . '/login/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Request an address change and return the token from the delivered message.
     *
     * @param \stdClass $user The account.
     * @param string $email The requested address.
     * @return string
     */
    private function request(\stdClass $user, string $email): string {
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        $this->assertContains(
            email_verifier::request($user->id, $user, $email),
            [email_verifier::REQUESTED, email_verifier::REQUESTED_DUPLICATE]
        );
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertSame(1, preg_match('/token=([0-9a-f]{64})/', quoted_printable_decode($messages[0]->body), $m));
        return $m[1];
    }

    /**
     * Run core's forgot-password processing and return the recipients of the mails it sent.
     *
     * @param string $username Username or empty.
     * @param string $email Address or empty.
     * @return string[] Recipient addresses.
     */
    private function forgot_password(string $username, string $email): array {
        unset_config('noemailever');
        $sink = $this->redirectEmails();
        ob_start();
        try {
            core_login_process_password_reset($username, $email);
        } finally {
            ob_end_clean();
        }
        $to = array_map(static function ($message): string {
            return $message->to;
        }, $sink->get_messages());
        $sink->close();
        return $to;
    }

    /**
     * Pending address of an LTI account: core finds no account for it, sends nothing, creates no reset token.
     */
    public function test_pending_address_is_unknown_to_forgot_password(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('rc-1', ['given_name' => 'Rec', 'family_name' => 'One', 'email' => 'old.rc1@example.org']);
        $this->request($user, 'pending.rc1@example.org');

        $this->assertSame([], $this->forgot_password('', 'pending.rc1@example.org'));
        $this->assertSame(0, $DB->count_records('user_password_resets'));
        // Asking for the account by its username reaches only the stored address, never the pending one.
        $this->assertSame(['old.rc1@example.org'], $this->forgot_password($user->username, ''));
        $this->assertSame(0, $DB->count_records('user_password_resets'), 'auth_lti cannot reset passwords');
    }

    /**
     * Even an account that can reset its password (auth switched to manual by an administrator) gets no reset link
     * at a pending address; the link goes to the stored address only.
     */
    public function test_pending_address_unusable_when_account_can_reset(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('rc-2', ['given_name' => 'Rec', 'family_name' => 'Two', 'email' => 'old.rc2@example.org']);
        $this->request($user, 'attacker.rc2@example.org');
        $DB->set_field('user', 'auth', 'manual', ['id' => $user->id]);

        $this->assertSame([], $this->forgot_password('', 'attacker.rc2@example.org'));
        $this->assertSame(0, $DB->count_records('user_password_resets'));
        $this->assertSame(['old.rc2@example.org'], $this->forgot_password('', 'old.rc2@example.org'));
        $this->assertSame(1, $DB->count_records('user_password_resets', ['userid' => $user->id]));
    }

    /**
     * Confirming a new address removes reset tokens that were sent to the previous address (4.5 and 5.3).
     */
    public function test_confirmation_invalidates_outstanding_reset_tokens(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('rc-3', ['email' => 'old.rc3@example.org']);
        $token = $this->request($user, 'new.rc3@example.org');
        $DB->insert_record('user_password_resets', (object) [
            'userid' => $user->id, 'timerequested' => time(), 'timererequested' => 0, 'token' => 'oldaddresstoken',
        ]);
        $other = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_password_resets', (object) [
            'userid' => $other->id, 'timerequested' => time(), 'timererequested' => 0, 'token' => 'othertoken',
        ]);

        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));
        $this->assertSame('new.rc3@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('user_password_resets', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('user_password_resets', ['userid' => $other->id]), 'Other accounts untouched');
    }

    /**
     * A failed confirmation (wrong token) leaves reset state and the address alone.
     */
    public function test_failed_confirmation_changes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $user = launch_helper::launch('rc-4', ['email' => 'old.rc4@example.org']);
        $this->request($user, 'new.rc4@example.org');
        $DB->insert_record('user_password_resets', (object) [
            'userid' => $user->id, 'timerequested' => time(), 'timererequested' => 0, 'token' => 'keep',
        ]);
        $this->assertSame(email_verifier::INVALID, email_verifier::confirm($user->id, str_repeat('0', 64), $user->id));
        $this->assertSame('old.rc4@example.org', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertTrue($DB->record_exists('user_password_resets', ['userid' => $user->id]));
    }

    /**
     * Duplicate address under the warn policy: two separate accounts, bindings unchanged, nothing linked or merged.
     */
    public function test_duplicate_is_never_linked(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        set_config('emailduplicate', email_verifier::DUP_WARN, 'local_ltiidentity');
        $manual = $this->getDataGenerator()->create_user(['auth' => 'manual', 'email' => 'shared.rc5@example.org']);
        $user = launch_helper::launch('rc-5');
        $token = $this->request($user, 'shared.rc5@example.org');
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($user->id, $token, $user->id));

        $this->assertNotEquals($manual->id, $user->id);
        $this->assertSame('manual', $DB->get_field('user', 'auth', ['id' => $manual->id]));
        $this->assertSame('lti', $DB->get_field('user', 'auth', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('auth_lti_linked_login', ['userid' => $manual->id]));
        $this->assertSame(1, $DB->count_records('auth_lti_linked_login', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('user', ['deleted' => 1]));

        // Under the default policy (block) the same request is refused before anything is stored.
        set_config('emailduplicate', email_verifier::DUP_BLOCK, 'local_ltiidentity');
        $third = launch_helper::launch('rc-6');
        $this->assertSame(email_verifier::DUPLICATE, email_verifier::request($third->id, $third, 'shared.rc5@example.org'));
        $this->assertNull(email_verifier::get_pending($third->id));
    }
}

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

namespace local_ltiidentity\local;

/**
 * E-mail change with verification: hashed one-time token, expiry, anti-replay, duplicate policy.
 *
 * The stored e-mail address of the account does not change until the owner confirmed the link,
 * so an unconfirmed address is never used by Moodle (password reset, notifications): core's forgot-password path
 * (core_login_process_password_reset() in login/lib.php of 4.5 and 5.3) looks accounts up by user.email only, and
 * the pending address lives in a user preference. Confirming the change removes outstanding password reset tokens. The pending
 * request lives in a user preference as JSON with only the SHA-256 hash of the token.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_verifier {
    /** @var string Preference holding the pending request. */
    public const PENDING = 'local_ltiidentity_emailpending';
    /** @var string Preference holding the hash of the verified address. */
    public const VERIFIED = 'local_ltiidentity_emailverified';
    /** @var int Wrong token attempts before the request is invalidated. */
    public const MAXATTEMPTS = 5;
    /** @var int Minimum seconds between two requests for the same user. */
    public const COOLDOWN = 60;

    /** @var string Duplicate policy: allow. */
    public const DUP_ALLOW = 'allow';
    /** @var string Duplicate policy: warn. */
    public const DUP_WARN = 'warn';
    /** @var string Duplicate policy: block. */
    public const DUP_BLOCK = 'block';

    /** @var string Result: link sent. */
    public const REQUESTED = 'requested';
    /** @var string Result: link sent, address also used by another account. */
    public const REQUESTED_DUPLICATE = 'requestedduplicate';
    /** @var string Result: address invalid or not allowed. */
    public const INVALID = 'invalid';
    /** @var string Result: duplicate address blocked by policy. */
    public const DUPLICATE = 'duplicate';
    /** @var string Result: a request was made moments ago. */
    public const TOOSOON = 'toosoon';
    /** @var string Result: address equals the current one. */
    public const UNCHANGED = 'unchanged';
    /** @var string Result: confirmed. */
    public const CONFIRMED = 'confirmed';
    /** @var string Result: token expired. */
    public const EXPIRED = 'expired';
    /** @var string Result: the logged-in user is not the account owner. */
    public const WRONGUSER = 'wronguser';
    /** @var string Result: another request for the same account is being processed (lock timeout). */
    public const BUSY = 'busy';

    /**
     * The configured duplicate policy, never weaker than the core setting.
     *
     * With $CFG->allowaccountssameemail disabled Moodle itself forbids duplicates, so the effective
     * policy is block regardless of the plugin setting.
     *
     * @return string
     */
    public static function effective_duplicate_policy(): string {
        global $CFG;
        if (empty($CFG->allowaccountssameemail)) {
            return self::DUP_BLOCK;
        }
        $value = (string) get_config('local_ltiidentity', 'emailduplicate');
        return in_array($value, [self::DUP_ALLOW, self::DUP_WARN, self::DUP_BLOCK], true) ? $value : self::DUP_BLOCK;
    }

    /**
     * Whether another live account uses the address.
     *
     * @param int $userid The account that wants the address.
     * @param string $email The address.
     * @return bool
     */
    public static function is_used_by_other(int $userid, string $email): bool {
        global $DB, $CFG;
        $select = $DB->sql_equal('email', ':email', false) . ' AND id <> :id AND deleted = 0 AND mnethostid = :mnet';
        return $DB->record_exists_select('user', $select, [
            'email' => $email, 'id' => $userid, 'mnet' => $CFG->mnet_localhost_id,
        ]);
    }

    /**
     * Whether an address may be requested at all (syntax and site allow/deny lists).
     *
     * @param string $email The address.
     * @return bool
     */
    public static function is_acceptable(string $email): bool {
        if ($email === '' || !validate_email($email) || \core_user::clean_field($email, 'email') !== $email) {
            return false;
        }
        if (preg_match('/^enrol_lti_13_[0-9a-f]{40}@example\.com$/i', $email)) {
            return false;
        }
        return !email_is_not_allowed($email);
    }

    /**
     * Start an e-mail change: store the hashed token and send the link to the new address.
     *
     * @param int $actorid The user who asked for the change.
     * @param \stdClass $user The account whose address changes.
     * @param string $email The requested address.
     * @return string One of the result constants.
     */
    public static function request(int $actorid, \stdClass $user, string $email): string {
        $result = locking::run('email_' . (int) $user->id, static function () use ($actorid, $user, $email): string {
            return self::do_request($actorid, $user, $email);
        }, $acquired);
        return $acquired ? $result : self::BUSY;
    }

    /**
     * The body of request(), running under the per-user lock.
     *
     * @param int $actorid The user who asked for the change.
     * @param \stdClass $user The account whose address changes.
     * @param string $email The requested address.
     * @return string One of the result constants.
     */
    private static function do_request(int $actorid, \stdClass $user, string $email): string {
        global $SITE;
        $email = trim($email);
        if (strtolower($email) === strtolower((string) $user->email)) {
            return self::UNCHANGED;
        }
        if (!self::is_acceptable($email)) {
            return self::INVALID;
        }
        $duplicate = self::is_used_by_other((int) $user->id, $email);
        if ($duplicate && self::effective_duplicate_policy() === self::DUP_BLOCK) {
            return self::DUPLICATE;
        }
        $pending = self::get_pending((int) $user->id);
        if ($pending && time() - (int) $pending->c < self::COOLDOWN) {
            return self::TOOSOON;
        }

        $token = bin2hex(random_bytes(32));
        $lifetime = (int) get_config('local_ltiidentity', 'emailtokenlifetime');
        if ($lifetime <= 0) {
            $lifetime = DAYSECS;
        }
        set_user_preference(self::PENDING, json_encode([
            'e' => $email,
            'h' => hash('sha256', $token),
            'x' => time() + $lifetime,
            'c' => time(),
            'a' => 0,
            'r' => $actorid,
        ]), (int) $user->id);

        $link = self::build_link((int) $user->id, $token);
        $a = (object) [
            'sitename' => format_string($SITE->fullname),
            'link' => $link,
            'hours' => (int) ceil($lifetime / HOURSECS),
        ];
        $recipient = clone $user;
        $recipient->email = $email;
        $subject = get_string('emailverify_subject', 'local_ltiidentity', $a);
        $text = get_string('emailverify_body', 'local_ltiidentity', $a);
        email_to_user($recipient, \core_user::get_noreply_user(), $subject, $text);

        return $duplicate ? self::REQUESTED_DUPLICATE : self::REQUESTED;
    }

    /**
     * Build the confirmation link from $CFG->wwwroot only, never from request headers.
     *
     * @param int $userid The account.
     * @param string $token The plain token.
     * @return string
     */
    public static function build_link(int $userid, string $token): string {
        $url = new \moodle_url('/local/ltiidentity/confirmemail.php', ['userid' => $userid, 'token' => $token]);
        return $url->out(false);
    }

    /**
     * Complete an e-mail change.
     *
     * @param int $userid The account that gets the address.
     * @param string $token The plain token from the link.
     * @param int $confirmerid The logged-in user who submits the confirmation.
     * @return string One of CONFIRMED, INVALID, EXPIRED, DUPLICATE, WRONGUSER, BUSY.
     */
    public static function confirm(int $userid, string $token, int $confirmerid): string {
        if ($confirmerid !== $userid || $userid <= 0) {
            return self::WRONGUSER;
        }
        $result = locking::run('email_' . $userid, static function () use ($userid, $token): string {
            return self::do_confirm($userid, $token);
        }, $acquired);
        return $acquired ? $result : self::BUSY;
    }

    /**
     * The body of confirm(), running under the per-user lock.
     *
     * @param int $userid The account that gets the address.
     * @param string $token The plain token from the link.
     * @return string One of the result constants.
     */
    private static function do_confirm(int $userid, string $token): string {
        global $DB, $USER;
        $user = access::get_lti_user($userid);
        $pending = self::get_pending($userid);
        if (!$user || !$pending) {
            return self::INVALID;
        }
        if (time() > (int) $pending->x) {
            unset_user_preference(self::PENDING, $userid);
            return self::EXPIRED;
        }
        if (!hash_equals((string) $pending->h, hash('sha256', $token))) {
            $pending->a = (int) $pending->a + 1;
            if ($pending->a >= self::MAXATTEMPTS) {
                unset_user_preference(self::PENDING, $userid);
            } else {
                set_user_preference(self::PENDING, json_encode($pending), $userid);
            }
            return self::INVALID;
        }
        // Anti-replay: under the account lock, address change, verification flag, field lock and consumption of
        // the request are one transaction. If the write fails the link stays usable and nothing is half applied;
        // a second confirmation can only run after the first released the lock and then finds no request.
        $email = (string) $pending->e;
        if (!self::is_acceptable($email)) {
            // Allow/deny lists may have changed since the request.
            unset_user_preference(self::PENDING, $userid);
            return self::INVALID;
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            if (self::is_used_by_other($userid, $email) && self::effective_duplicate_policy() === self::DUP_BLOCK) {
                unset_user_preference(self::PENDING, $userid);
                $transaction->allow_commit();
                return self::DUPLICATE;
            }
            $oldflag = lock_manager::is_locked($userid, 'email') ? 'locked' : 'unlocked';
            user_writer::update((object) ['id' => $userid, 'email' => $email]);
            set_user_preference(self::VERIFIED, self::hash_email($email), $userid);
            lock_manager::lock($userid, 'email', lock_manager::SOURCE_VERIFIED);
            provenance::record($userid, 'email', provenance::VERIFIED, $email);
            // A password reset link that was sent to the previous address must not survive the change of address.
            // Moodle 5.3 does this in \core\user::update_user(); Moodle 4.5 does not, so it is done here for both.
            $DB->delete_records('user_password_resets', ['userid' => $userid]);
            unset_user_preference(self::PENDING, $userid);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if ($userid == $USER->id) {
                // The session copy of the preferences may hold values of the rolled back transaction.
                unset($USER->preference);
            }
            $transaction->rollback($e);
        }

        $context = \context_user::instance($userid);
        audit::record($userid, $userid, $context, audit::ACTION_EMAILVERIFY, ['email'], $oldflag, 'locked');
        \local_ltiidentity\event\email_verified::create([
            'context' => $context,
            'objectid' => $userid,
            'userid' => $userid,
            'relateduserid' => $userid,
        ])->trigger();
        return self::CONFIRMED;
    }

    /**
     * Whether the stored address was confirmed through the link.
     *
     * @param \stdClass $user A user record.
     * @return bool
     */
    public static function is_verified(\stdClass $user): bool {
        $stored = (string) get_user_preferences(self::VERIFIED, '', (int) $user->id);
        return $stored !== '' && hash_equals($stored, self::hash_email((string) $user->email));
    }

    /**
     * The decoded pending request, or null.
     *
     * @param int $userid The account.
     * @return \stdClass|null
     */
    public static function get_pending(int $userid): ?\stdClass {
        $raw = get_user_preferences(self::PENDING, '', $userid);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw);
        if (!is_object($data) || !isset($data->e, $data->h, $data->x, $data->c)) {
            return null;
        }
        $data->a = (int) ($data->a ?? 0);
        return $data;
    }

    /**
     * Hash used to remember which address was verified.
     *
     * @param string $email The address.
     * @return string
     */
    public static function hash_email(string $email): string {
        return hash('sha256', strtolower(trim($email)));
    }
}

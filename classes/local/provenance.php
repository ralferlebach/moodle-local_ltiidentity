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
 * Field provenance: where the current value of firstname, lastname and email came from (IP-04).
 *
 * Stored in core user preferences only (IP-P02), one per field: `local_ltiidentity_src_<field>` =
 * `<source>:<sha256 of the value>`. The hash binds the record to the value it describes. When the stored value no longer
 * matches (changed by a path that does not record provenance, or a write that failed after the record), the record is
 * ignored and the value is reported as "not recorded"; a provenance is therefore never shown for a value it does not
 * describe. No value is stored, only its hash.
 *
 * Who records what:
 * - the before_user_updated hook: "lti" for a value the auth_lti launch / membership sync writes, "other" for a value
 *   any other writer (administrator profile form, upload, web service) writes into an LTI account;
 * - profile_service: "teacher" or "self" for saved names;
 * - email_verifier: "verified" for an address confirmed through the one-time link.
 *
 * Derived states (not stored): "placeholder" when the value is the auth_lti placeholder, "initial" when no record exists
 * (unchanged since the account was created, normally by the first LTI launch), "unknown" when a record exists for
 * another value.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provenance {
    /** @var string Preference prefix. */
    public const PREFIX = 'local_ltiidentity_src_';

    /** @var string Value transmitted by the LTI platform (launch or membership sync). */
    public const LTI = 'lti';
    /** @var string Value entered by a teacher through this plugin. */
    public const TEACHER = 'teacher';
    /** @var string Value entered by the user through this plugin. */
    public const SELFSERVICE = 'self';
    /** @var string Address confirmed by its owner through the one-time link. */
    public const VERIFIED = 'verified';
    /** @var string Value written by another path (administrator profile form, upload, web service). */
    public const OTHER = 'other';
    /** @var string Derived: the auth_lti placeholder (claim not transmitted). */
    public const PLACEHOLDER = 'placeholder';
    /** @var string Derived: no record, value unchanged since the account was created. */
    public const INITIAL = 'initial';
    /** @var string Derived: a record exists but describes another value. */
    public const UNKNOWN = 'unknown';

    /**
     * Sources that can be recorded.
     *
     * @return string[]
     */
    public static function recordable(): array {
        return [self::LTI, self::TEACHER, self::SELFSERVICE, self::VERIFIED, self::OTHER];
    }

    /**
     * Hash that binds a record to a value.
     *
     * E-mail addresses are compared case-insensitively (as Moodle does), names exactly.
     *
     * @param string $field firstname, lastname or email.
     * @param string $value The value.
     * @return string
     */
    public static function hash_value(string $field, string $value): string {
        if ($field === 'email') {
            $value = strtolower(trim($value));
        }
        return hash('sha256', $value);
    }

    /**
     * Record the provenance of a value that is (about to be) stored.
     *
     * @param int $userid The user.
     * @param string $field firstname, lastname or email.
     * @param string $source One of the recordable sources.
     * @param string $value The value the record describes.
     */
    public static function record(int $userid, string $field, string $source, string $value): void {
        if (!in_array($field, placeholder::FIELDS, true)) {
            throw new \coding_exception('Unknown provenance field');
        }
        if (!in_array($source, self::recordable(), true)) {
            throw new \coding_exception('Unknown provenance source');
        }
        $encoded = $source . ':' . self::hash_value($field, $value);
        if ((string) get_user_preferences(self::PREFIX . $field, '', $userid) !== $encoded) {
            set_user_preference(self::PREFIX . $field, $encoded, $userid);
        }
    }

    /**
     * The raw record of a field.
     *
     * @param int $userid The user.
     * @param string $field The field.
     * @return array|null [source, hash] or null.
     */
    public static function get_record(int $userid, string $field): ?array {
        $raw = (string) get_user_preferences(self::PREFIX . $field, '', $userid);
        if (!preg_match('/^([a-z]+):([0-9a-f]{64})$/', $raw, $match) || !in_array($match[1], self::recordable(), true)) {
            return null;
        }
        return [$match[1], $match[2]];
    }

    /**
     * The provenance of the current value of one field.
     *
     * @param \stdClass $user User record with id and the field.
     * @param string $field firstname, lastname or email.
     * @param \stdClass[]|null $bindings Result of placeholder::bindings() (fetched when null).
     * @return string A recordable source or one of PLACEHOLDER, INITIAL, UNKNOWN.
     */
    public static function of(\stdClass $user, string $field, ?array $bindings = null): string {
        $value = (string) ($user->$field ?? '');
        $bindings ??= placeholder::bindings((int) $user->id);
        if (placeholder::matches($bindings, $field, $value)) {
            return self::PLACEHOLDER;
        }
        $record = self::get_record((int) $user->id, $field);
        if ($record === null) {
            return self::INITIAL;
        }
        return hash_equals($record[1], self::hash_value($field, $value)) ? $record[0] : self::UNKNOWN;
    }

    /**
     * Provenance of all three fields.
     *
     * @param \stdClass $user User record with id, firstname, lastname, email.
     * @return string[] field => provenance
     */
    public static function all_of(\stdClass $user): array {
        $bindings = placeholder::bindings((int) $user->id);
        $result = [];
        foreach (placeholder::FIELDS as $field) {
            $result[$field] = self::of($user, $field, $bindings);
        }
        return $result;
    }

    /**
     * Whether the viewer may see the provenance of the user's fields.
     *
     * The user themselves; a holder of local/ltiidentity:viewprovenance (system); a teacher who may manage the user in
     * the given course (local/ltiidentity:manage, same rules as the teacher page). Only for LTI accounts.
     *
     * @param \stdClass $user The profile owner.
     * @param int $viewerid The viewing user.
     * @param \stdClass|null $course The course of a course profile, or null.
     * @return bool
     */
    public static function can_view(\stdClass $user, int $viewerid, ?\stdClass $course = null): bool {
        if ($viewerid <= 0 || !access::get_lti_user((int) $user->id)) {
            return false;
        }
        if ((int) $user->id === $viewerid) {
            return true;
        }
        if (has_capability('local/ltiidentity:viewprovenance', \context_system::instance(), $viewerid)) {
            return true;
        }
        if ($course && (int) $course->id !== SITEID) {
            return access::can_manage_user(\context_course::instance((int) $course->id), (int) $user->id, $viewerid);
        }
        return false;
    }
}

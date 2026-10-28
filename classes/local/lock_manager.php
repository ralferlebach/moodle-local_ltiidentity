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
 * Provenance and lock flags, stored only in core user preferences (IP-P02).
 *
 * The preference value is the provenance of the locked value: teacher, self or verified.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_manager {
    /** @var string Preference prefix of the per-field lock. */
    public const PREFIX = 'local_ltiidentity_lock_';
    /** @var string Provenance: entered by a teacher. */
    public const SOURCE_TEACHER = 'teacher';
    /** @var string Provenance: entered by the user. */
    public const SOURCE_SELF = 'self';
    /** @var string Provenance: confirmed through the e-mail link. */
    public const SOURCE_VERIFIED = 'verified';

    /**
     * Fields that can be locked.
     *
     * @return string[]
     */
    public static function fields(): array {
        return placeholder::FIELDS;
    }

    /**
     * Whether a field is locked.
     *
     * @param int $userid The user.
     * @param string $field The field name.
     * @return bool
     */
    public static function is_locked(int $userid, string $field): bool {
        return self::provenance($userid, $field) !== '';
    }

    /**
     * Provenance of a locked field, empty string when not locked.
     *
     * @param int $userid The user.
     * @param string $field The field name.
     * @return string
     */
    public static function provenance(int $userid, string $field): string {
        if (!in_array($field, self::fields(), true)) {
            return '';
        }
        return (string) get_user_preferences(self::PREFIX . $field, '', $userid);
    }

    /**
     * Lock a field.
     *
     * @param int $userid The user.
     * @param string $field The field name.
     * @param string $source One of the SOURCE_ constants.
     */
    public static function lock(int $userid, string $field, string $source): void {
        if (!in_array($field, self::fields(), true)) {
            throw new \coding_exception('Unknown lockable field');
        }
        if (!in_array($source, [self::SOURCE_TEACHER, self::SOURCE_SELF, self::SOURCE_VERIFIED], true)) {
            throw new \coding_exception('Unknown lock source');
        }
        set_user_preference(self::PREFIX . $field, $source, $userid);
    }

    /**
     * Release a lock.
     *
     * @param int $userid The user.
     * @param string $field The field name.
     */
    public static function release(int $userid, string $field): void {
        if (in_array($field, self::fields(), true)) {
            unset_user_preference(self::PREFIX . $field, $userid);
        }
    }

    /**
     * Names of all locked fields of a user.
     *
     * @param int $userid The user.
     * @return string[]
     */
    public static function locked_fields(int $userid): array {
        return array_values(array_filter(self::fields(), static function (string $field) use ($userid): bool {
            return self::is_locked($userid, $field);
        }));
    }
}

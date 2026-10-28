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
 * Detection of the privacy-friendly placeholders written by auth_lti.
 *
 * auth_lti fills missing claims with: firstname = sub, lastname = iss and
 * email = 'enrol_lti_13_' followed by sha1(iss_sub) at the example.com domain. The placeholders are derived from the
 * bindings of the user in auth_lti_linked_login, which is read only.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placeholder {
    /** @var string Fields that carry placeholders. */
    public const FIELDS = ['firstname', 'lastname', 'email'];

    /**
     * Whether a value is the auth_lti placeholder for the given field of the given user.
     *
     * @param int $userid The user the value belongs to.
     * @param string $field firstname, lastname or email.
     * @param string $value The value to test.
     * @return bool
     */
    public static function is_placeholder(int $userid, string $field, string $value): bool {
        if (!in_array($field, self::FIELDS, true) || $value === '') {
            return false;
        }
        return self::matches(self::bindings($userid), $field, $value);
    }

    /**
     * The auth_lti bindings (issuer and sub) of a user, read in one query.
     *
     * Callers that test several fields (the hook) fetch this once and use matches().
     *
     * @param int $userid The user.
     * @return \stdClass[]
     */
    public static function bindings(int $userid): array {
        global $DB;
        return $DB->get_records('auth_lti_linked_login', ['userid' => $userid], '', 'id, issuer, sub');
    }

    /**
     * The platform user identifier (sub) of the first binding: the only handle a teacher has to tell apart two
     * accounts whose names were not transmitted. It is pseudonymous platform data, not a name.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function display_id(int $userid): string {
        $bindings = self::bindings($userid);
        $first = reset($bindings);
        return $first ? $first->sub : '';
    }

    /**
     * Whether a value equals the placeholder of one of the given bindings.
     *
     * @param \stdClass[] $bindings Result of bindings().
     * @param string $field firstname, lastname or email.
     * @param string $value The value to test.
     * @return bool
     */
    public static function matches(array $bindings, string $field, string $value): bool {
        if ($value === '') {
            return false;
        }
        foreach ($bindings as $binding) {
            switch ($field) {
                case 'firstname':
                    if ($value === $binding->sub) {
                        return true;
                    }
                    break;
                case 'lastname':
                    if ($value === $binding->issuer) {
                        return true;
                    }
                    break;
                case 'email':
                    if (strtolower($value) === self::placeholder_email($binding->issuer, $binding->sub)) {
                        return true;
                    }
                    break;
            }
        }
        return false;
    }

    /**
     * The placeholder e-mail address auth_lti derives for an identity.
     *
     * @param string $issuer The platform issuer.
     * @param string $sub The platform user identifier.
     * @return string
     */
    public static function placeholder_email(string $issuer, string $sub): string {
        return 'enrol_lti_13_' . sha1($issuer . '_' . $sub) . '@example.com';
    }

    /**
     * Which of the three fields of a stored user currently hold a placeholder.
     *
     * @param \stdClass $user A user record with id, firstname, lastname and email.
     * @return string[] Field names.
     */
    public static function placeholder_fields(\stdClass $user): array {
        $result = [];
        foreach (self::FIELDS as $field) {
            if (isset($user->$field) && self::is_placeholder((int) $user->id, $field, (string) $user->$field)) {
                $result[] = $field;
            }
        }
        return $result;
    }
}

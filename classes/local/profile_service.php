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
 * Writes profile changes through the core user API only, then lock, audit and event.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_service {
    /**
     * Validate a name value for the profile form and the service.
     *
     * @param int $userid The user the value is for.
     * @param string $field firstname or lastname.
     * @param string $value The submitted value.
     * @return string Empty when valid, otherwise a lang string identifier of this plugin.
     */
    public static function validate_name(int $userid, string $field, string $value): string {
        $value = trim($value);
        if ($value === '') {
            return 'errornameempty';
        }
        if (\core_text::strlen($value) > 100) {
            return 'errornametoolong';
        }
        if (placeholder::is_placeholder($userid, $field, $value)) {
            return 'errorplaceholdervalue';
        }
        return '';
    }

    /**
     * Save names (and request an e-mail change) for a user and lock the saved fields.
     *
     * Authorisation is the caller's job; this method trusts it. Names are validated again here.
     *
     * @param int $actorid The acting user.
     * @param int $targetid The user being edited.
     * @param \context $context Course context for teachers, user context for self-service.
     * @param \stdClass $data Object with firstname, lastname and optionally email.
     * @param string $source lock_manager::SOURCE_TEACHER or SOURCE_SELF.
     * @return \stdClass With properties saved (field names), email (result constant or empty string).
     */
    public static function save(int $actorid, int $targetid, \context $context, \stdClass $data, string $source): \stdClass {
        $result = locking::run('profile_' . $targetid, static function () use ($actorid, $targetid, $context, $data, $source) {
            return self::do_save($actorid, $targetid, $context, $data, $source);
        }, $acquired);
        if (!$acquired) {
            throw new \moodle_exception('errorbusy', 'local_ltiidentity');
        }
        return $result;
    }

    /**
     * The body of save(), running under the per-user lock.
     *
     * @param int $actorid The acting user.
     * @param int $targetid The user being edited.
     * @param \context $context Course context for teachers, user context for self-service.
     * @param \stdClass $data Object with firstname, lastname and optionally email.
     * @param string $source The lock provenance.
     * @return \stdClass
     */
    private static function do_save(int $actorid, int $targetid, \context $context, \stdClass $data, string $source): \stdClass {
        $user = access::get_lti_user($targetid);
        if (!$user) {
            throw new \moodle_exception('cannotmanageuser', 'local_ltiidentity');
        }
        $update = ['id' => $targetid];
        $saved = [];
        $values = [];
        foreach (['firstname', 'lastname'] as $field) {
            if (!isset($data->$field)) {
                continue;
            }
            $value = trim(clean_param($data->$field, PARAM_TEXT));
            $error = self::validate_name($targetid, $field, $value);
            if ($error !== '') {
                throw new \invalid_parameter_exception(get_string($error, 'local_ltiidentity'));
            }
            if ($value !== $user->$field) {
                $update[$field] = $value;
            }
            $saved[] = $field;
            $values[$field] = $value;
        }
        $oldflag = count(array_filter($saved, static function (string $field) use ($targetid): bool {
            return lock_manager::is_locked($targetid, $field);
        })) === count($saved) && $saved ? 'locked' : 'unlocked';

        if (count($update) > 1) {
            user_writer::update((object) $update);
        }
        foreach ($saved as $field) {
            lock_manager::lock($targetid, $field, $source);
            provenance::record($targetid, $field, $source, $values[$field]);
        }
        if ($saved) {
            audit::record($actorid, $targetid, $context, audit::ACTION_UPDATE, $saved, $oldflag, 'locked');
            \local_ltiidentity\event\profile_updated::create([
                'context' => $context,
                'objectid' => $targetid,
                'userid' => $actorid,
                'relateduserid' => $targetid,
                'other' => ['fields' => $saved],
            ])->trigger();
        }

        $result = (object) ['saved' => $saved, 'email' => ''];
        $email = isset($data->email) ? trim((string) $data->email) : '';
        if ($email !== '') {
            $result->email = email_verifier::request($actorid, $user, $email);
            if (in_array($result->email, [email_verifier::REQUESTED, email_verifier::REQUESTED_DUPLICATE], true)) {
                audit::record(
                    $actorid,
                    $targetid,
                    $context,
                    audit::ACTION_EMAILREQUEST,
                    ['email'],
                    'unverified',
                    'pending'
                );
            }
        }
        return $result;
    }

    /**
     * Release the lock of one field (administrators only).
     *
     * @param int $actorid The acting user.
     * @param int $targetid The user whose lock is released.
     * @param string $field The field name.
     */
    public static function release_lock(int $actorid, int $targetid, string $field): void {
        if (!access::can_release($actorid)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/ltiidentity:releaselock',
                'nopermissions',
                ''
            );
        }
        if (!in_array($field, lock_manager::fields(), true) || !lock_manager::is_locked($targetid, $field)) {
            throw new \invalid_parameter_exception('Field is not locked');
        }
        lock_manager::release($targetid, $field);
        $context = \context_user::instance($targetid);
        audit::record($actorid, $targetid, $context, audit::ACTION_RELEASE, [$field], 'locked', 'unlocked');
        \local_ltiidentity\event\lock_released::create([
            'context' => $context,
            'objectid' => $targetid,
            'userid' => $actorid,
            'relateduserid' => $targetid,
            'other' => ['field' => $field],
        ])->trigger();
    }
}

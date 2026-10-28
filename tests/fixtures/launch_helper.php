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

namespace local_ltiidentity\tests\fixtures;

/**
 * Test helper: performs real auth_plugin_lti launches and teacher/course set-up.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launch_helper {
    /** @var string Platform issuer used in the tests. */
    public const ISSUER = 'https://platform.example.org';

    /**
     * Build launch data as found in a verified LTI 1.3 resource link launch JWT.
     *
     * @param string $sub The platform user id.
     * @param array $claims Optional given_name, family_name, email.
     * @param string $issuer The issuer.
     * @return array
     */
    public static function launchdata(string $sub, array $claims = [], string $issuer = self::ISSUER): array {
        return array_merge([
            'iss' => $issuer,
            'aud' => '123',
            'sub' => $sub,
            'exp' => time() + 60,
            'nonce' => 'nonce-' . $sub,
        ], $claims);
    }

    /**
     * Run the real auth_plugin_lti::complete_login() in automatic provisioning mode.
     *
     * The session is reset first so that every call behaves like a fresh browser session.
     *
     * @param string $sub The platform user id.
     * @param array $claims Optional given_name, family_name, email.
     * @param string $issuer The issuer.
     * @return \stdClass The stored Moodle user that is now logged in.
     */
    public static function launch(string $sub, array $claims = [], string $issuer = self::ISSUER): \stdClass {
        global $USER, $DB;
        \core\session\manager::set_user((object) ['id' => 0]);
        $auth = get_auth_plugin('lti');
        // The error control operator hides the session header warning that complete_user_login() raises
        // without a real HTTP session; core's own tests do the same (see lib/tests/moodlelib_test.php).
        @$auth->complete_login(
            self::launchdata($sub, $claims, $issuer),
            new \moodle_url('/'),
            \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY
        );
        return $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
    }
}

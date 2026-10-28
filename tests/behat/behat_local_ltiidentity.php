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

/**
 * Behat steps of local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test, because this file is required by Behat.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps of local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_ltiidentity extends behat_base {
    /**
     * Switches an existing account to the LTI authentication method.
     *
     * Accounts with the authentication method "lti" cannot sign in with a password, so the scenario signs in
     * first and calls this step afterwards. The running session stays valid.
     *
     * @Given /^the account of "(?P<username_string>[^"]*)" uses LTI authentication$/
     * @param string $username The username.
     */
    public function the_account_uses_lti_authentication(string $username): void {
        global $DB;
        $DB->set_field('user', 'auth', 'lti', ['username' => $username]);
    }

    /** @var string Issuer of the platform used by the scenarios (also the auth_lti placeholder of the last name). */
    private const ISSUER = 'https://platform.example.org';

    /**
     * Binds an account to a platform user, as auth_lti does when it provisions the account (core API, read by core only).
     *
     * @Given /^the account of "(?P<username_string>[^"]*)" is bound to the LTI platform user "(?P<sub_string>[^"]*)"$/
     * @param string $username The username.
     * @param string $sub The platform user id (sub).
     */
    public function the_account_is_bound_to_platform_user(string $username, string $sub): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        get_auth_plugin('lti')->create_user_binding(self::ISSUER, $sub, (int) $userid);
    }

    /**
     * Runs the real auth_lti profile sync for a platform user without name and e-mail claims.
     *
     * This is core's NRPS membership path auth_plugin_lti::find_or_create_user_from_membership(), which calls the same
     * update_user_account() as a launch (auth/lti/auth.php in 4.5 and 5.3) and so dispatches before_user_updated.
     *
     * @When /^the LTI platform syncs "(?P<sub_string>[^"]*)" without claims$/
     * @param string $sub The platform user id.
     */
    public function the_platform_syncs_without_claims(string $sub): void {
        $this->sync($sub, []);
    }

    /**
     * Runs the real auth_lti profile sync for a platform user with the given claims.
     *
     * @When /^the LTI platform syncs "(?P<sub_string>[^"]*)" with the claims:$/
     * @param string $sub The platform user id.
     * @param \Behat\Gherkin\Node\TableNode $table Rows of claim name (given_name, family_name, email) and value.
     */
    public function the_platform_syncs_with_claims(string $sub, \Behat\Gherkin\Node\TableNode $table): void {
        $claims = [];
        foreach ($table->getRowsHash() as $name => $value) {
            if (!in_array($name, ['given_name', 'family_name', 'email'], true)) {
                throw new \coding_exception('Unsupported claim: ' . $name);
            }
            $claims[$name] = (string) $value;
        }
        $this->sync($sub, $claims);
    }

    /**
     * Opens the course profile (user/view.php) of a user.
     *
     * @Given /^I open the course profile of "(?P<username_string>[^"]*)" in "(?P<shortname_string>[^"]*)"$/
     * @param string $username The username.
     * @param string $shortname The course short name.
     */
    public function i_open_the_course_profile(string $username, string $shortname): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->execute('behat_general::i_visit', [new moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid])]);
    }

    /**
     * Run the membership sync.
     *
     * @param string $sub The platform user id.
     * @param array $claims Claims.
     */
    private function sync(string $sub, array $claims): void {
        global $CFG;
        require_once($CFG->libdir . '/authlib.php');
        get_auth_plugin('lti')->find_or_create_user_from_membership(['user_id' => $sub] + $claims, self::ISSUER);
    }
}

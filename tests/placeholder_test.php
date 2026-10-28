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

use local_ltiidentity\local\placeholder;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Unit/integration tests for placeholder detection against real auth_lti accounts.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\placeholder
 */
final class placeholder_test extends \advanced_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * An account created by a launch without claims carries exactly the three placeholders.
     */
    public function test_launch_without_claims_creates_placeholders(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('sub-1');
        $this->assertSame('sub-1', $user->firstname);
        $this->assertSame(launch_helper::ISSUER, $user->lastname);
        $this->assertSame(placeholder::placeholder_email(launch_helper::ISSUER, 'sub-1'), $user->email);
        $this->assertEqualsCanonicalizing(['firstname', 'lastname', 'email'], placeholder::placeholder_fields($user));
    }

    /**
     * Real claims are not placeholders.
     */
    public function test_launch_with_claims_has_no_placeholders(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('sub-2', [
            'given_name' => 'Ada', 'family_name' => 'Lovelace', 'email' => 'ada@example.org',
        ]);
        $this->assertSame([], placeholder::placeholder_fields($user));
    }

    /**
     * The same string is a placeholder only for the user it was derived for.
     */
    public function test_placeholder_is_user_specific(): void {
        $this->resetAfterTest();
        $one = launch_helper::launch('sub-a');
        $two = launch_helper::launch('sub-b');
        $this->assertTrue(placeholder::is_placeholder((int) $one->id, 'firstname', 'sub-a'));
        $this->assertFalse(placeholder::is_placeholder((int) $one->id, 'firstname', 'sub-b'));
        $this->assertFalse(placeholder::is_placeholder((int) $two->id, 'email', $one->email));
        $this->assertTrue(placeholder::is_placeholder((int) $two->id, 'email', strtoupper($two->email)));
    }

    /**
     * Edge cases: empty value, unknown field, user without binding.
     */
    public function test_edge_cases(): void {
        $this->resetAfterTest();
        $user = launch_helper::launch('sub-c');
        $this->assertFalse(placeholder::is_placeholder((int) $user->id, 'firstname', ''));
        $this->assertFalse(placeholder::is_placeholder((int) $user->id, 'username', 'sub-c'));
        $plain = $this->getDataGenerator()->create_user(['firstname' => 'sub-c']);
        $this->assertFalse(placeholder::is_placeholder((int) $plain->id, 'firstname', 'sub-c'));
    }
}

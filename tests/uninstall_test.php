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

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\user_preference;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\preferences;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Tests for the uninstall hook: no user preference of the plugin survives an uninstall.
 *
 * The hook is called directly; a real uninstall_plugin() inside PHPUnit would drop the plugin's tables, which a test
 * reset cannot restore.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\preferences
 * @covers     ::xmldb_local_ltiidentity_uninstall
 */
final class uninstall_test extends \advanced_testcase {
    /**
     * Load the fixtures and the uninstall hook.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once(__DIR__ . '/fixtures/launch_helper.php');
        require_once($CFG->dirroot . '/local/ltiidentity/db/uninstall.php');
        parent::setUpBeforeClass();
    }

    /**
     * Request an address change and return the token from the delivered message.
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
        $body = quoted_printable_decode($messages[0]->body);
        $this->assertSame(1, preg_match('/token=([0-9a-f]{64})/', $body, $m), 'Token in the link');
        return $m[1];
    }

    /**
     * Preference names of the plugin that a user currently has.
     *
     * @param int $userid The user.
     * @return string[]
     */
    private function plugin_preferences(int $userid): array {
        global $DB;
        return array_values($DB->get_fieldset_select(
            'user_preferences',
            'name',
            'userid = :userid AND ' . $DB->sql_like('name', ':pattern'),
            ['userid' => $userid, 'pattern' => $DB->sql_like_escape('local_ltiidentity_') . '%']
        ));
    }

    /**
     * The real flows write exactly the listed preferences, and the uninstall hook removes all of them, for all users,
     * while preferences of core and of other components stay.
     */
    public function test_uninstall_removes_every_plugin_preference(): void {
        global $DB;
        $this->resetAfterTest();

        // User 1: names saved through the self-service flow (locks and provenance), address change requested (pending).
        $pending = launch_helper::launch('u-1');
        profile_service::save(
            $pending->id,
            $pending->id,
            \context_user::instance($pending->id),
            (object) ['firstname' => 'Una', 'lastname' => 'Pending'],
            lock_manager::SOURCE_SELF
        );
        $this->request_and_capture($pending, 'pending.u1@example.org');

        // User 2: address change confirmed (verified flag and e-mail lock).
        $verified = launch_helper::launch('u-2');
        $token = $this->request_and_capture($verified, 'verified.u2@example.org');
        $this->assertSame(email_verifier::CONFIRMED, email_verifier::confirm($verified->id, $token, $verified->id));

        $written = array_unique(array_merge(
            $this->plugin_preferences($pending->id),
            $this->plugin_preferences($verified->id)
        ));
        $this->assertEqualsCanonicalizing(preferences::names(), $written, 'The flows write exactly the listed preferences');

        // Preferences that are not the plugin's must survive.
        set_user_preference('auth_forcepasswordchange', 1, $pending->id);
        set_user_preference('local_ltiidentityother_x', 'keep', $verified->id);
        $both = ['a' => $pending->id, 'b' => $verified->id];
        $before = $DB->count_records_select('user_preferences', 'userid IN (:a, :b)', $both);
        $plugincount = count($this->plugin_preferences($pending->id)) + count($this->plugin_preferences($verified->id));

        $this->assertTrue(xmldb_local_ltiidentity_uninstall());

        $this->assertSame([], $this->plugin_preferences($pending->id));
        $this->assertSame([], $this->plugin_preferences($verified->id));
        [$insql, $params] = $DB->get_in_or_equal(preferences::names());
        $this->assertSame(0, $DB->count_records_select('user_preferences', "name $insql", $params));
        $kept = $DB->get_field('user_preferences', 'value', ['userid' => $pending->id, 'name' => 'auth_forcepasswordchange']);
        $this->assertSame('1', $kept);
        $kept = $DB->get_field('user_preferences', 'value', ['userid' => $verified->id, 'name' => 'local_ltiidentityother_x']);
        $this->assertSame('keep', $kept);
        $this->assertSame($before - $plugincount, $DB->count_records_select('user_preferences', 'userid IN (:a, :b)', $both));
        // Sessions that cached the preferences reload them.
        $this->assertNotEmpty(get_cache_flag('userpreferenceschanged', $pending->id));
        $this->assertNotEmpty(get_cache_flag('userpreferenceschanged', $verified->id));
    }

    /**
     * The hook succeeds on a site without any preference of the plugin and reports the count.
     */
    public function test_delete_all_counts_and_is_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        lock_manager::lock($user->id, 'email', lock_manager::SOURCE_VERIFIED);
        $this->assertSame(1, preferences::delete_all());
        $this->assertSame(0, preferences::delete_all());
        $this->assertTrue(xmldb_local_ltiidentity_uninstall());
    }

    /**
     * The uninstall list and the privacy metadata name the same preferences.
     */
    public function test_names_match_privacy_metadata(): void {
        $collection = \local_ltiidentity\privacy\provider::get_metadata(new collection('local_ltiidentity'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof user_preference) {
                $declared[] = $item->get_name();
            }
        }
        $this->assertEqualsCanonicalizing($declared, preferences::names());
    }
}

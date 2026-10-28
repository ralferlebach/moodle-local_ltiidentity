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

namespace local_ltiidentity\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_ltiidentity\local\audit;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\profile_service;
use local_ltiidentity\tests\fixtures\launch_helper;

/**
 * Privacy API tests (IP-L08).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Load the fixtures.
     */
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../fixtures/launch_helper.php');
        parent::setUpBeforeClass();
    }

    /**
     * Teacher edits an LTI learner.
     *
     * @return array [teacher, learner, course context]
     */
    private function scenario(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $learner = launch_helper::launch('pv-1');
        $gen->enrol_user($learner->id, $course->id, 'student');
        $context = \context_course::instance($course->id);
        profile_service::save(
            $teacher->id,
            $learner->id,
            $context,
            (object) ['firstname' => 'Priv', 'lastname' => 'Acy'],
            lock_manager::SOURCE_TEACHER
        );
        return [$teacher, $learner, $context];
    }

    /**
     * Metadata declares the table and the preferences.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_ltiidentity'));
        $names = array_map(static function ($item): string {
            return $item->get_name();
        }, $collection->get_collection());
        $this->assertContainsEquals('local_ltiidentity_audit', $names);
        $this->assertContainsEquals('local_ltiidentity_lock_firstname', $names);
        $this->assertContainsEquals('local_ltiidentity_emailpending', $names);
    }

    /**
     * Contexts: target and actor both find the course context, the target also the user context.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();
        $learnerlist = provider::get_contexts_for_userid($learner->id);
        $this->assertContainsEquals($context->id, $learnerlist->get_contextids());
        $this->assertContainsEquals(\context_user::instance($learner->id)->id, $learnerlist->get_contextids());
        $this->assertContainsEquals($context->id, provider::get_contexts_for_userid($teacher->id)->get_contextids());

        $userlist = new userlist($context, 'local_ltiidentity');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$teacher->id, $learner->id], $userlist->get_userids());
    }

    /**
     * Export contains the audit entries and the preferences.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();
        $list = new approved_contextlist($learner, 'local_ltiidentity', [$context->id]);
        provider::export_user_data($list);
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('privacy:audit', 'local_ltiidentity')]);
        $this->assertCount(1, $data->entries);
        $this->assertSame('update', $data->entries[0]->action);

        provider::export_user_preferences($learner->id);
        $prefs = writer::with_context(\context_user::instance($learner->id))->get_user_preferences('local_ltiidentity');
        $this->assertSame('teacher', $prefs->local_ltiidentity_lock_firstname->value);
    }

    /**
     * Provenance records are exported as their source only (the value hash is not exported) and erased with the user.
     */
    public function test_export_and_delete_provenance(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();
        $raw = get_user_preferences(\local_ltiidentity\local\provenance::PREFIX . 'firstname', '', $learner->id);
        $this->assertStringStartsWith('teacher:', $raw, 'The teacher save recorded the provenance');

        provider::export_user_preferences($learner->id);
        $prefs = writer::with_context(\context_user::instance($learner->id))->get_user_preferences('local_ltiidentity');
        $this->assertSame('teacher', $prefs->local_ltiidentity_src_firstname->value);
        $this->assertStringNotContainsString(substr($raw, 8), json_encode($prefs));

        provider::delete_data_for_user(new approved_contextlist(
            $learner,
            'local_ltiidentity',
            [\context_user::instance($learner->id)->id]
        ));
        $this->assertFalse($DB->record_exists_select(
            'user_preferences',
            'userid = :id AND ' . $DB->sql_like('name', ':p'),
            ['id' => $learner->id, 'p' => 'local\_ltiidentity\_src\_%']
        ));
    }

    /**
     * The pending e-mail request is exported without the token hash; the address and expiry are the user's data.
     */
    public function test_export_pending_email_without_token_hash(): void {
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();
        $hash = str_repeat('c', 64);
        set_user_preference(
            \local_ltiidentity\local\email_verifier::PENDING,
            json_encode(['e' => 'pending@example.org', 'h' => $hash, 'x' => 1900000000, 'c' => 1, 'a' => 0, 'r' => 1]),
            $learner->id
        );
        provider::export_user_preferences($learner->id);
        $prefs = writer::with_context(\context_user::instance($learner->id))->get_user_preferences('local_ltiidentity');
        $value = $prefs->local_ltiidentity_emailpending->value;
        $this->assertStringContainsString('pending@example.org', $value);
        $this->assertStringNotContainsString($hash, $value);
        $this->assertStringNotContainsString('"h"', $value);

        // Erasure removes the pending request and every other preference of the plugin.
        provider::delete_data_for_user(new approved_contextlist(
            $learner,
            'local_ltiidentity',
            [\context_user::instance($learner->id)->id]
        ));
        global $DB;
        $this->assertFalse($DB->record_exists('user_preferences', [
            'userid' => $learner->id, 'name' => \local_ltiidentity\local\email_verifier::PENDING,
        ]));
    }

    /**
     * Deleting the target removes the audit rows and preferences; the actor's rows about others are anonymised.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();

        provider::delete_data_for_user(new approved_contextlist($teacher, 'local_ltiidentity', [$context->id]));
        $row = $DB->get_record(audit::TABLE, []);
        $this->assertEquals(0, $row->actorid, 'Actor anonymised');
        $this->assertEquals($learner->id, $row->targetuserid);

        $usercontext = \context_user::instance($learner->id);
        provider::delete_data_for_user(new approved_contextlist(
            $learner,
            'local_ltiidentity',
            [$context->id, $usercontext->id]
        ));
        $this->assertSame(0, $DB->count_records(audit::TABLE));
        $this->assertSame(0, $DB->count_records_select(
            'user_preferences',
            $DB->sql_like('name', ':p') . ' AND userid = :u',
            ['p' => $DB->sql_like_escape('local_ltiidentity_') . '%', 'u' => $learner->id]
        ));
    }

    /**
     * Delete for a list of users and for all users of a context.
     */
    public function test_delete_for_users_and_context(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, $learner, $context] = $this->scenario();
        provider::delete_data_for_users(new approved_userlist($context, 'local_ltiidentity', [$learner->id]));
        $this->assertSame(0, $DB->count_records(audit::TABLE));

        [$teacher2, $learner2, $context2] = $this->scenario();
        provider::delete_data_for_all_users_in_context($context2);
        $this->assertSame(0, $DB->count_records(audit::TABLE, ['contextid' => $context2->id]));
    }
}

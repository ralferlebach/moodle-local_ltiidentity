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
 * Teacher overview: LTI users of a course with placeholder and lock indicators.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ltiidentity\local\access;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\placeholder;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/ltiidentity:manage', $context);

$PAGE->set_url(new moodle_url('/local/ltiidentity/index.php', ['id' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('ltiusers', 'local_ltiidentity'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$canrelease = access::can_release();
$fieldlabels = [
    'firstname' => get_string('firstname'),
    'lastname' => get_string('lastname'),
    'email' => get_string('email'),
];
$rows = [];
$enrolled = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, false);
foreach ($enrolled as $user) {
    if (!access::can_manage_user($context, (int) $user->id)) {
        continue;
    }
    $placeholders = placeholder::placeholder_fields($user);
    $locks = [];
    foreach (lock_manager::locked_fields((int) $user->id) as $field) {
        $locks[] = [
            'label' => $fieldlabels[$field],
            'source' => get_string('source_' . lock_manager::provenance((int) $user->id, $field), 'local_ltiidentity'),
            'releaseurl' => !$canrelease ? '' : (new moodle_url(
                '/local/ltiidentity/release.php',
                ['userid' => $user->id, 'field' => $field, 'id' => $course->id]
            ))->out(false),
        ];
    }
    $pending = email_verifier::get_pending((int) $user->id);
    $emailstatus = 'emailstatus_unverified';
    if ($pending) {
        $emailstatus = 'emailstatus_pending';
    } else if (email_verifier::is_verified($user)) {
        $emailstatus = 'emailstatus_verified';
    }
    $rows[] = [
        'fullname' => in_array('firstname', $placeholders, true) || in_array('lastname', $placeholders, true) ?
            get_string('nameplaceholder', 'local_ltiidentity', placeholder::display_id((int) $user->id)) :
            fullname($user),
        'placeholders' => array_map(static function (string $field) use ($fieldlabels): string {
            return $fieldlabels[$field];
        }, $placeholders),
        'locks' => $locks,
        'emailstatus' => get_string($emailstatus, 'local_ltiidentity'),
        'editurl' => (new moodle_url(
            '/local/ltiidentity/edit.php',
            ['id' => $course->id, 'userid' => $user->id]
        ))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('ltiusers', 'local_ltiidentity'));
echo $PAGE->get_renderer('local_ltiidentity')->user_list($rows);
echo $OUTPUT->footer();

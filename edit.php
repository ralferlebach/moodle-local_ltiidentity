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
 * Teacher form: complete name and e-mail of an LTI user in the course.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ltiidentity\form\profile_form;
use local_ltiidentity\local\access;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\profile_service;

$courseid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/ltiidentity:manage', $context);
if (!access::can_manage_user($context, $userid)) {
    throw new moodle_exception('cannotmanageuser', 'local_ltiidentity');
}
$user = access::get_lti_user($userid);

$returnurl = new moodle_url('/local/ltiidentity/index.php', ['id' => $course->id]);
$PAGE->set_url(new moodle_url('/local/ltiidentity/edit.php', ['id' => $course->id, 'userid' => $userid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('editprofile', 'local_ltiidentity'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$form = new profile_form(null, [
    'userid' => $userid,
    'courseid' => $course->id,
    'showemail' => (bool) get_config('local_ltiidentity', 'teacheremail'),
]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    // Form API checked the sesskey; authorisation is checked again above for every request.
    if (!get_config('local_ltiidentity', 'teacheremail')) {
        unset($data->email);
    }
    $result = profile_service::save($USER->id, $userid, $context, $data, lock_manager::SOURCE_TEACHER);
    $message = get_string('profilesaved', 'local_ltiidentity');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    if ($result->email === email_verifier::REQUESTED || $result->email === email_verifier::REQUESTED_DUPLICATE) {
        $message .= ' ' . get_string('emailrequested', 'local_ltiidentity');
        if ($result->email === email_verifier::REQUESTED_DUPLICATE) {
            $message .= ' ' . get_string('emailduplicatewarning', 'local_ltiidentity');
            $type = \core\output\notification::NOTIFY_WARNING;
        }
    } else if ($result->email !== '') {
        $message .= ' ' . get_string('emailresult_' . $result->email, 'local_ltiidentity');
        $type = \core\output\notification::NOTIFY_WARNING;
    }
    redirect($returnurl, $message, null, $type);
}
$form->set_data((object) [
    'userid' => $userid,
    'id' => $course->id,
    'firstname' => \local_ltiidentity\local\placeholder::is_placeholder((int) $user->id, 'firstname', $user->firstname) ?
        '' : $user->firstname,
    'lastname' => \local_ltiidentity\local\placeholder::is_placeholder((int) $user->id, 'lastname', $user->lastname) ?
        '' : $user->lastname,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editprofile', 'local_ltiidentity'));
echo $OUTPUT->notification(get_string('lockexplanation', 'local_ltiidentity'), 'info');
$form->display();
echo $OUTPUT->footer();

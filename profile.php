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
 * Self-service: an LTI user completes their own name and e-mail address.
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

require_login(null, false);
if (isguestuser() || !access::can_edit_own((int) $USER->id)) {
    throw new moodle_exception('cannotmanageuser', 'local_ltiidentity');
}
$context = context_user::instance($USER->id);
$user = access::get_lti_user((int) $USER->id);

$returnurl = new moodle_url('/local/ltiidentity/profile.php');
$PAGE->set_url($returnurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myltiprofile', 'local_ltiidentity'));
$PAGE->set_heading(fullname($USER));

$form = new profile_form(null, ['userid' => (int) $USER->id, 'courseid' => 0, 'showemail' => true]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/user/preferences.php'));
}
if ($data = $form->get_data()) {
    $result = profile_service::save((int) $USER->id, (int) $USER->id, $context, $data, lock_manager::SOURCE_SELF);
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
    'userid' => (int) $USER->id,
    'id' => 0,
    'firstname' => \local_ltiidentity\local\placeholder::is_placeholder((int) $user->id, 'firstname', $user->firstname) ?
        '' : $user->firstname,
    'lastname' => \local_ltiidentity\local\placeholder::is_placeholder((int) $user->id, 'lastname', $user->lastname) ?
        '' : $user->lastname,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myltiprofile', 'local_ltiidentity'));
echo $OUTPUT->notification(get_string('lockexplanation', 'local_ltiidentity'), 'info');
$provenancerows = \local_ltiidentity\output\profile_provenance::rows($user, (int) $USER->id);
if ($provenancerows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-ltiidentity-provenance';
    $table->caption = get_string('provenance', 'local_ltiidentity');
    $table->head = [get_string('profilefield', 'admin'), get_string('provenance', 'local_ltiidentity')];
    foreach ($provenancerows as $row) {
        $cell = new html_table_cell(s($row['label']));
        $cell->header = true;
        $cell->scope = 'row';
        $table->data[] = new html_table_row([
            $cell,
            new html_table_cell(s(\local_ltiidentity\output\profile_provenance::row_text($row))),
        ]);
    }
    echo html_writer::table($table);
}
$form->display();
echo $OUTPUT->footer();

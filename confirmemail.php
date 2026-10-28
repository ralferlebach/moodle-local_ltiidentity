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
 * Confirm a new e-mail address with the one-time token.
 *
 * GET shows a confirmation form (the token is only read, nothing changes). The change happens on
 * POST with a valid sesskey, for the logged-in account that the request belongs to.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ltiidentity\local\email_verifier;

$userid = required_param('userid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('cannotmanageuser', 'local_ltiidentity');
}

$PAGE->set_url(new moodle_url('/local/ltiidentity/confirmemail.php', ['userid' => $userid, 'token' => $token]));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('confirmemail', 'local_ltiidentity'));
$PAGE->set_heading(get_string('confirmemail', 'local_ltiidentity'));

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    $result = email_verifier::confirm($userid, $token, (int) $USER->id);
    $type = $result === email_verifier::CONFIRMED ?
        \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
    redirect(
        new moodle_url('/local/ltiidentity/profile.php'),
        get_string('confirmresult_' . $result, 'local_ltiidentity'),
        null,
        $type
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('confirmemail', 'local_ltiidentity'));
echo $OUTPUT->confirm(
    get_string('confirmemailquestion', 'local_ltiidentity'),
    new single_button(new moodle_url($PAGE->url, ['confirm' => 1]), get_string('confirm'), 'post', single_button::BUTTON_PRIMARY),
    new moodle_url('/local/ltiidentity/profile.php')
);
echo $OUTPUT->footer();

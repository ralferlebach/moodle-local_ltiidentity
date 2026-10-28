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
 * Administrator action: release the local lock of one profile field (with audit).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\profile_service;

$userid = required_param('userid', PARAM_INT);
$field = required_param('field', PARAM_ALPHA);
$courseid = optional_param('id', 0, PARAM_INT);

require_login();
$systemcontext = context_system::instance();
require_capability('local/ltiidentity:releaselock', $systemcontext);
if (!in_array($field, lock_manager::fields(), true) || !lock_manager::is_locked($userid, $field)) {
    throw new moodle_exception('cannotmanageuser', 'local_ltiidentity');
}
$returnurl = $courseid ? new moodle_url('/local/ltiidentity/index.php', ['id' => $courseid]) :
    new moodle_url('/admin/search.php');

$PAGE->set_url(new moodle_url(
    '/local/ltiidentity/release.php',
    ['userid' => $userid, 'field' => $field, 'id' => $courseid]
));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('releaselock', 'local_ltiidentity'));
$PAGE->set_heading(get_string('releaselock', 'local_ltiidentity'));

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    profile_service::release_lock((int) $USER->id, $userid, $field);
    redirect(
        $returnurl,
        get_string('lockreleased', 'local_ltiidentity'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('releaselock', 'local_ltiidentity'));
echo $OUTPUT->confirm(
    get_string('releaselockquestion', 'local_ltiidentity'),
    new single_button(new moodle_url($PAGE->url, ['confirm' => 1]), get_string('confirm'), 'post', single_button::BUTTON_PRIMARY),
    $returnurl
);
echo $OUTPUT->footer();

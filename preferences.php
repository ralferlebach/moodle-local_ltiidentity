<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * User self-service page for local LTI identity preferences.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/selfservice_form.php');

$id = optional_param('id', $USER->id, PARAM_INT);
$userid = ($id > 0) ? $id : $USER->id;

require_login();
$context = context_user::instance($userid);
require_capability('local/ltiidentity:selfeditidentity', $context);

$url = new moodle_url('/local/ltiidentity/preferences.php', ['id' => $userid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managemyidentity', 'local_ltiidentity'));
$PAGE->set_heading(get_string('managemyidentity', 'local_ltiidentity'));

$form = new \local_ltiidentity\form\selfservice_form($url, ['userid' => $userid]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my/'));
}

if ($form->get_data()) {
    require_sesskey();
    redirect($url, get_string('changessaved'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();

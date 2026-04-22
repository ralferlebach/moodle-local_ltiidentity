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
 * Confirmation endpoint for local email verification.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();

$url = new moodle_url('/local/ltiidentity/confirmemail.php', ['token' => $token]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('confirmemailchange', 'local_ltiidentity'));
$PAGE->set_heading(get_string('confirmemailchange', 'local_ltiidentity'));

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('emailconfirmplaceholder', 'local_ltiidentity'), 'info');
echo $OUTPUT->continue_button(new moodle_url('/my/'));
echo $OUTPUT->footer();

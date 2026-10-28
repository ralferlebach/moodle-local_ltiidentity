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
 * Admin settings of local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\policy;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ltiidentity', get_string('pluginname', 'local_ltiidentity'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configselect(
            'local_ltiidentity/claimpriority',
            get_string('claimpriority', 'local_ltiidentity'),
            get_string('claimpriority_desc', 'local_ltiidentity'),
            policy::CLAIMS_WIN,
            [
                policy::CLAIMS_WIN => get_string('claimpriority_claimswin', 'local_ltiidentity'),
                policy::LOCAL_WINS => get_string('claimpriority_localwins', 'local_ltiidentity'),
                policy::FILL_PLACEHOLDERS => get_string('claimpriority_fill', 'local_ltiidentity'),
            ]
        ));
        $settings->add(new admin_setting_configselect(
            'local_ltiidentity/emailduplicate',
            get_string('emailduplicate', 'local_ltiidentity'),
            get_string('emailduplicate_desc', 'local_ltiidentity'),
            email_verifier::DUP_BLOCK,
            [
                email_verifier::DUP_ALLOW => get_string('emailduplicate_allow', 'local_ltiidentity'),
                email_verifier::DUP_WARN => get_string('emailduplicate_warn', 'local_ltiidentity'),
                email_verifier::DUP_BLOCK => get_string('emailduplicate_block', 'local_ltiidentity'),
            ]
        ));
        $settings->add(new admin_setting_configduration(
            'local_ltiidentity/emailtokenlifetime',
            get_string('emailtokenlifetime', 'local_ltiidentity'),
            get_string('emailtokenlifetime_desc', 'local_ltiidentity'),
            DAYSECS,
            HOURSECS
        ));
        $settings->add(new admin_setting_configtext(
            'local_ltiidentity/locktimeout',
            get_string('locktimeout', 'local_ltiidentity'),
            get_string('locktimeout_desc', 'local_ltiidentity'),
            10,
            PARAM_INT,
            4
        ));
        $settings->add(new admin_setting_configtext(
            'local_ltiidentity/auditretention',
            get_string('auditretention', 'local_ltiidentity'),
            get_string('auditretention_desc', 'local_ltiidentity'),
            730,
            PARAM_INT,
            6
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_ltiidentity/logprotected',
            get_string('logprotected', 'local_ltiidentity'),
            get_string('logprotected_desc', 'local_ltiidentity'),
            0
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_ltiidentity/teacheremail',
            get_string('teacheremail', 'local_ltiidentity'),
            get_string('teacheremail_desc', 'local_ltiidentity'),
            1
        ));
    }
}

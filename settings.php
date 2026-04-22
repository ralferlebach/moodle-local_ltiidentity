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
 * Admin settings for the local_ltiidentity plugin.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ltiidentity',
        get_string('pluginname', 'local_ltiidentity'),
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiidentity/enabled',
        get_string('settingsenabled', 'local_ltiidentity'),
        get_string('settingsenabled_desc', 'local_ltiidentity'),
        1,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiidentity/allowselfservice',
        get_string('settingsallowselfservice', 'local_ltiidentity'),
        get_string('settingsallowselfservice_desc', 'local_ltiidentity'),
        1,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiidentity/allowlocalemail',
        get_string('settingsallowlocalemail', 'local_ltiidentity'),
        get_string('settingsallowlocalemail_desc', 'local_ltiidentity'),
        0,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiidentity/requireemailconfirmation',
        get_string('settingsrequireemailconfirmation', 'local_ltiidentity'),
        get_string('settingsrequireemailconfirmation_desc', 'local_ltiidentity'),
        1,
    ));

    $settings->add(new admin_setting_configtext(
        'local_ltiidentity/defaultfirstnamepattern',
        get_string('settingsdefaultfirstnamepattern', 'local_ltiidentity'),
        get_string('settingsdefaultfirstnamepattern_desc', 'local_ltiidentity'),
        'LTI-User',
        PARAM_TEXT,
    ));

    $settings->add(new admin_setting_configtext(
        'local_ltiidentity/defaultlastnamepattern',
        get_string('settingsdefaultlastnamepattern', 'local_ltiidentity'),
        get_string('settingsdefaultlastnamepattern_desc', 'local_ltiidentity'),
        '{{moodle_userid}}',
        PARAM_TEXT,
    ));

    $ADMIN->add('localplugins', $settings);
}

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
 * English language pack for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LTI identity facade';
$string['privacy:metadata'] = 'The local_ltiidentity plugin stores local identity override settings for LTI-authenticated users.';
$string['manageidentity'] = 'Manage LTI identity facade';
$string['managemyidentity'] = 'Manage my LTI identity';
$string['courseidentitymanagement'] = 'Course identity management';
$string['confirmemailchange'] = 'Confirm email change';
$string['emailconfirmplaceholder'] = 'The email confirmation workflow will be implemented in a later increment.';
$string['managementplaceholder'] = 'This page is the initial management shell for the local LTI identity facade.';
$string['courseplaceholder'] = 'This page is the initial course-level shell for managing LTI user identities.';
$string['settingsenabled'] = 'Enable plugin';
$string['settingsenabled_desc'] = 'Enable the local LTI identity facade plugin.';
$string['settingsallowselfservice'] = 'Allow self-service';
$string['settingsallowselfservice_desc'] = 'Allow LTI-authenticated users to maintain local identity values.';
$string['settingsallowlocalemail'] = 'Allow local email';
$string['settingsallowlocalemail_desc'] = 'Allow storing a local email address for LTI-authenticated users.';
$string['settingsrequireemailconfirmation'] = 'Require email confirmation';
$string['settingsrequireemailconfirmation_desc'] = 'Require confirmation before a locally managed email address becomes active.';
$string['settingsdefaultfirstnamepattern'] = 'Default first-name rule';
$string['settingsdefaultfirstnamepattern_desc'] = 'Template for generated first names when anonymous LTI logins need a rule-based value.';
$string['settingsdefaultlastnamepattern'] = 'Default last-name rule';
$string['settingsdefaultlastnamepattern_desc'] = 'Template for generated last names when anonymous LTI logins need a rule-based value.';
$string['firstname'] = 'First name';
$string['lastname'] = 'Last name';
$string['email'] = 'Email';

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
 * Admin form for name rules.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Administrative form for rule-based anonymous login names.
 */
class admin_namerule_form extends \moodleform {
    /**
     * Defines the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('pluginname', 'local_ltiidentity'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text', 'firstnamepattern', get_string('settingsdefaultfirstnamepattern', 'local_ltiidentity'));
        $mform->setType('firstnamepattern', PARAM_TEXT);

        $mform->addElement('text', 'lastnamepattern', get_string('settingsdefaultlastnamepattern', 'local_ltiidentity'));
        $mform->setType('lastnamepattern', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

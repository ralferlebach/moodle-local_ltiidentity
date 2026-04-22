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
 * Admin form for per-user LTI identity management.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Admin identity edit form.
 */
class admin_user_form extends \moodleform {
    /**
     * Defines the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'firstnameoverride', get_string('firstname', 'local_ltiidentity'));
        $mform->setType('firstnameoverride', PARAM_TEXT);

        $mform->addElement('text', 'lastnameoverride', get_string('lastname', 'local_ltiidentity'));
        $mform->setType('lastnameoverride', PARAM_TEXT);

        $mform->addElement('text', 'emailoverride', get_string('email', 'local_ltiidentity'));
        $mform->setType('emailoverride', PARAM_EMAIL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

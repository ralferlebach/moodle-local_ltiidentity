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

namespace local_ltiidentity\form;

use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\profile_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to complete the name and (optionally) the e-mail address of an LTI user.
 *
 * Custom data: userid (int), courseid (int, 0 for self-service), showemail (bool).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'userid', $this->_customdata['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'firstname', get_string('firstname'), ['maxlength' => 100, 'size' => 40]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', get_string('required'), 'required', null, 'client');
        $mform->addElement('text', 'lastname', get_string('lastname'), ['maxlength' => 100, 'size' => 40]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', get_string('required'), 'required', null, 'client');

        if (!empty($this->_customdata['showemail'])) {
            $mform->addElement(
                'text',
                'email',
                get_string('newemail', 'local_ltiidentity'),
                ['maxlength' => 100, 'size' => 40]
            );
            $mform->setType('email', PARAM_RAW_TRIMMED);
            $mform->addHelpButton('email', 'newemail', 'local_ltiidentity');
        }
        $this->add_action_buttons();
    }

    /**
     * Server side validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Field name => error message.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $userid = (int) $this->_customdata['userid'];
        foreach (['firstname', 'lastname'] as $field) {
            $error = profile_service::validate_name($userid, $field, (string) ($data[$field] ?? ''));
            if ($error !== '') {
                $errors[$field] = get_string($error, 'local_ltiidentity');
            }
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '') {
            if (!email_verifier::is_acceptable($email)) {
                $errors['email'] = get_string('erroremailinvalid', 'local_ltiidentity');
            } else if (
                email_verifier::is_used_by_other($userid, $email)
                    && email_verifier::effective_duplicate_policy() === email_verifier::DUP_BLOCK
            ) {
                // Deliberately generic: do not reveal that another account exists.
                $errors['email'] = get_string('erroremailnotavailable', 'local_ltiidentity');
            }
        }
        return $errors;
    }
}

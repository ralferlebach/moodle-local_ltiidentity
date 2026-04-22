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
 * Resolves rule-based names for anonymous LTI logins.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Name rule resolver.
 */
class name_rule_resolver {
    /**
     * Renders the effective fallback name for the given user.
     *
     * @param stdClass $user User record.
     * @param array $launchdata Optional launch data.
     * @return array{firstname:string,lastname:string}
     */
    public function render_name(stdClass $user, array $launchdata = []): array {
        $firstname = (string)get_config('local_ltiidentity', 'defaultfirstnamepattern');
        $lastname = (string)get_config('local_ltiidentity', 'defaultlastnamepattern');

        $replacements = [
            '{{moodle_userid}}' => (string)($user->id ?? ''),
            '{{lti_user_id}}' => (string)($launchdata['lti_user_id'] ?? ''),
            '{{lti_provider_id}}' => (string)($launchdata['lti_provider_id'] ?? ''),
        ];

        return [
            'firstname' => strtr($firstname, $replacements),
            'lastname' => strtr($lastname, $replacements),
        ];
    }
}

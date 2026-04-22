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
 * Email verification service skeleton.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles creation and verification of local email change tokens.
 */
class email_verification_service {
    /**
     * Creates a verification token for a user.
     *
     * @param int $userid User id.
     * @param string $email Pending email address.
     * @return string
     */
    public function create_token(int $userid, string $email): string {
        return hash('sha256', $userid . ':' . $email . ':' . microtime(true));
    }
}

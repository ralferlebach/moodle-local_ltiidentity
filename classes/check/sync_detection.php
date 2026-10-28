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

namespace local_ltiidentity\check;

use core\check\check;
use core\check\result;
use local_ltiidentity\local\sync_detector;

/**
 * Status check: is the LTI synchronisation fingerprint of this plugin still what installed core sends?
 *
 * The protection of locked values relies on the shape of the update that auth_plugin_lti passes to the user API.
 * If a Moodle upgrade changes that shape, this check turns to warning instead of letting the protection fail
 * silently.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_detection extends check {
    /**
     * Name of the check.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_syncdetection', 'local_ltiidentity');
    }

    /**
     * Run the check.
     *
     * @return result
     */
    public function get_result(): result {
        $hooks = \core\di::get(\core\hook\manager::class);
        $registered = false;
        foreach ($hooks->get_callbacks_for_hook(\core_user\hook\before_user_updated::class) as $callback) {
            if ($callback['component'] === 'local_ltiidentity') {
                $registered = true;
            }
        }
        if (!$registered) {
            return new result(result::ERROR, get_string('check_syncdetection_nohook', 'local_ltiidentity'));
        }
        $installed = sync_detector::installed_core_fingerprint();
        if ($installed === null) {
            return new result(result::UNKNOWN, get_string('check_syncdetection_unknown', 'local_ltiidentity'));
        }
        if ($installed !== sync_detector::FINGERPRINT) {
            return new result(result::WARNING, get_string('check_syncdetection_changed', 'local_ltiidentity'));
        }
        return new result(result::OK, get_string('check_syncdetection_ok', 'local_ltiidentity'));
    }
}

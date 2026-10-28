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

namespace local_ltiidentity\local;

/**
 * Recognises the launch / membership synchronisation of auth_lti without inspecting the call stack.
 *
 * Core offers no flag or event that says "this user update comes from auth_plugin_lti". What is reliably
 * distinguishable (verified in the sources of Moodle 4.5 and 5.3) is the fingerprint of the update object:
 * auth_plugin_lti::update_user_account() always passes exactly the four properties id, firstname, lastname and
 * email, for an account whose auth is "lti", and it does so on every launch and every NRPS member sync.
 * Interactive forms, admin tools, bulk uploads and this plugin's own writer pass other property sets.
 *
 * Known limit (documented in docs/DOCUMENTATION.md): a third-party caller that updates an LTI account with exactly
 * these four properties looks identical. Web service requests (WS_SERVER) and this plugin's own writes are
 * excluded explicitly. The status check in lib.php compares the fingerprint with the installed core source and
 * warns when core changes it; independent of the fingerprint, placeholder values never overwrite real values
 * (see hook_callbacks::apply_priority()).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_detector {
    /** @var string[] Sorted property names of the update object that auth_lti passes to the user API. */
    public const FINGERPRINT = ['email', 'firstname', 'id', 'lastname'];

    /**
     * Whether an update to a stored account looks like the auth_lti synchronisation.
     *
     * @param \stdClass $update The update object of the before_user_updated hook.
     * @param \stdClass $current The stored user record.
     * @return bool
     */
    public static function is_sync(\stdClass $update, \stdClass $current): bool {
        if ($current->auth !== 'lti' || user_writer::is_active() || self::is_web_service()) {
            return false;
        }
        $keys = array_keys(get_object_vars($update));
        sort($keys);
        return $keys === self::FINGERPRINT;
    }

    /**
     * Whether the request is a web service call (those never carry the LTI launch sync).
     *
     * @return bool
     */
    public static function is_web_service(): bool {
        return defined('WS_SERVER') && WS_SERVER;
    }

    /**
     * Extract the property names of the $update array of an update_user_account() source text.
     *
     * @param string $source Source code of the method.
     * @return string[]|null Sorted property names, or null if no update array was found.
     */
    public static function fingerprint_from_source(string $source): ?array {
        if (!preg_match('/\\$update\s*=\s*\[(.*?)\];/s', $source, $match)) {
            return null;
        }
        if (!preg_match_all("/'([a-z_]+)'\s*=>/", $match[1], $keys)) {
            return null;
        }
        $names = array_values(array_unique($keys[1]));
        sort($names);
        return $names;
    }

    /**
     * Fingerprint of the installed core, read from the source of auth_plugin_lti::update_user_account().
     *
     * @return string[]|null Sorted property names, or null if the method or its source cannot be read.
     */
    public static function installed_core_fingerprint(): ?array {
        global $CFG;
        require_once($CFG->libdir . '/authlib.php');
        if (!class_exists(\auth_plugin_lti::class) || !method_exists(\auth_plugin_lti::class, 'update_user_account')) {
            return null;
        }
        $method = new \ReflectionMethod(\auth_plugin_lti::class, 'update_user_account');
        $file = $method->getFileName();
        if (!$file || !is_readable($file)) {
            return null;
        }
        $lines = array_slice(file($file), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        return self::fingerprint_from_source(implode('', $lines));
    }
}

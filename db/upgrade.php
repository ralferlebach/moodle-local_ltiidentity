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
 * Upgrade steps for local_ltiidentity.
 *
 * Supported upgrade sources are the delivered builds of this implementation: 0.2.0 (2026102501, 2026102502).
 *
 * Not supported: the April 2026 rump that was published under the same component name on the development branch
 * (release 0.1.0, version 2026042200). It has a different and incompatible schema (tables local_ltiidentity_override,
 * _mailtoken, _namerule and a local_ltiidentity_audit with other columns) and a post-login observer. This implementation
 * replaces it; it was never installed productively (owner decision 2026-10-27), so there is no upgrade path. An installed
 * rump makes the upgrade stop with an explanation instead of running on a foreign schema (docs/MIGRATION.md).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_ltiidentity plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_ltiidentity_upgrade($oldversion) {
    global $CFG;
    require_once($CFG->dirroot . '/local/ltiidentity/db/upgradelib.php');

    if ($oldversion < LOCAL_LTIIDENTITY_FIRST_SUPPORTED_VERSION) {
        throw new moodle_exception('upgradefromrump', 'local_ltiidentity', '', (string) $oldversion);
    }

    if ($oldversion < 2026102700) {
        // Release 0.3.0: field provenance (IP-04). The field locks of 0.2.0 recorded who entered a locked value
        // (teacher, self, verified); carry that over as the provenance of the value. Idempotent.
        local_ltiidentity_backfill_provenance();
        upgrade_plugin_savepoint(true, 2026102700, 'local', 'ltiidentity');
    }

    return true;
}

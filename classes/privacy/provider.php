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
 * Privacy provider for local_ltiidentity.
 *
 * @package   local_ltiidentity
 * @copyright 2026 Ralf Erlebach
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltiidentity\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Declares the privacy metadata stored by the plugin.
 */
class provider implements metadata_provider {
    /**
     * Returns metadata for the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_ltiidentity_override', [
            'userid' => 'privacy:metadata',
            'firstnameoverride' => 'privacy:metadata',
            'lastnameoverride' => 'privacy:metadata',
            'emailoverride' => 'privacy:metadata',
        ], 'privacy:metadata');

        $collection->add_database_table('local_ltiidentity_mailtoken', [
            'userid' => 'privacy:metadata',
            'emailcandidate' => 'privacy:metadata',
        ], 'privacy:metadata');

        $collection->add_database_table('local_ltiidentity_namerule', [
            'firstnamepattern' => 'privacy:metadata',
            'lastnamepattern' => 'privacy:metadata',
        ], 'privacy:metadata');

        $collection->add_database_table('local_ltiidentity_audit', [
            'userid' => 'privacy:metadata',
            'actorid' => 'privacy:metadata',
            'detailsjson' => 'privacy:metadata',
        ], 'privacy:metadata');

        return $collection;
    }
}

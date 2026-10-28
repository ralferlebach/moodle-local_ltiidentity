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

namespace local_ltiidentity\tests\fixtures;

/**
 * Test probe for the ordering proof (IP-02): what is written, and what the database holds afterwards.
 *
 * - final_update() runs as a before_user_updated callback with a priority below the plugin's (-100), i.e. after the
 *   plugin and directly before core writes: it records the update object exactly as core will write it.
 * - after_write() observes \core\event\user_updated, which core triggers after the write: it records the stored values
 *   at that moment. Any later correction step would have to happen after this point.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_ordering_probe {
    /** @var array[] Update objects as core will write them. */
    public static $final = [];
    /** @var array[] Stored values when core announced the write. */
    public static $afterwrite = [];

    /**
     * Reset the recordings.
     */
    public static function reset(): void {
        self::$final = [];
        self::$afterwrite = [];
    }

    /**
     * Record the update object after every other callback.
     *
     * @param \core_user\hook\before_user_updated $hook The hook.
     */
    public static function final_update(\core_user\hook\before_user_updated $hook): void {
        self::$final[] = get_object_vars($hook->user);
    }

    /**
     * Record the stored profile values right after core's write.
     *
     * @param \core\event\user_updated $event The event.
     */
    public static function after_write(\core\event\user_updated $event): void {
        global $DB;
        self::$afterwrite[] = (array) $DB->get_record(
            'user',
            ['id' => $event->relateduserid ?? $event->objectid],
            'id, firstname, lastname, email, timemodified'
        );
    }
}

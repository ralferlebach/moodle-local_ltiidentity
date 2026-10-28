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
 * Short exclusive sections per user, built on the core lock factory.
 *
 * Used for e-mail request/confirmation and profile saves so that two simultaneous requests (double click,
 * two tabs, link opened twice) cannot both consume the same one-time token or interleave writes. The
 * launch hook never takes a lock: it must not delay a launch and only reads.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locking {
    /** @var int Default lock timeout in seconds. */
    public const DEFAULT_TIMEOUT = 10;

    /**
     * The configured lock timeout, bounded to 1..60 seconds.
     *
     * @return int
     */
    public static function timeout(): int {
        $value = (int) get_config('local_ltiidentity', 'locktimeout');
        return $value >= 1 && $value <= 60 ? $value : self::DEFAULT_TIMEOUT;
    }

    /**
     * Run a callback while holding the lock of a resource.
     *
     * @param string $resource Resource name, e.g. "email_42".
     * @param callable $callback The work.
     * @param bool|null $acquired Set to whether the lock was obtained.
     * @return mixed|null The callback result, or null if the lock could not be acquired in time.
     */
    public static function run(string $resource, callable $callback, ?bool &$acquired = null) {
        $factory = \core\lock\lock_config::get_lock_factory('local_ltiidentity');
        $lock = $factory->get_lock($resource, self::timeout());
        $acquired = (bool) $lock;
        if (!$lock) {
            return null;
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}

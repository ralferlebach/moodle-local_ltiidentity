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
 * Deterministic field priority (IP-P01) as a pure function.
 *
 * Priority: (1) locally locked values, (2) valid transmitted claims, (3) non-empty local old
 * values, (4) placeholders. The admin policy only decides between (2) and (3) for unlocked fields.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy {
    /** @var string Claims overwrite local values unless the field is locked (default). */
    public const CLAIMS_WIN = 'claimswin';
    /** @var string Existing local values always win, claims only fill empty fields. */
    public const LOCAL_WINS = 'localwins';
    /** @var string Claims only replace placeholders and empty values. */
    public const FILL_PLACEHOLDERS = 'fillplaceholders';

    /** @var string Decision: leave the stored value untouched. */
    public const KEEP = 'keep';
    /** @var string Decision: let the incoming value through. */
    public const APPLY = 'apply';

    /**
     * All valid policy identifiers.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::CLAIMS_WIN, self::LOCAL_WINS, self::FILL_PLACEHOLDERS];
    }

    /**
     * The configured policy, falling back to the default for unknown values.
     *
     * @return string
     */
    public static function configured(): string {
        $value = (string) get_config('local_ltiidentity', 'claimpriority');
        return in_array($value, self::all(), true) ? $value : self::CLAIMS_WIN;
    }

    /**
     * Decide what happens to one incoming field of an auth_lti sync.
     *
     * @param string $policy One of the policy constants.
     * @param bool $locked Whether the field is locally locked.
     * @param string $old The stored value.
     * @param bool $oldisplaceholder Whether the stored value is a placeholder.
     * @param string $new The incoming value.
     * @param bool $newisplaceholder Whether the incoming value is a placeholder (claim missing).
     * @return string KEEP or APPLY.
     */
    public static function decide(
        string $policy,
        bool $locked,
        string $old,
        bool $oldisplaceholder,
        string $new,
        bool $newisplaceholder
    ): string {
        if (trim($old) === '') {
            // Nothing to protect.
            return self::APPLY;
        }
        if ($new === $old) {
            return self::APPLY;
        }
        if ($locked) {
            return self::KEEP;
        }
        if (trim($new) === '' || $newisplaceholder) {
            // A missing claim never resets a non-empty local value. A claim of only white space (an empty given_name
            // or family_name passes auth_lti unchanged, the "??" operator only replaces null) counts as missing.
            return self::KEEP;
        }
        switch ($policy) {
            case self::LOCAL_WINS:
                return self::KEEP;
            case self::FILL_PLACEHOLDERS:
                return $oldisplaceholder ? self::APPLY : self::KEEP;
            case self::CLAIMS_WIN:
            default:
                return self::APPLY;
        }
    }
}

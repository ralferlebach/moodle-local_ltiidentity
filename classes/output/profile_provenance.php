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

namespace local_ltiidentity\output;

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use local_ltiidentity\local\email_verifier;
use local_ltiidentity\local\lock_manager;
use local_ltiidentity\local\provenance;

/**
 * Field provenance on the user profile page (IP-04): where first name, last name and e-mail came from.
 *
 * Added to core's profile tree through the myprofile_navigation callback (lib.php), which user/profile.php and
 * user/view.php build in Moodle 4.5 and 5.3 (\core_user\output\myprofile\manager::build_tree(), identical in both).
 * Shows sources and lock states only, never values: the values themselves are shown by core where the viewer may see
 * them.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_provenance {
    /** @var string Name of the profile category. */
    public const CATEGORY = 'local_ltiidentity';

    /**
     * The rows shown for a profile, or null when the viewer may not see them.
     *
     * @param \stdClass $user The profile owner.
     * @param int $viewerid The viewing user.
     * @param \stdClass|null $course The course of a course profile.
     * @return array[]|null Rows with field, label, source, sourcelabel, locked.
     */
    public static function rows(\stdClass $user, int $viewerid, ?\stdClass $course = null): ?array {
        if (!provenance::can_view($user, $viewerid, $course)) {
            return null;
        }
        $labels = [
            'firstname' => get_string('firstname'),
            'lastname' => get_string('lastname'),
            'email' => get_string('email'),
        ];
        $rows = [];
        foreach (provenance::all_of($user) as $field => $source) {
            $locked = lock_manager::provenance((int) $user->id, $field);
            $rows[] = [
                'field' => $field,
                'label' => $labels[$field],
                'source' => $source,
                'sourcelabel' => get_string('provenance_' . $source, 'local_ltiidentity'),
                'locked' => $locked !== '',
            ];
        }
        return $rows;
    }

    /**
     * Text of one row.
     *
     * @param array $row A row of rows().
     * @return string
     */
    public static function row_text(array $row): string {
        $text = $row['sourcelabel'];
        if ($row['locked']) {
            $text .= ' - ' . get_string('provenancelocked', 'local_ltiidentity');
        }
        return $text;
    }

    /**
     * Add the category and its nodes to a profile tree.
     *
     * @param tree $tree The profile tree.
     * @param \stdClass $user The profile owner.
     * @param int $viewerid The viewing user.
     * @param \stdClass|null $course The course of a course profile.
     * @return bool Whether anything was added.
     */
    public static function add_to_tree(tree $tree, \stdClass $user, int $viewerid, ?\stdClass $course = null): bool {
        $rows = self::rows($user, $viewerid, $course);
        if ($rows === null) {
            return false;
        }
        $tree->add_category(new category(self::CATEGORY, get_string('provenance', 'local_ltiidentity')));
        foreach ($rows as $row) {
            $tree->add_node(new node(
                self::CATEGORY,
                'local_ltiidentity_src_' . $row['field'],
                get_string('provenancefield', 'local_ltiidentity', $row['label']),
                null,
                null,
                s(self::row_text($row))
            ));
        }
        $status = 'emailstatus_unverified';
        if (email_verifier::get_pending((int) $user->id)) {
            $status = 'emailstatus_pending';
        } else if (email_verifier::is_verified($user)) {
            $status = 'emailstatus_verified';
        }
        $tree->add_node(new node(
            self::CATEGORY,
            'local_ltiidentity_emailstatus',
            get_string('emailstatus', 'local_ltiidentity'),
            null,
            null,
            s(get_string($status, 'local_ltiidentity'))
        ));
        return true;
    }
}

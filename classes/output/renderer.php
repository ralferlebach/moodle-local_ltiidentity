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

/**
 * Renderer of local_ltiidentity.
 *
 * A PHP-rendered table is used on purpose: it keeps the plugin free of Mustache templates, whose
 * lint step needs an external HTML validator service that is not always reachable.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the list of LTI users of a course.
     *
     * @param array $rows Row data: fullname, placeholders (labels), locks (label, source, releaseurl), emailstatus, editurl.
     * @return string
     */
    public function user_list(array $rows): string {
        if (!$rows) {
            return $this->output->notification(get_string('nousers', 'local_ltiidentity'), 'info');
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-ltiidentity-users';
        $table->caption = get_string('ltiusers', 'local_ltiidentity');
        $table->captionhide = true;
        $table->head = [
            get_string('fullname'),
            get_string('placeholderfields', 'local_ltiidentity'),
            get_string('lockedfields', 'local_ltiidentity'),
            get_string('emailstatus', 'local_ltiidentity'),
            get_string('actions'),
        ];
        $table->colclasses = ['', '', '', '', ''];
        foreach ($rows as $row) {
            $placeholders = '-';
            if ($row['placeholders']) {
                $badges = array_map(static function (string $label): string {
                    return \html_writer::span(s($label), 'badge bg-warning text-dark');
                }, $row['placeholders']);
                $placeholders = implode(' ', $badges);
            }
            $locks = '-';
            if ($row['locks']) {
                $items = [];
                foreach ($row['locks'] as $lock) {
                    $text = s($lock['label']) . ' (' . s($lock['source']) . ')';
                    if (!empty($lock['releaseurl'])) {
                        $text .= ' ' . \html_writer::link(
                            $lock['releaseurl'],
                            get_string('releaselock', 'local_ltiidentity')
                        );
                    }
                    $items[] = $text;
                }
                $locks = \html_writer::alist($items, ['class' => 'list-unstyled mb-0']);
            }
            $cells = [
                new \html_table_cell(s($row['fullname'])),
                new \html_table_cell($placeholders),
                new \html_table_cell($locks),
                new \html_table_cell(s($row['emailstatus'])),
                new \html_table_cell(\html_writer::link(
                    $row['editurl'],
                    get_string('editprofile', 'local_ltiidentity')
                )),
            ];
            $cells[0]->header = true;
            $cells[0]->scope = 'row';
            $table->data[] = new \html_table_row($cells);
        }
        return \html_writer::table($table);
    }
}

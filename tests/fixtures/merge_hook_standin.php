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
 * Stand-in with the public surface of \tool_mergeusers\hook\after_merged_all_tables (tool_mergeusers is not
 * installed in the test trees).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class merge_hook_standin {
    /** @var int The surviving account. */
    public $toid;
    /** @var int The merged-away account. */
    public $fromid;
    /** @var string[] Collected log lines. */
    public $logs = [];
    /** @var string[] Collected error messages. */
    public $errors = [];

    /**
     * Constructor.
     *
     * @param int $toid The surviving account.
     * @param int $fromid The merged-away account.
     */
    public function __construct(int $toid, int $fromid) {
        $this->toid = $toid;
        $this->fromid = $fromid;
    }

    /**
     * Collect a log line.
     *
     * @param string $logline The line.
     */
    public function add_log(string $logline): void {
        $this->logs[] = $logline;
    }

    /**
     * Collect an error message.
     *
     * @param string $errorline The line.
     */
    public function add_error_message(string $errorline): void {
        $this->errors[] = $errorline;
    }
}

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

namespace local_ltiidentity\event;

/**
 * An LTI synchronisation or placeholder write was filtered to protect local profile values.
 *
 * Carries field names only, never names or addresses. Triggered only when the setting "logprotected" is on.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_protected extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_syncprotected', 'local_ltiidentity');
    }

    /**
     * Description without personal values.
     *
     * @return string
     */
    public function get_description() {
        $fields = implode(', ', (array) ($this->other['fields'] ?? []));
        return "An update of the LTI account with id '{$this->objectid}' was filtered; protected fields: $fields.";
    }

    /**
     * Validate the other data.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['fields']) || !is_array($this->other['fields'])) {
            throw new \coding_exception('The \'fields\' value must be set in other.');
        }
        if (empty($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
    }

    /**
     * Mapping for the legacy object id.
     *
     * @return bool
     */
    public static function get_objectid_mapping() {
        return ['db' => 'user', 'restore' => 'user'];
    }

    /**
     * Mapping of other data on restore: none is restored.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}

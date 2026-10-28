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

namespace local_ltiidentity;

/**
 * Fresh installation of release 0.3.0: structure and setting defaults are present after install, the schema equals
 * install.xml and the release information is consistent (upgrade paths: upgrade_test).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class install_test extends \advanced_testcase {
    /**
     * A fresh install has the audit index on timecreated and the defaults of the settings (formerly added by the upgrade).
     */
    public function test_fresh_install_has_index_and_defaults(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->index_exists(
            new \xmldb_table('local_ltiidentity_audit'),
            new \xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated'])
        ));
        $this->assertEquals(10, get_config('local_ltiidentity', 'locktimeout'));
        $this->assertEquals(730, get_config('local_ltiidentity', 'auditretention'));
        $this->assertEquals(0, get_config('local_ltiidentity', 'logprotected'));
    }

    /**
     * The installed tables equal install.xml.
     */
    public function test_schema_equals_install_xml(): void {
        global $DB, $CFG;
        $xml = simplexml_load_file($CFG->dirroot . '/local/ltiidentity/db/install.xml');
        $this->assertSame('local/ltiidentity/db', (string) $xml['PATH']);
        $count = 0;
        foreach ($xml->TABLES->TABLE as $table) {
            $fields = [];
            foreach ($table->FIELDS->FIELD as $field) {
                $fields[] = (string) $field['NAME'];
            }
            $actual = array_keys($DB->get_columns((string) $table['NAME']));
            sort($fields);
            sort($actual);
            $this->assertSame($fields, $actual, 'table ' . $table['NAME']);
            $count++;
        }
        $this->assertSame(1, $count);
    }

    /**
     * The installed plugin reports 0.3.0; the change log and the migration note record that the development rump is
     * replaced without an upgrade path.
     */
    public function test_release_is_030(): void {
        global $CFG;
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_ltiidentity');
        $this->assertSame('0.3.0', $plugin->release);
        $this->assertSame(2026102700, (int) $plugin->versiondisk);
        $changelog = file_get_contents($CFG->dirroot . '/local/ltiidentity/docs/CHANGELOG.md');
        $this->assertStringContainsString('## 0.3.0', $changelog);
        $this->assertStringContainsString('no upgrade path from the development rump', $changelog);
        $migration = file_get_contents($CFG->dirroot . '/local/ltiidentity/docs/MIGRATION.md');
        $this->assertStringContainsString('2026042200', $migration);
    }
}

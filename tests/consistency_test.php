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
 * IP-01: component, install path, language files, schema, namespaces, capabilities and package name agree, and nothing
 * of the replaced development rump (0.1.0, 2026042200) or of the former component name is left in the code.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class consistency_test extends \advanced_testcase {
    /** @var string The component. */
    private const COMPONENT = 'local_ltiidentity';

    /**
     * Plugin directory.
     *
     * @return string
     */
    private function dir(): string {
        return \core_component::get_component_directory(self::COMPONENT);
    }

    /**
     * Shipped PHP files (relative paths), without the test files of this class.
     *
     * @return string[]
     */
    private function php_files(): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->dir(),
            \FilesystemIterator::SKIP_DOTS
        ));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($this->dir()) + 1);
            if ($file->getExtension() === 'php' && !str_starts_with($relative, 'tools/')) {
                $files[] = $relative;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Component, install path and release metadata.
     */
    public function test_component_and_path(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($this->dir() . '/version.php');
        $this->assertSame(self::COMPONENT, $plugin->component);
        $this->assertSame(2026102700, $plugin->version);
        $this->assertSame('0.3.0', $plugin->release);
        $this->assertSame(2024100700, $plugin->requires);
        $this->assertSame([405, 503], $plugin->supported);
        $this->assertSame($CFG->dirroot . '/local/ltiidentity', $this->dir());
        $this->assertSame(['local', 'ltiidentity'], \core_component::normalize_component(self::COMPONENT));
    }

    /**
     * Language files carry the component name, exist in en and de with the same keys.
     */
    public function test_language_files(): void {
        $this->assertSame(['de', 'en'], array_values(array_diff(scandir($this->dir() . '/lang'), ['.', '..'])));
        $keys = [];
        foreach (['en', 'de'] as $lang) {
            $files = array_values(array_diff(scandir($this->dir() . "/lang/$lang"), ['.', '..']));
            $this->assertSame([self::COMPONENT . '.php'], $files, $lang);
            $string = [];
            include($this->dir() . "/lang/$lang/" . self::COMPONENT . '.php');
            $keys[$lang] = array_keys($string);
            sort($keys[$lang]);
        }
        $this->assertSame($keys['en'], $keys['de']);
        $this->assertContains('pluginname', $keys['en']);
    }

    /**
     * install.xml path and table names match the component; the installed tables equal install.xml (none of the rump).
     */
    public function test_schema(): void {
        global $DB;
        $xml = simplexml_load_file($this->dir() . '/db/install.xml');
        $this->assertSame('local/ltiidentity/db', (string) $xml['PATH']);
        $declared = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $declared[] = (string) $table['NAME'];
        }
        foreach ($declared as $table) {
            $this->assertStringStartsWith(self::COMPONENT . '_', $table);
        }
        $installed = array_values(array_filter($DB->get_tables(false), static function (string $table): bool {
            return str_starts_with($table, self::COMPONENT . '_');
        }));
        sort($installed);
        sort($declared);
        $this->assertSame($declared, $installed);
        foreach (['override', 'mailtoken', 'namerule'] as $rumptable) {
            $this->assertNotContains(self::COMPONENT . '_' . $rumptable, $installed, 'Rump table present');
        }
    }

    /**
     * Every PHP file names the package, every namespace and capability uses the component.
     */
    public function test_package_namespaces_and_capabilities(): void {
        foreach ($this->php_files() as $file) {
            $source = file_get_contents($this->dir() . '/' . $file);
            $this->assertMatchesRegularExpression('/@package\s+' . self::COMPONENT . '\s/', $source, $file);
            $this->assertDoesNotMatchRegularExpression('/@package\s+(?!' . self::COMPONENT . '\s)\S+/', $source, $file);
            if (preg_match('/^namespace\s+([^;]+);/m', $source, $match)) {
                $this->assertStringStartsWith(self::COMPONENT, $match[1], $file);
            }
            if (preg_match_all("/'(local\/[a-z0-9_]+):[a-z]+'/", $source, $caps)) {
                foreach ($caps[1] as $prefix) {
                    $this->assertSame('local/ltiidentity', $prefix, $file);
                }
            }
        }
        $capabilities = [];
        include($this->dir() . '/db/access.php');
        foreach (array_keys($capabilities) as $name) {
            $this->assertStringStartsWith('local/ltiidentity:', $name);
        }
    }

    /**
     * No code refers to the former component name, the rump's tables, classes or its post-login observer.
     */
    public function test_no_old_names_or_rump_artefacts(): void {
        $forbidden = [
            'ltiidentityprofile',
            'local_ltiidentity_override',
            'local_ltiidentity_mailtoken',
            'local_ltiidentity_namerule',
            'reconciliation_service',
            'override_repository',
            'name_rule_resolver',
            'event\\observer',
            'user_loggedin',
        ];
        foreach ($this->php_files() as $file) {
            if (str_starts_with($file, 'tests/') || $file === 'db/upgrade.php') {
                continue;
            }
            $source = file_get_contents($this->dir() . '/' . $file);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $source, "$file mentions $needle");
            }
        }
    }

    /**
     * Mirror of the CI stale-files job: no file listed in db/removed_files.txt (rump files among them) exists.
     */
    public function test_removed_files_are_absent(): void {
        $list = file($this->dir() . '/db/removed_files.txt', FILE_IGNORE_NEW_LINES);
        $paths = array_filter($list, static function (string $line): bool {
            return trim($line) !== '' && !str_starts_with($line, '#');
        });
        $this->assertContains('db/events.php', $paths, 'The rump observer registration is listed');
        $this->assertContains('classes/event/observer.php', $paths);
        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($this->dir() . '/' . $path);
        }
    }

    /**
     * Build tooling names the same package.
     */
    public function test_tooling_names(): void {
        $makefile = file_get_contents($this->dir() . '/makefile');
        $this->assertMatchesRegularExpression('/^PLUGIN_NAME\s+\?=\s+local_ltiidentity$/m', $makefile);
        $this->assertMatchesRegularExpression('/^PLUGIN_REL\s+\?=\s+local\/ltiidentity$/m', $makefile);
        $this->assertStringContainsString('<ruleset name="local_ltiidentity">', file_get_contents($this->dir() . '/phpcs.xml'));
    }
}

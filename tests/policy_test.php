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

use local_ltiidentity\local\policy;

/**
 * Unit tests for the pure field priority decision (IP-P01).
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ltiidentity\local\policy
 */
final class policy_test extends \advanced_testcase {
    /**
     * Decision matrix.
     *
     * @dataProvider decision_provider
     * @param string $policy Policy.
     * @param bool $locked Locked.
     * @param string $old Stored value.
     * @param bool $oldph Stored value is a placeholder.
     * @param string $new Incoming value.
     * @param bool $newph Incoming value is a placeholder.
     * @param string $expected Expected decision.
     */
    public function test_decide(
        string $policy,
        bool $locked,
        string $old,
        bool $oldph,
        string $new,
        bool $newph,
        string $expected
    ): void {
        $this->assertSame($expected, policy::decide($policy, $locked, $old, $oldph, $new, $newph));
    }

    /**
     * Cases for test_decide.
     *
     * @return array
     */
    public static function decision_provider(): array {
        $k = policy::KEEP;
        $a = policy::APPLY;
        $cases = [];
        foreach (policy::all() as $p) {
            // Priority 1: a lock beats a real claim under every policy.
            $cases["$p locked beats claim"] = [$p, true, 'Local', false, 'Claim', false, $k];
            // Priority 3 over 4: a placeholder never replaces a non-empty local value.
            $cases["$p placeholder keeps local"] = [$p, false, 'Local', false, 'ph', true, $k];
            $cases["$p placeholder keeps local placeholder"] = [$p, false, 'ph1', true, 'ph2', true, $k];
            // Empty claim never resets.
            $cases["$p empty claim keeps local"] = [$p, false, 'Local', false, '', false, $k];
            // White space only counts as a missing claim (0.3.0, IP-02 contradictory claims).
            $cases["$p blank claim keeps local"] = [$p, false, 'Local', false, "  \t", false, $k];
            // A blank stored value has nothing to protect.
            $cases["$p blank local takes claim"] = [$p, false, '  ', false, 'Claim', false, $a];
            // Nothing stored: anything goes through.
            $cases["$p empty local takes placeholder"] = [$p, false, '', false, 'ph', true, $a];
            $cases["$p empty local takes claim"] = [$p, true, '', false, 'Claim', false, $a];
            // Identical values are harmless.
            $cases["$p same value"] = [$p, true, 'Same', false, 'Same', false, $a];
        }
        $cases['claimswin overrides unlocked local'] = [policy::CLAIMS_WIN, false, 'Local', false, 'Claim', false, $a];
        $cases['claimswin replaces placeholder'] = [policy::CLAIMS_WIN, false, 'ph', true, 'Claim', false, $a];
        $cases['localwins keeps unlocked local'] = [policy::LOCAL_WINS, false, 'Local', false, 'Claim', false, $k];
        $cases['localwins keeps local placeholder'] = [policy::LOCAL_WINS, false, 'ph', true, 'Claim', false, $k];
        $cases['fill keeps real local'] = [policy::FILL_PLACEHOLDERS, false, 'Local', false, 'Claim', false, $k];
        $cases['fill replaces placeholder'] = [policy::FILL_PLACEHOLDERS, false, 'ph', true, 'Claim', false, $a];
        return $cases;
    }

    /**
     * Unknown configuration values fall back to the default.
     */
    public function test_configured_falls_back_to_default(): void {
        $this->resetAfterTest();
        $this->assertSame(policy::CLAIMS_WIN, policy::configured());
        set_config('claimpriority', 'garbage', 'local_ltiidentity');
        $this->assertSame(policy::CLAIMS_WIN, policy::configured());
        set_config('claimpriority', policy::LOCAL_WINS, 'local_ltiidentity');
        $this->assertSame(policy::LOCAL_WINS, policy::configured());
    }

    /**
     * Unknown policy value is treated like the default in decide().
     */
    public function test_decide_unknown_policy_is_default(): void {
        $this->assertSame(policy::APPLY, policy::decide('nonsense', false, 'Local', false, 'Claim', false));
    }
}

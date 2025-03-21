<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Badges external functions tests.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

namespace core_badges\external;

use core_badges\tests\external_helper;
use core_external\external_api;
use core_external\external_settings;
use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Tests for get_user_badges external function
 *
 * @package    core_badges
 * @category   test
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @author     2016 Juan Leyva <juan@moodle.com>
 * @author     2025 Dai Nguyen Trong <ngtrdai@hotmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_badges\external\get_user_badges
 */
final class get_user_badges_test extends externallib_advanced_testcase {
    use external_helper;

    /**
     * Test getting user's own badges.
     */
    public function test_get_own_user_badges(): void {
        $data = $this->prepare_test_data();

        $this->setUser($data['student']);
        $result = get_user_badges::execute();
        $result = external_api::clean_returnvalue(get_user_badges::execute_returns(), $result);
        $this->assertCount(2, $result['badges']);
        $this->assert_issued_badge($data['coursebadge'], $result['badges'][0], true, false);
        $this->assert_issued_badge($data['sitebadge'], $result['badges'][1], true, false);

        // Test pagination and filtering.
        $result = get_user_badges::execute(0, 0, 0, 1, '', true);
        $result = external_api::clean_returnvalue(get_user_badges::execute_returns(), $result);
        $this->assertCount(1, $result['badges']);
        $this->assert_issued_badge($data['coursebadge'], $result['badges'][0], true, false);
    }

    /**
     * Test get_user_badges with filtered issuername content.
     */
    public function test_get_user_badges_filter_issuername(): void {
        global $DB;

        $data = $this->prepare_test_data();

        // Enable multilang filter.
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
        external_settings::get_instance()->set_filter(true);

        // Update badge issuer name with multilang content.
        $issuername = '<span class="multilang" lang="en">Issuer (en)</span><span class="multilang" lang="es">Issuer (es)</span>';
        $DB->set_field('badge', 'issuername', $issuername, ['name' => 'Test badge site']);

        // Test that filtered content is returned correctly.
        $result = get_user_badges::execute($data['student']->id);
        $result = external_api::clean_returnvalue(get_user_badges::execute_returns(), $result);

        // Find the site badge (will be last since it has the earlier issued date).
        $badge = end($result['badges']);
        $this->assertEquals('Issuer (en)', $badge['issuername']);
    }

    /**
     * Test badges disabled at site level.
     */
    public function test_badges_disabled(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Disable badges.
        $CFG->enablebadges = 0;

        $this->expectException(\core\exception\moodle_exception::class);
        $this->expectExceptionMessage(get_string('badgesdisabled', 'badges'));

        get_user_badges::execute();
    }

    /**
     * Test course badges disabled when filtering by course.
     */
    public function test_course_badges_disabled(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Disable course badges.
        $CFG->badges_allowcoursebadges = 0;

        $this->expectException(\core\exception\moodle_exception::class);
        $this->expectExceptionMessage(get_string('coursebadgesdisabled', 'badges'));

        get_user_badges::execute(0, 1);
    }
}

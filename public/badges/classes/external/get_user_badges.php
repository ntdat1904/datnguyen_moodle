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

namespace core_badges\external;

use context_user;
use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core\exception\moodle_exception;
use core_external\external_function_parameters;
use core_external\external_warnings;
use core_user;

/**
 * External service to get user badges.
 *
 * @package   core_badges
 * @category  external
 * @copyright 2025 Dai Nguyen Trong <ngtrdai@hotmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_badges extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Badges only for this user id, empty for current user', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Filter badges by course id, empty all the courses', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_RAW, 'A simple string to search for', VALUE_DEFAULT, ''),
            'onlypublic' => new external_value(PARAM_BOOL, 'Whether to return only public badges', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Returns the list of badges awarded to a user.
     *
     * @param int $userid User id
     * @param int $courseid Course id
     * @param int $page Page of records to return
     * @param int $perpage Number of records to return per page
     * @param string $search A simple string to search for
     * @param bool $onlypublic Whether to return only public badges
     * @return array Array containing warnings and the awarded badges
     */
    public static function execute(
        int $userid = 0,
        int $courseid = 0,
        int $page = 0,
        int $perpage = 0,
        string $search = '',
        bool $onlypublic = false
    ): array {
        global $CFG, $USER;

        require_once($CFG->libdir . '/badgeslib.php');

        $warnings = [];

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage,
            'search' => $search,
            'onlypublic' => $onlypublic,
        ]);

        if (empty($CFG->enablebadges)) {
            throw new moodle_exception('badgesdisabled', 'badges');
        }

        if (empty($CFG->badges_allowcoursebadges) && $params['courseid'] != 0) {
            throw new moodle_exception('coursebadgesdisabled', 'badges');
        }

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Validate the user.
        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        $usercontext = context_user::instance($user->id);
        self::validate_context($usercontext);

        if ($USER->id != $user->id) {
            require_capability('moodle/badges:viewotherbadges', $usercontext);
            // We are looking other user's badges, we must retrieve only public badges.
            $params['onlypublic'] = true;
        }

        $userbadges = badges_get_user_badges(
            $user->id,
            $params['courseid'],
            $params['page'],
            $params['perpage'],
            $params['search'],
            $params['onlypublic']
        );

        $result = [
            'badges' => [],
            'warnings' => $warnings,
        ];

        foreach ($userbadges as $badge) {
            $result['badges'][] = badges_prepare_badge_for_external($badge, $user);
        }

        return $result;
    }

    /**
     * Describes the return structure of the external service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'badges' => new external_multiple_structure(
                user_badge_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}

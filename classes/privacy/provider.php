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
 * Privacy provider for mod_webexmeetings
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexmeetings\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('webexmeetings_attendance', [
            'userid' => 'privacy:metadata:webexmeetings_attendance:userid',
            'join_time' => 'privacy:metadata:webexmeetings_attendance:join_time',
            'leave_time' => 'privacy:metadata:webexmeetings_attendance:leave_time',
            'duration' => 'privacy:metadata:webexmeetings_attendance:duration',
        ], 'privacy:metadata:webexmeetings_attendance');

        $collection->add_database_table('webexmeetings_sessions', [
            'userid' => 'privacy:metadata:webexmeetings_sessions:userid',
            'join_time' => 'privacy:metadata:webexmeetings_sessions:join_time',
            'leave_time' => 'privacy:metadata:webexmeetings_sessions:leave_time',
            'duration' => 'privacy:metadata:webexmeetings_sessions:duration',
            'ip_address' => 'privacy:metadata:webexmeetings_sessions:ip_address',
        ], 'privacy:metadata:webexmeetings_sessions');

        $collection->add_external_location_link('webex', [
            'email' => 'privacy:metadata:webex:email',
            'fullname' => 'privacy:metadata:webex:fullname',
        ], 'privacy:metadata:webex');

        return $collection;
    }

    /**
     * Get contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {webexmeetings} wm ON wm.id = cm.instance
                  JOIN {webexmeetings_attendance} wa ON wa.meetingid = wm.id
                 WHERE wa.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'webexmeetings',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT wa.userid
                  FROM {webexmeetings_attendance} wa
                  JOIN {webexmeetings} wm ON wm.id = wa.meetingid
                  JOIN {course_modules} cm ON cm.instance = wm.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'webexmeetings'
                 WHERE cm.id = :cmid";

        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // Minimal implementation for now.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('webexmeetings', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('webexmeetings_sessions', ['meetingid' => $cm->instance]);
        $DB->delete_records('webexmeetings_attendance', ['meetingid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('webexmeetings', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('webexmeetings_sessions', ['meetingid' => $cm->instance, 'userid' => $userid]);
            $DB->delete_records('webexmeetings_attendance', ['meetingid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('webexmeetings', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['meetingid' => $cm->instance], $userparams);

        $DB->delete_records_select('webexmeetings_sessions',
            "meetingid = :meetingid AND userid $usersql", $params);
        $DB->delete_records_select('webexmeetings_attendance',
            "meetingid = :meetingid AND userid $usersql", $params);
    }
}

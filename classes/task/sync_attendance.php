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
 * Scheduled task to sync attendance from Webex
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webexmeetings\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Sync attendance task
 */
class sync_attendance extends \core\task\scheduled_task {
    
    /**
     * Task name
     * @return string
     */
    public function get_name() {
        return get_string('syncattendancetask', 'mod_webexmeetings');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        $lookback_days = get_config('mod_webexmeetings', 'sync_lookback_days') ?: 7;
        $since = time() - ($lookback_days * 86400);
        
        // Get meetings that need sync
        $sql = "SELECT wm.*, cm.id as cmid, c.id as courseid
                FROM {webexmeetings} wm
                JOIN {course_modules} cm ON cm.instance = wm.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'webexmeetings'
                JOIN {course} c ON c.id = wm.course
                WHERE wm.track_attendance = 1
                  AND wm.meeting_id IS NOT NULL
                  AND wm.meeting_id != ''
                  AND wm.start_time >= ?
                  AND (wm.last_sync IS NULL OR wm.last_sync < (? - 300))
                ORDER BY wm.last_sync ASC
                LIMIT 20";
        
        $meetings = $DB->get_records_sql($sql, array($since, time()));
        
        if (empty($meetings)) {
            mtrace('No Webex meetings to sync.');
            return;
        }
        
        mtrace('Found ' . count($meetings) . ' meetings to sync.');
        
        try {
            $webex_api = new \mod_webexmeetings\external\webex_api();
        } catch (\Exception $e) {
            mtrace('Failed to initialize Webex API: ' . $e->getMessage());
            return;
        }
        
        foreach ($meetings as $meeting) {
            mtrace('Syncing meeting: ' . $meeting->name . ' (ID: ' . $meeting->meeting_id . ')');
            
            try {
                $attendance_data = $webex_api->get_attendance($meeting->meeting_id);
                
                if ($attendance_data && !empty($attendance_data)) {
                    $synced = 0;
                    
                    foreach ($attendance_data as $participant) {
                        if (empty($participant->userid)) {
                            continue;
                        }
                        
                        // Process each session
                        foreach ($participant->sessions as $session_data) {
                            $sessionid = $meeting->meeting_id . '_' . $participant->userid . '_' . $session_data->join_time;
                            
                            $existing = $DB->get_record('webexmeetings_sessions', array(
                                'meetingid' => $meeting->id,
                                'userid' => $participant->userid,
                                'join_time' => $session_data->join_time
                            ));
                            
                            if (!$existing) {
                                $record = new \stdClass();
                                $record->meetingid = $meeting->id;
                                $record->userid = $participant->userid;
                                $record->sessionid = $sessionid;
                                $record->join_time = $session_data->join_time;
                                $record->leave_time = $session_data->leave_time;
                                $record->duration = $session_data->duration;
                                
                                $DB->insert_record('webexmeetings_sessions', $record);
                            } else if ($existing->leave_time != $session_data->leave_time) {
                                $existing->leave_time = $session_data->leave_time;
                                $existing->duration = $session_data->duration;
                                $DB->update_record('webexmeetings_sessions', $existing);
                            }
                        }
                        
                        // Update attendance summary
                        require_once($GLOBALS['CFG']->dirroot . '/mod/webexmeetings/lib.php');
                        webexmeetings_update_attendance_summary($meeting->id, $participant->userid);
                        $synced++;
                    }
                    
                    $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $meeting->id));
                    $DB->set_field('webexmeetings', 'sync_status', 'success', array('id' => $meeting->id));
                    
                    mtrace('  Synced ' . $synced . ' participants.');
                    
                    // Trigger event
                    $context = \context_module::instance($meeting->cmid);
                    $event = \mod_webexmeetings\event\attendance_synced::create(array(
                        'objectid' => $meeting->id,
                        'context' => $context,
                        'other' => array('count' => $synced)
                    ));
                    $event->trigger();
                } else {
                    $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $meeting->id));
                    $DB->set_field('webexmeetings', 'sync_status', 'nodata', array('id' => $meeting->id));
                    mtrace('  No attendance data available yet.');
                }
            } catch (\Exception $e) {
                $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $meeting->id));
                $DB->set_field('webexmeetings', 'sync_status', 'error', array('id' => $meeting->id));
                mtrace('  Error: ' . $e->getMessage());
            }
        }
    }
}

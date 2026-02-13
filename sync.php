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
 * Manual sync of attendance data from Webex
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/webexmeetings/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID

$cm = get_coursemodule_from_id('webexmeetings', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$webexmeeting = $DB->get_record('webexmeetings', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webexmeetings:syncattendance', $context);

$PAGE->set_url('/mod/webexmeetings/sync.php', array('id' => $cm->id));
$PAGE->set_title(get_string('syncattendance', 'webexmeetings'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('syncattendance', 'webexmeetings') . ': ' . format_string($webexmeeting->name));

if (empty($webexmeeting->meeting_id)) {
    echo $OUTPUT->notification(get_string('nomeetingid', 'webexmeetings'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

try {
    $webex_api = new \mod_webexmeetings\external\webex_api();
    $attendance_data = $webex_api->get_attendance($webexmeeting->meeting_id);
    
    if ($attendance_data && !empty($attendance_data)) {
        $synced = 0;
        
        foreach ($attendance_data as $participant) {
            if (empty($participant->userid)) {
                continue;
            }
            
            // Process each session
            foreach ($participant->sessions as $session_data) {
                $sessionid = $webexmeeting->meeting_id . '_' . $participant->userid . '_' . $session_data->join_time;
                
                // Check if session already exists
                $existing = $DB->get_record('webexmeetings_sessions', array(
                    'meetingid' => $webexmeeting->id,
                    'userid' => $participant->userid,
                    'join_time' => $session_data->join_time
                ));
                
                if (!$existing) {
                    $record = new stdClass();
                    $record->meetingid = $webexmeeting->id;
                    $record->userid = $participant->userid;
                    $record->sessionid = $sessionid;
                    $record->join_time = $session_data->join_time;
                    $record->leave_time = $session_data->leave_time;
                    $record->duration = $session_data->duration;
                    
                    $DB->insert_record('webexmeetings_sessions', $record);
                } else {
                    // Update if leave_time changed
                    if ($existing->leave_time != $session_data->leave_time) {
                        $existing->leave_time = $session_data->leave_time;
                        $existing->duration = $session_data->duration;
                        $DB->update_record('webexmeetings_sessions', $existing);
                    }
                }
            }
            
            // Update attendance summary
            webexmeetings_update_attendance_summary($webexmeeting->id, $participant->userid);
            $synced++;
        }
        
        // Update last_sync and sync_status
        $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $webexmeeting->id));
        $DB->set_field('webexmeetings', 'sync_status', 'success', array('id' => $webexmeeting->id));
        
        echo $OUTPUT->notification(
            get_string('syncsuccess', 'webexmeetings', $synced),
            \core\output\notification::NOTIFY_SUCCESS
        );
        
        // Trigger event
        $event = \mod_webexmeetings\event\attendance_synced::create(array(
            'objectid' => $webexmeeting->id,
            'context' => $context,
            'other' => array('count' => $synced)
        ));
        $event->trigger();
    } else {
        $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $webexmeeting->id));
        $DB->set_field('webexmeetings', 'sync_status', 'nodata', array('id' => $webexmeeting->id));
        
        echo $OUTPUT->notification(
            get_string('noattendancedata', 'webexmeetings'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
} catch (Exception $e) {
    $DB->set_field('webexmeetings', 'last_sync', time(), array('id' => $webexmeeting->id));
    $DB->set_field('webexmeetings', 'sync_status', 'error', array('id' => $webexmeeting->id));
    
    $msg = webexmeetings_extract_error_message($e);
    echo $OUTPUT->notification(
        get_string('syncerror', 'webexmeetings', $msg),
        \core\output\notification::NOTIFY_ERROR
    );
}

// Return button
$returnurl = new moodle_url('/mod/webexmeetings/view.php', array('id' => $cm->id));
echo $OUTPUT->continue_button($returnurl);

echo $OUTPUT->footer();
